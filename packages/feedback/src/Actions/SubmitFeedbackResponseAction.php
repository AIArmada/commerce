<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Data\SubmitFeedbackResponseData;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Events\FeedbackResponseSubmitted;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackInvitation;
use AIArmada\Feedback\Models\FeedbackResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class SubmitFeedbackResponseAction
{
    public function __construct(
        private readonly StartFeedbackResponseAction $startResponse,
        private readonly ValidateFeedbackAnswersAction $validateAnswers,
        private readonly NormalizeFeedbackAnswerAction $normalizeAnswer,
        private readonly CalculateFeedbackAnswerScoreAction $calculateAnswerScore,
        private readonly CalculateFeedbackResponseScoreAction $calculateResponseScore,
        private readonly ExtractFeedbackTestimonialAction $extractTestimonial,
    ) {}

    public function execute(SubmitFeedbackResponseData $data): FeedbackResponse
    {
        return retry(
            5,
            fn (): FeedbackResponse => $this->executeWithinTransaction($data),
            50,
            fn (Throwable $exception): bool => $exception instanceof DeadlockException,
        );
    }

    private function executeWithinTransaction(SubmitFeedbackResponseData $data): FeedbackResponse
    {
        return DB::transaction(function () use ($data): FeedbackResponse {
            $guardedForm = OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $data->formId);
            $form = FeedbackForm::with('questions.options')
                ->lockForUpdate()
                ->whereKey($guardedForm->getKey())
                ->firstOrFail();

            $this->assertFormAcceptingSubmissions($form, $data);
            $this->assertSubmittedQuestionsBelongToForm($form, $data);

            $existingResponse = $this->findExistingSubmittedResponse($form, $data);

            if ($existingResponse !== null) {
                return $existingResponse;
            }

            $invitation = null;
            if ($data->invitationId !== null) {
                $guardedInvitation = OwnerWriteGuard::findOrFailForOwner(
                    FeedbackInvitation::class,
                    $data->invitationId,
                );
                $invitation = FeedbackInvitation::query()
                    ->lockForUpdate()
                    ->whereKey($guardedInvitation->getKey())
                    ->firstOrFail();
                $this->assertInvitationValid($invitation, $form);
            }

            try {
                return DB::transaction(function () use ($form, $data, $invitation): FeedbackResponse {
                    $response = $this->startResponse->execute(
                        form: $form,
                        respondentType: $data->respondentType,
                        respondentId: $data->respondentId,
                        invitation: $invitation,
                        isAnonymous: $data->isAnonymous,
                    );

                    $submittedValues = [];
                    foreach ($data->answers as $answer) {
                        $submittedValues[$answer->questionKey] = $answer->value;
                    }

                    $visibleQuestions = $this->validateAnswers->execute($form, $submittedValues);

                    $answerModels = [];
                    foreach ($visibleQuestions as $question) {
                        $value = $submittedValues[$question->key] ?? null;

                        if ($value === null && ! $question->is_required) {
                            continue;
                        }

                        $normalized = $this->normalizeAnswer->execute($question, $value);
                        $score = $this->calculateAnswerScore->execute($question, $value);

                        $answerData = array_merge($normalized, [
                            'feedback_response_id' => $response->id,
                            'feedback_question_id' => $question->id,
                            'score' => $score,
                        ]);

                        $answerModels[] = $response->answers()->create($answerData);
                    }

                    $response->forceFill([
                        'status' => FeedbackResponseStatus::Submitted,
                        'enforce_respondent_uniqueness' => $form->is_one_response_per_respondent,
                        'submitted_at' => CarbonImmutable::now(),
                        'ip_address' => $data->ipAddress,
                        'user_agent' => $data->userAgent,
                    ])->save();

                    if (isset($invitation)) {
                        $invitation->forceFill([
                            'status' => FeedbackInvitationStatus::Submitted,
                            'submitted_at' => CarbonImmutable::now(),
                        ])->save();
                    }

                    $this->calculateResponseScore->execute($response);

                    if (config('feedback.features.testimonials', true)) {
                        $this->extractTestimonial->execute($response);
                    }

                    FeedbackResponseSubmitted::dispatch($response);

                    return $response;
                }, 5);
            } catch (QueryException $exception) {
                if (! $this->isDuplicateResponseConstraintViolation($exception)) {
                    throw $exception;
                }

                $existingResponse = $this->findExistingSubmittedResponse($form, $data);

                if ($existingResponse === null) {
                    throw $exception;
                }

                return $existingResponse;
            }
        }, 5);
    }

    private function assertFormAcceptingSubmissions(FeedbackForm $form, SubmitFeedbackResponseData $data): void
    {
        if ($form->status !== FeedbackFormStatus::Published) {
            throw new RuntimeException('This form is not accepting submissions.');
        }

        if ($form->opens_at !== null && CarbonImmutable::now()->isBefore($form->opens_at)) {
            throw new RuntimeException('This form has not opened yet.');
        }

        if ($form->closes_at !== null && CarbonImmutable::now()->isAfter($form->closes_at)) {
            throw new RuntimeException('This form has closed.');
        }

        if ($data->isAnonymous && ! $form->is_anonymous_allowed) {
            throw new RuntimeException('Anonymous submissions are not allowed for this form.');
        }

        if ($form->is_login_required && ($data->respondentType === null || $data->respondentId === null)) {
            throw new RuntimeException('You must be logged in to submit this form.');
        }
    }

    private function findExistingSubmittedResponse(
        FeedbackForm $form,
        SubmitFeedbackResponseData $data,
    ): ?FeedbackResponse {
        if (! $form->is_one_response_per_respondent
            || $data->respondentType === null
            || $data->respondentId === null) {
            return null;
        }

        return FeedbackResponse::query()
            ->lockForUpdate()
            ->where('feedback_form_id', $form->id)
            ->where('respondent_type', $data->respondentType)
            ->where('respondent_id', $data->respondentId)
            ->where('status', FeedbackResponseStatus::Submitted)
            ->first();
    }

    private function isDuplicateResponseConstraintViolation(QueryException $exception): bool
    {
        return in_array(
            (string) ($exception->errorInfo[0] ?? $exception->getPrevious()?->getCode() ?? $exception->getCode()),
            ['23000', '23505'],
            true,
        );
    }

    private function assertInvitationValid(FeedbackInvitation $invitation, FeedbackForm $form): void
    {
        if ($invitation->feedback_form_id !== $form->id) {
            throw new RuntimeException('This invitation does not belong to the selected form.');
        }

        if ($invitation->status === FeedbackInvitationStatus::Expired) {
            throw new RuntimeException('This invitation has expired.');
        }

        if ($invitation->status === FeedbackInvitationStatus::Cancelled) {
            throw new RuntimeException('This invitation has been cancelled.');
        }

        if ($invitation->status === FeedbackInvitationStatus::Submitted) {
            throw new RuntimeException('This invitation has already been used.');
        }

        if ($invitation->expires_at !== null && CarbonImmutable::now()->isAfter($invitation->expires_at)) {
            $invitation->forceFill(['status' => FeedbackInvitationStatus::Expired])->save();

            throw new RuntimeException('This invitation has expired.');
        }
    }

    private function assertSubmittedQuestionsBelongToForm(
        FeedbackForm $form,
        SubmitFeedbackResponseData $data,
    ): void {
        $questions = $form->questions->keyBy('id');
        $seenQuestionIds = [];
        $seenQuestionKeys = [];

        foreach ($data->answers as $answer) {
            $question = $questions->get($answer->questionId);

            if ($question === null || $question->key !== $answer->questionKey) {
                throw new RuntimeException('A submitted answer references a question outside the selected form.');
            }

            if (isset($seenQuestionIds[$answer->questionId]) || isset($seenQuestionKeys[$answer->questionKey])) {
                throw new RuntimeException('A question may only be answered once per response.');
            }

            $seenQuestionIds[$answer->questionId] = true;
            $seenQuestionKeys[$answer->questionKey] = true;
        }
    }
}

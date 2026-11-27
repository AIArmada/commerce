<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Data\SubmitFeedbackResponseData;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Events\FeedbackResponseSubmitted;
use AIArmada\Feedback\Exceptions\FeedbackInvitationExpiredException;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Invitations\Api\ManagesInvitationLifecycle;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Features\Testimonials\Api\ExtractsTestimonials;
use AIArmada\Feedback\Support\FeedbackModelReferenceGuard;
use AIArmada\Feedback\Support\FeedbackSubmissionGuard;
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
        private readonly ExtractsTestimonials $extractTestimonial,
        private readonly FeedbackSubmissionGuard $submissionGuard,
        private readonly FeedbackModelReferenceGuard $referenceGuard,
        private readonly ManagesInvitationLifecycle $invitationLifecycle,
    ) {}

    public function execute(SubmitFeedbackResponseData $data): FeedbackResponse
    {
        try {
            return retry(
                5,
                fn (): FeedbackResponse => $this->executeWithinTransaction($data),
                50,
                fn (Throwable $exception): bool => $exception instanceof DeadlockException,
            );
        } catch (FeedbackInvitationExpiredException $exception) {
            $this->invitationLifecycle->expire($exception->invitationId);

            throw $exception;
        }
    }

    private function executeWithinTransaction(SubmitFeedbackResponseData $data): FeedbackResponse
    {
        return DB::transaction(function () use ($data): FeedbackResponse {
            $guardedForm = FeedbackForm::ownerScopeConfig()->enabled
                ? OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $data->formId)
                : FeedbackForm::query()->whereKey($data->formId)->firstOrFail();
            $form = FeedbackForm::with('questions.options')
                ->lockForUpdate()
                ->whereKey($guardedForm->getKey())
                ->firstOrFail();

            $this->submissionGuard->assertFormAcceptingSubmissions(
                $form,
                $data->isAnonymous,
                $data->respondentType,
                $data->respondentId,
            );
            $this->assertSubmittedQuestionsBelongToForm($form, $data);

            $this->referenceGuard->resolveRespondent($data->respondentType, $data->respondentId);
            if ($data->invitationId !== null) {
                $this->invitationLifecycle->assertUsable($data->invitationId, $form->id, $data->respondentType, $data->respondentId);
            }

            $existingResponse = $this->findExistingSubmittedResponse($form, $data);

            if ($existingResponse !== null) {
                return $existingResponse;
            }

            try {
                return DB::transaction(function () use ($form, $data): FeedbackResponse {
                    $response = $this->startResponse->execute(
                        form: $form,
                        respondentType: $data->respondentType,
                        respondentId: $data->respondentId,
                        invitationId: $data->invitationId,
                        isAnonymous: $data->isAnonymous,
                        metadata: $data->metadata,
                    );

                    $submittedValues = [];
                    foreach ($data->answers as $answer) {
                        $submittedValues[$answer->questionKey] = $answer->value;
                    }

                    $visibleQuestions = $this->validateAnswers->execute($form, $submittedValues);

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

                        $response->answers()->create($answerData);
                    }

                    $response->forceFill([
                        'status' => FeedbackResponseStatus::Submitted,
                        'is_anonymous' => $data->isAnonymous,
                        'enforce_respondent_uniqueness' => $form->is_one_response_per_respondent,
                        'submitted_at' => CarbonImmutable::now(),
                        'ip_address' => $data->ipAddress !== null
                            ? mb_substr($data->ipAddress, 0, 255)
                            : null,
                        'user_agent' => $data->userAgent,
                        'metadata' => $data->metadata,
                    ])->save();

                    if ($data->invitationId !== null) {
                        $this->invitationLifecycle->submit($data->invitationId);
                    }

                    $response = $this->calculateResponseScore->execute($response);

                    if (config('feedback.features.testimonials', true)) {
                        $this->extractTestimonial->execute($response->id);
                    }

                    FeedbackResponseSubmitted::dispatch($response->id, $response->feedback_form_id, $response->owner_type, $response->owner_id);

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

    private function findExistingSubmittedResponse(
        FeedbackForm $form,
        SubmitFeedbackResponseData $data,
    ): ?FeedbackResponse {
        if (! $form->is_one_response_per_respondent
            || $data->respondentType === null
            || $data->respondentId === null) {
            return null;
        }

        // Submitted and reviewed responses both satisfy one-response-per-respondent.
        // Rejected and spam responses do not: the respondent may correct and resubmit.
        return FeedbackResponse::query()
            ->lockForUpdate()
            ->where('feedback_form_id', $form->id)
            ->where('respondent_type', $data->respondentType)
            ->where('respondent_id', $data->respondentId)
            ->whereIn('status', [FeedbackResponseStatus::Submitted, FeedbackResponseStatus::Reviewed])
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

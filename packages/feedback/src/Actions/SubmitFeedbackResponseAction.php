<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Data\SubmitFeedbackResponseData;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Events\FeedbackResponseSubmitted;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackInvitation;
use AIArmada\Feedback\Models\FeedbackResponse;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
        /** @var FeedbackForm $form */
        $form = FeedbackForm::with('questions.options')->findOrFail($data->formId);

        $this->assertFormAcceptingSubmissions($form, $data);

        $invitation = null;
        if ($data->invitationId !== null) {
            $invitation = FeedbackInvitation::findOrFail($data->invitationId);
            $this->assertInvitationValid($invitation);
        }

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
        });
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

        if ($form->is_one_response_per_respondent && $data->respondentType && $data->respondentId) {
            $existing = FeedbackResponse::where('feedback_form_id', $form->id)
                ->where('respondent_type', $data->respondentType)
                ->where('respondent_id', $data->respondentId)
                ->where('status', 'submitted')
                ->exists();

            if ($existing) {
                throw new RuntimeException('You have already submitted a response for this form.');
            }
        }
    }

    private function assertInvitationValid(FeedbackInvitation $invitation): void
    {
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
}

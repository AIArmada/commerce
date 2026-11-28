<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Events\FeedbackResponseStarted;
use AIArmada\Feedback\Exceptions\FeedbackInvitationExpiredException;
use AIArmada\Feedback\Features\Forms\Api\ReadsSubmissionForm;
use AIArmada\Feedback\Features\Invitations\Api\ManagesInvitationLifecycle;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Support\FeedbackModelReferenceGuard;
use AIArmada\Feedback\Support\FeedbackSubmissionGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\DeadlockException;
use RuntimeException;
use Throwable;

final class StartFeedbackResponseAction
{
    public function __construct(
        private readonly FeedbackModelReferenceGuard $referenceGuard,
        private readonly FeedbackSubmissionGuard $submissionGuard,
        private readonly ReadsSubmissionForm $forms,
        private readonly ManagesInvitationLifecycle $invitationLifecycle,
    ) {}

    public function execute(
        string $formId,
        ?string $respondentType = null,
        ?string $respondentId = null,
        ?string $invitationId = null,
        bool $isAnonymous = false,
        ?array $metadata = null,
    ): FeedbackResponse {
        try {
            return retry(
                5,
                fn (): FeedbackResponse => $this->executeWithinTransaction(
                    $formId,
                    $respondentType,
                    $respondentId,
                    $invitationId,
                    $isAnonymous,
                    $metadata,
                ),
                50,
                fn (Throwable $exception): bool => $exception instanceof DeadlockException,
            );
        } catch (FeedbackInvitationExpiredException $exception) {
            $this->invitationLifecycle->expire($exception->invitationId);

            throw $exception;
        }
    }

    private function executeWithinTransaction(
        string $formId,
        ?string $respondentType,
        ?string $respondentId,
        ?string $invitationId,
        bool $isAnonymous,
        ?array $metadata,
    ): FeedbackResponse {
        return (new FeedbackResponse)->getConnection()->transaction(function () use (
            $formId,
            $respondentType,
            $respondentId,
            $invitationId,
            $isAnonymous,
            $metadata,
        ): FeedbackResponse {
            $form = $this->forms->requireOwned($formId, includeQuestions: false);

            $this->submissionGuard->assertFormAcceptingSubmissions($form, $isAnonymous, $respondentType, $respondentId);
            $respondent = $this->referenceGuard->resolveRespondent($respondentType, $respondentId);
            $respondentType = $respondent?->getMorphClass();
            $respondentId = $respondent === null ? null : (string) $respondent->getKey();

            if ($invitationId !== null) {
                $this->invitationLifecycle->assertUsable($invitationId, $form->id, $respondentType, $respondentId);
            }

            if ($form->isOneResponsePerRespondent
                && $respondentType !== null
                && $respondentId !== null
                && FeedbackResponse::query()
                    ->where('feedback_form_id', $form->id)
                    ->where('respondent_type', $respondentType)
                    ->where('respondent_id', $respondentId)
                    ->whereIn('status', [FeedbackResponseStatus::Submitted, FeedbackResponseStatus::Reviewed])
                    ->exists()) {
                throw new RuntimeException('You have already submitted a response for this form.');
            }

            $draftQuery = FeedbackResponse::query()
                ->lockForUpdate()
                ->where('feedback_form_id', $form->id)
                ->where('status', FeedbackResponseStatus::Draft);

            if ($respondentType === null) {
                $draftQuery->whereNull('respondent_type');
            } else {
                $draftQuery->where('respondent_type', $respondentType);
            }

            if ($respondentId === null) {
                $draftQuery->whereNull('respondent_id');
            } else {
                $draftQuery->where('respondent_id', $respondentId);
            }

            $draftQuery->where('feedback_invitation_id', $invitationId);

            $existingDraft = $invitationId !== null || ($respondentType !== null && $respondentId !== null)
                ? $draftQuery->first()
                : null;

            if ($existingDraft !== null) {
                return $existingDraft;
            }

            $response = FeedbackResponse::create([
                'feedback_form_id' => $form->id,
                'feedback_invitation_id' => $invitationId,
                'subject_type' => $form->subjectType,
                'subject_id' => $form->subjectId,
                'respondent_type' => $respondentType,
                'respondent_id' => $respondentId,
                'status' => FeedbackResponseStatus::Draft,
                'enforce_respondent_uniqueness' => $form->isOneResponsePerRespondent,
                'is_anonymous' => $isAnonymous,
                'metadata' => $metadata ?? [],
                'started_at' => CarbonImmutable::now(),
            ]);

            if ($invitationId !== null) {
                $this->invitationLifecycle->start($invitationId);
            }

            FeedbackResponseStarted::dispatch($response->id, $response->feedback_form_id, $response->owner_type, $response->owner_id);

            return $response;
        }, 5);
    }
}

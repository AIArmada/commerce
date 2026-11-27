<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Events\FeedbackResponseStarted;
use AIArmada\Feedback\Exceptions\FeedbackInvitationExpiredException;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Invitations\Api\ManagesInvitationLifecycle;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Support\FeedbackModelReferenceGuard;
use AIArmada\Feedback\Support\FeedbackSubmissionGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class StartFeedbackResponseAction
{
    public function __construct(
        private readonly FeedbackModelReferenceGuard $referenceGuard,
        private readonly FeedbackSubmissionGuard $submissionGuard,
        private readonly ManagesInvitationLifecycle $invitationLifecycle,
    ) {}

    public function execute(
        FeedbackForm $form,
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
                    $form,
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
        FeedbackForm $form,
        ?string $respondentType,
        ?string $respondentId,
        ?string $invitationId,
        bool $isAnonymous,
        ?array $metadata,
    ): FeedbackResponse {
        return DB::transaction(function () use (
            $form,
            $respondentType,
            $respondentId,
            $invitationId,
            $isAnonymous,
            $metadata,
        ): FeedbackResponse {
            $guardedForm = FeedbackForm::ownerScopeConfig()->enabled
                ? OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id)
                : FeedbackForm::query()->whereKey($form->id)->firstOrFail();
            $form = FeedbackForm::query()
                ->lockForUpdate()
                ->whereKey($guardedForm->getKey())
                ->firstOrFail();

            $this->submissionGuard->assertFormAcceptingSubmissions($form, $isAnonymous, $respondentType, $respondentId);
            $this->referenceGuard->resolveRespondent($respondentType, $respondentId);

            if ($invitationId !== null) {
                $this->invitationLifecycle->assertUsable($invitationId, $form->id, $respondentType, $respondentId);
            }

            if ($form->is_one_response_per_respondent
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
                'subject_type' => $form->subject_type,
                'subject_id' => $form->subject_id,
                'respondent_type' => $respondentType,
                'respondent_id' => $respondentId,
                'status' => FeedbackResponseStatus::Draft,
                'enforce_respondent_uniqueness' => $form->is_one_response_per_respondent,
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

<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Events\FeedbackResponseStarted;
use AIArmada\Feedback\Exceptions\FeedbackInvitationExpiredException;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackInvitation;
use AIArmada\Feedback\Models\FeedbackResponse;
use AIArmada\Feedback\Support\FeedbackModelReferenceGuard;
use AIArmada\Feedback\Support\FeedbackSubmissionGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class StartFeedbackResponseAction
{
    public function __construct(
        private readonly FeedbackModelReferenceGuard $referenceGuard,
        private readonly FeedbackSubmissionGuard $submissionGuard,
    ) {}

    public function execute(
        FeedbackForm $form,
        ?string $respondentType = null,
        ?string $respondentId = null,
        ?FeedbackInvitation $invitation = null,
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
                    $invitation,
                    $isAnonymous,
                    $metadata,
                ),
                50,
                fn (Throwable $exception): bool => $exception instanceof DeadlockException,
            );
        } catch (FeedbackInvitationExpiredException $exception) {
            $this->markInvitationExpired($exception->invitationId);

            throw $exception;
        }
    }

    private function executeWithinTransaction(
        FeedbackForm $form,
        ?string $respondentType,
        ?string $respondentId,
        ?FeedbackInvitation $invitation,
        bool $isAnonymous,
        ?array $metadata,
    ): FeedbackResponse {
        return DB::transaction(function () use (
            $form,
            $respondentType,
            $respondentId,
            $invitation,
            $isAnonymous,
            $metadata,
        ): FeedbackResponse {
            $guardedForm = OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);
            $form = FeedbackForm::query()
                ->lockForUpdate()
                ->whereKey($guardedForm->getKey())
                ->firstOrFail();

            $this->submissionGuard->assertFormAcceptingSubmissions($form, $isAnonymous, $respondentType, $respondentId);
            $this->referenceGuard->resolveRespondent($respondentType, $respondentId);

            if ($invitation !== null) {
                $guardedInvitation = OwnerWriteGuard::findOrFailForOwner(FeedbackInvitation::class, $invitation->id);
                $invitation = FeedbackInvitation::query()
                    ->lockForUpdate()
                    ->whereKey($guardedInvitation->getKey())
                    ->firstOrFail();

                if ($invitation->feedback_form_id !== $form->id) {
                    throw new InvalidArgumentException('The feedback invitation does not belong to the selected form.');
                }

                $this->submissionGuard->assertInvitationValid($invitation, $form);
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

            $existingDraft = $draftQuery->first();

            if ($existingDraft !== null) {
                return $existingDraft;
            }

            $response = FeedbackResponse::create([
                'feedback_form_id' => $form->id,
                'feedback_invitation_id' => $invitation?->id,
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

            if ($invitation !== null) {
                $invitation->forceFill([
                    'status' => FeedbackInvitationStatus::Started,
                    'started_at' => CarbonImmutable::now(),
                ])->save();
            }

            FeedbackResponseStarted::dispatch($response);

            return $response;
        }, 5);
    }

    private function markInvitationExpired(string $invitationId): void
    {
        DB::transaction(function () use ($invitationId): void {
            $invitation = FeedbackInvitation::query()
                ->lockForUpdate()
                ->whereKey($invitationId)
                ->first();

            if ($invitation === null) {
                return;
            }

            $guarded = OwnerWriteGuard::findOrFailForOwner(FeedbackInvitation::class, $invitation->id);

            $guarded->forceFill(['status' => FeedbackInvitationStatus::Expired])->save();
        });
    }
}

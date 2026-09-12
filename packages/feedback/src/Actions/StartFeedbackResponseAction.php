<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Events\FeedbackResponseStarted;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackInvitation;
use AIArmada\Feedback\Models\FeedbackResponse;
use AIArmada\Feedback\Support\FeedbackModelReferenceGuard;
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
    ) {}

    public function execute(
        FeedbackForm $form,
        ?string $respondentType = null,
        ?string $respondentId = null,
        ?FeedbackInvitation $invitation = null,
        bool $isAnonymous = false,
    ): FeedbackResponse {
        return retry(
            5,
            fn (): FeedbackResponse => $this->executeWithinTransaction(
                $form,
                $respondentType,
                $respondentId,
                $invitation,
                $isAnonymous,
            ),
            50,
            fn (Throwable $exception): bool => $exception instanceof DeadlockException,
        );
    }

    private function executeWithinTransaction(
        FeedbackForm $form,
        ?string $respondentType,
        ?string $respondentId,
        ?FeedbackInvitation $invitation,
        bool $isAnonymous,
    ): FeedbackResponse {
        return DB::transaction(function () use (
            $form,
            $respondentType,
            $respondentId,
            $invitation,
            $isAnonymous,
        ): FeedbackResponse {
            $guardedForm = OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);
            $form = FeedbackForm::query()
                ->lockForUpdate()
                ->whereKey($guardedForm->getKey())
                ->firstOrFail();

            $this->referenceGuard->resolve($respondentType, $respondentId);

            if ($invitation !== null) {
                $invitation = OwnerWriteGuard::findOrFailForOwner(FeedbackInvitation::class, $invitation->id);

                if ($invitation->feedback_form_id !== $form->id) {
                    throw new InvalidArgumentException('The feedback invitation does not belong to the selected form.');
                }
            }

            if ($form->is_one_response_per_respondent
                && $respondentType !== null
                && $respondentId !== null
                && FeedbackResponse::query()
                    ->where('feedback_form_id', $form->id)
                    ->where('respondent_type', $respondentType)
                    ->where('respondent_id', $respondentId)
                    ->where('status', FeedbackResponseStatus::Submitted)
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
}

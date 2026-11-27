<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Invitations\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Features\Invitations\Api\ManagesInvitationLifecycle;
use AIArmada\Feedback\Features\Invitations\Models\FeedbackInvitation;
use AIArmada\Feedback\Models\FeedbackForm;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class ManageInvitationLifecycleAction implements ManagesInvitationLifecycle
{
    public function __construct(private ExpireFeedbackInvitationAction $expireInvitation) {}

    public function start(string $invitationId): void
    {
        $this->transition($invitationId, FeedbackInvitationStatus::Started);
    }

    public function submit(string $invitationId): void
    {
        $this->transition($invitationId, FeedbackInvitationStatus::Submitted);
    }

    public function expire(string $invitationId): void
    {
        $this->expireInvitation->execute($invitationId);
    }

    public function deleteForForm(string $formId): void
    {
        if (FeedbackForm::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $formId);
        }

        FeedbackInvitation::query()->where('feedback_form_id', $formId)->delete();
    }

    private function transition(string $invitationId, FeedbackInvitationStatus $status): void
    {
        (new FeedbackInvitation)->getConnection()->transaction(function () use ($invitationId, $status): void {
            if (FeedbackInvitation::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackInvitation::class, $invitationId);
            }

            $invitation = FeedbackInvitation::query()->whereKey($invitationId)->lockForUpdate()->firstOrFail();

            if ($invitation->isExpired() || in_array($invitation->status, [
                FeedbackInvitationStatus::Cancelled,
                FeedbackInvitationStatus::Expired,
                FeedbackInvitationStatus::Submitted,
            ], true)) {
                throw new RuntimeException('This invitation cannot be used.');
            }

            if ($invitation->status === $status) {
                return;
            }

            $invitation->forceFill([
                'status' => $status,
                $status === FeedbackInvitationStatus::Started ? 'started_at' : 'submitted_at' => CarbonImmutable::now(),
            ])->save();
        });
    }
}

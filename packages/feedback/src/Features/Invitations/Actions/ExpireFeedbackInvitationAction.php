<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Invitations\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Features\Invitations\Models\FeedbackInvitation;
use Illuminate\Support\Facades\DB;

final readonly class ExpireFeedbackInvitationAction
{
    public function execute(string $invitationId): void
    {
        DB::transaction(function () use ($invitationId): void {
            $invitation = FeedbackInvitation::query()
                ->whereKey($invitationId)
                ->lockForUpdate()
                ->first();

            if (! $invitation instanceof FeedbackInvitation) {
                return;
            }

            if (FeedbackInvitation::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackInvitation::class, $invitationId);
            }

            if (! $invitation->isExpired() || in_array($invitation->status, [
                FeedbackInvitationStatus::Submitted,
                FeedbackInvitationStatus::Cancelled,
                FeedbackInvitationStatus::Expired,
            ], true)) {
                return;
            }

            $invitation->forceFill(['status' => FeedbackInvitationStatus::Expired])->save();
        });
    }
}

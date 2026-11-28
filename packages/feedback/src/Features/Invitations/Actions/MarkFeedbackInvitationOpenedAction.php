<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Invitations\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Events\FeedbackInvitationOpened;
use AIArmada\Feedback\Features\Invitations\Models\FeedbackInvitation;
use Carbon\CarbonImmutable;

final readonly class MarkFeedbackInvitationOpenedAction
{
    public function execute(FeedbackInvitation $invitation): FeedbackInvitation
    {
        return (new FeedbackInvitation)->getConnection()->transaction(function () use ($invitation): FeedbackInvitation {
            if (FeedbackInvitation::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackInvitation::class, $invitation->id);
            }

            $invitation = FeedbackInvitation::query()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invitation->isExpired() || ! in_array($invitation->status, [
                FeedbackInvitationStatus::Sent,
                FeedbackInvitationStatus::Pending,
            ], true)) {
                return $invitation;
            }

            $invitation->forceFill([
                'status' => FeedbackInvitationStatus::Opened,
                'opened_at' => CarbonImmutable::now(),
            ])->save();

            FeedbackInvitationOpened::dispatch($invitation->id, $invitation->owner_type, $invitation->owner_id);

            return $invitation;
        });
    }
}

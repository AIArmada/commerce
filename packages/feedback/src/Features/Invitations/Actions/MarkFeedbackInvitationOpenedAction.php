<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Invitations\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Events\FeedbackInvitationOpened;
use AIArmada\Feedback\Features\Invitations\Models\FeedbackInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class MarkFeedbackInvitationOpenedAction
{
    public function execute(FeedbackInvitation $invitation): FeedbackInvitation
    {
        $guarded = FeedbackInvitation::ownerScopeConfig()->enabled
            ? OwnerWriteGuard::findOrFailForOwner(FeedbackInvitation::class, $invitation->id)
            : FeedbackInvitation::query()->whereKey($invitation->id)->firstOrFail();

        return DB::transaction(function () use ($guarded): FeedbackInvitation {
            $invitation = FeedbackInvitation::query()
                ->whereKey($guarded->id)
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

            FeedbackInvitationOpened::dispatch($invitation);

            return $invitation;
        });
    }
}

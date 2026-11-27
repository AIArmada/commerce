<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Events\FeedbackInvitationOpened;
use AIArmada\Feedback\Models\FeedbackInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class MarkFeedbackInvitationOpenedAction
{
    public function execute(FeedbackInvitation $invitation): FeedbackInvitation
    {
        $guarded = OwnerWriteGuard::findOrFailForOwner(FeedbackInvitation::class, $invitation->id);

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

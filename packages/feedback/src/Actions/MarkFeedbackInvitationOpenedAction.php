<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Events\FeedbackInvitationOpened;
use AIArmada\Feedback\Models\FeedbackInvitation;
use Carbon\CarbonImmutable;

final class MarkFeedbackInvitationOpenedAction
{
    public function execute(FeedbackInvitation $invitation): FeedbackInvitation
    {
        if ($invitation->status !== FeedbackInvitationStatus::Sent && $invitation->status !== FeedbackInvitationStatus::Pending) {
            return $invitation;
        }

        $invitation->forceFill([
            'status' => FeedbackInvitationStatus::Opened,
            'opened_at' => CarbonImmutable::now(),
        ])->save();

        FeedbackInvitationOpened::dispatch($invitation);

        return $invitation;
    }
}

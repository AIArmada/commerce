<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Models\FeedbackInvitation;
use Carbon\CarbonImmutable;
use RuntimeException;

final class ResolveFeedbackInvitationTokenAction
{
    public function execute(string $rawToken): FeedbackInvitation
    {
        $tokenHash = hash('sha256', $rawToken);

        $invitation = FeedbackInvitation::query()
            ->withoutOwnerScope()
            ->where('token_hash', $tokenHash)
            ->first();

        if ($invitation === null) {
            throw new RuntimeException('Invalid invitation token.');
        }

        if ($invitation->status === FeedbackInvitationStatus::Expired) {
            throw new RuntimeException('This invitation has expired.');
        }

        if ($invitation->status === FeedbackInvitationStatus::Cancelled) {
            throw new RuntimeException('This invitation has been cancelled.');
        }

        if ($invitation->status === FeedbackInvitationStatus::Submitted) {
            throw new RuntimeException('This invitation has already been used.');
        }

        if ($invitation->expires_at !== null && CarbonImmutable::now()->isAfter($invitation->expires_at)) {
            $owner = OwnerTupleParser::fromTypeAndId(
                $invitation->owner_type,
                $invitation->owner_id,
            )->toOwnerModel();

            OwnerContext::withOwner($owner, function () use ($invitation): void {
                $invitation->forceFill([
                    'status' => FeedbackInvitationStatus::Expired,
                ])->save();
            });

            throw new RuntimeException('This invitation has expired.');
        }

        return $invitation;
    }
}

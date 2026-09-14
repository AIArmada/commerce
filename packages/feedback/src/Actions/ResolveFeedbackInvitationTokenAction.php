<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Models\FeedbackInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

final class ResolveFeedbackInvitationTokenAction
{
    public function execute(string $rawToken): FeedbackInvitation
    {
        $tokenHash = hash('sha256', $rawToken);
        $maxAttempts = (int) config('feedback.security.invitation_rate_limit.max_attempts', 60);
        $decaySeconds = (int) config('feedback.security.invitation_rate_limit.decay_seconds', 60);

        // The IP bucket throttles token enumeration; the token bucket throttles
        // repeated re-resolution of one invitation.
        $ipKey = 'feedback-invitation-token-ip:' . (request()->ip() ?? 'console');

        if (! RateLimiter::attempt($ipKey, $maxAttempts, static fn (): bool => true, $decaySeconds)) {
            throw new RuntimeException('Too many invitation token attempts.');
        }

        $allowed = RateLimiter::attempt(
            "feedback-invitation-token:{$tokenHash}",
            $maxAttempts,
            static fn (): bool => true,
            $decaySeconds,
        );

        if (! $allowed) {
            throw new RuntimeException('Too many invitation token attempts.');
        }

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

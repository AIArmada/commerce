<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Services;

use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\Reactable;
use AIArmada\Engagement\Contracts\Respondable;

final class DefaultEngagementPolicyResolver implements EngagementPolicyResolver
{
    public function canFollow(mixed $actor, mixed $subject): bool
    {
        return true;
    }

    public function canBookmark(mixed $actor, mixed $subject): bool
    {
        return true;
    }

    public function canRespond(mixed $actor, mixed $subject, string $responseType): bool
    {
        if ($subject instanceof Respondable) {
            return in_array($responseType, $subject->allowedResponseTypes(), true);
        }

        return true;
    }

    public function canReact(mixed $actor, mixed $subject, string $reactionType): bool
    {
        if ($subject instanceof Reactable) {
            return in_array($reactionType, $subject->allowedReactionTypes(), true);
        }

        return true;
    }

    public function canSubscribe(mixed $actor, mixed $subject = null, string $subscriptionType = 'updates'): bool
    {
        return true;
    }

    public function canSetReminder(mixed $actor, mixed $subject, string $reminderType): bool
    {
        return true;
    }
}

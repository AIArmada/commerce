<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

interface EngagementPolicyResolver
{
    public function canFollow(mixed $actor, mixed $subject): bool;

    public function canBookmark(mixed $actor, mixed $subject): bool;

    public function canRespond(mixed $actor, mixed $subject, string $responseType): bool;

    public function canReact(mixed $actor, mixed $subject, string $reactionType): bool;

    public function canSubscribe(mixed $actor, mixed $subject = null, string $subscriptionType = 'updates'): bool;

    public function canSetReminder(mixed $actor, mixed $subject, string $reminderType): bool;
}

<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

use AIArmada\Engagement\Models\Reaction;
use AIArmada\Engagement\Models\Response;

interface EngagementStateResolver
{
    public function isFollowing(mixed $actor, mixed $subject): bool;

    public function isBookmarked(mixed $actor, mixed $subject): bool;

    public function responseFor(mixed $actor, mixed $subject): ?Response;

    public function reactionFor(mixed $actor, mixed $subject, ?string $reactionType = null): ?Reaction;

    /** @return iterable */
    public function subscriptionsFor(mixed $subscriber, mixed $subject = null): iterable;

    /** @return iterable */
    public function remindersFor(mixed $recipient, mixed $subject): iterable;
}

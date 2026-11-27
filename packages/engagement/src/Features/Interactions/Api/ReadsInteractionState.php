<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Api;

interface ReadsInteractionState
{
    public function isFollowing(mixed $actor, mixed $subject): bool;

    public function isBookmarked(mixed $actor, mixed $subject): bool;

    public function responseFor(mixed $actor, mixed $subject): ?array;

    public function reactionFor(mixed $actor, mixed $subject, ?string $reactionType = null): ?array;
}

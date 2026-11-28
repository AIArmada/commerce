<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Api;

interface ReadsInteractionState
{
    public function isFollowing(mixed $actor, mixed $subject): bool;

    public function isBookmarked(mixed $actor, mixed $subject): bool;

    /** @return array<string, mixed>|null */
    public function responseFor(mixed $actor, mixed $subject): ?array;

    /** @return array<string, mixed>|null */
    public function reactionFor(mixed $actor, mixed $subject, ?string $reactionType = null): ?array;

    /** @return array<string, mixed>|null */
    public function shareFor(mixed $actor, mixed $subject): ?array;
}

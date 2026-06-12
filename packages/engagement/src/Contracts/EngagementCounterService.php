<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

interface EngagementCounterService
{
    public function countFollowers(mixed $subject): int;

    public function countBookmarks(mixed $subject): int;

    public function countResponses(mixed $subject, ?string $responseType = null): int;

    public function countReactions(mixed $subject, ?string $reactionType = null): int;

    public function recalculate(mixed $subject): void;
}

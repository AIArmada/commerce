<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

use AIArmada\Engagement\Events\BookmarkArchived;
use AIArmada\Engagement\Events\BookmarkCreated;
use AIArmada\Engagement\Events\BookmarkRemoved;
use AIArmada\Engagement\Events\ResponseCancelled;
use AIArmada\Engagement\Events\ResponseChanged;
use AIArmada\Engagement\Events\ResponseCreated;

interface EngagementCounterService
{
    public function onBookmarkCreated(BookmarkCreated $event): void;

    public function onBookmarkRemoved(BookmarkRemoved $event): void;

    public function onBookmarkArchived(BookmarkArchived $event): void;

    public function onResponseCreated(ResponseCreated $event): void;

    public function onResponseChanged(ResponseChanged $event): void;

    public function onResponseCancelled(ResponseCancelled $event): void;

    public function value(mixed $subject, string $counterType, string $counterKey = ''): int;

    public function countFollowers(mixed $subject): int;

    public function countBookmarks(mixed $subject): int;

    public function countResponses(mixed $subject, ?string $responseType = null): int;

    public function countReactions(mixed $subject, ?string $reactionType = null): int;

    public function recalculate(mixed $subject): void;

    public function recalculateBookmarks(mixed $subject): void;

    public function recalculateResponses(mixed $subject, ?string $responseType = null): void;
}

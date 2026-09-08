<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

use AIArmada\Engagement\Events\BookmarkArchived;
use AIArmada\Engagement\Events\BookmarkCreated;
use AIArmada\Engagement\Events\BookmarkRemoved;
use AIArmada\Engagement\Events\FollowCreated;
use AIArmada\Engagement\Events\FollowMuted;
use AIArmada\Engagement\Events\FollowRemoved;
use AIArmada\Engagement\Events\FollowUnmuted;
use AIArmada\Engagement\Events\ReactionCreated;
use AIArmada\Engagement\Events\ReactionRemoved;
use AIArmada\Engagement\Events\ResponseCancelled;
use AIArmada\Engagement\Events\ResponseChanged;
use AIArmada\Engagement\Events\ResponseCreated;

interface EngagementCounterService
{
    public function onFollowCreated(FollowCreated $event): void;

    public function onFollowRemoved(FollowRemoved $event): void;

    public function onFollowMuted(FollowMuted $event): void;

    public function onFollowUnmuted(FollowUnmuted $event): void;

    public function onReactionCreated(ReactionCreated $event): void;

    public function onReactionRemoved(ReactionRemoved $event): void;

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

    public function recalculateFollowers(mixed $subject): void;

    public function recalculateReactions(mixed $subject, ?string $reactionType = null): void;

    public function recalculateBookmarks(mixed $subject): void;

    public function recalculateResponses(mixed $subject, ?string $responseType = null): void;
}

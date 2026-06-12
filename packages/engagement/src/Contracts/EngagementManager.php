<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

use AIArmada\Engagement\Models\Bookmark;
use AIArmada\Engagement\Models\Follow;
use AIArmada\Engagement\Models\Reaction;
use AIArmada\Engagement\Models\Reminder;
use AIArmada\Engagement\Models\Response;
use AIArmada\Engagement\Models\Share;

interface EngagementManager
{
    public function follow(mixed $actor, mixed $subject, array $options = []): Follow;

    public function unfollow(mixed $actor, mixed $subject, array $options = []): void;

    public function muteFollow(mixed $actor, mixed $subject, array $options = []): Follow;

    public function unmuteFollow(mixed $actor, mixed $subject, array $options = []): Follow;

    public function bookmark(mixed $actor, mixed $subject, array $options = []): Bookmark;

    public function removeBookmark(mixed $actor, mixed $subject, array $options = []): void;

    public function archiveBookmark(mixed $actor, mixed $subject, array $options = []): void;

    public function respond(mixed $actor, mixed $subject, string $responseType, array $options = []): Response;

    public function cancelResponse(mixed $actor, mixed $subject, array $options = []): void;

    public function react(mixed $actor, mixed $subject, string $reactionType, array $options = []): Reaction;

    public function removeReaction(mixed $actor, mixed $subject, ?string $reactionType = null, array $options = []): void;

    public function remind(mixed $actor, mixed $subject, array $options = []): Reminder;

    public function share(mixed $actor, mixed $subject, array $options = []): Share;

    public function addBookmarkToCollection(mixed $actor, mixed $bookmark, mixed $collection, array $options = []): void;

    public function removeBookmarkFromCollection(mixed $actor, mixed $bookmark, mixed $collection, array $options = []): void;
}

<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

use AIArmada\Engagement\Models\Bookmark;
use AIArmada\Engagement\Models\BookmarkCollection;
use AIArmada\Engagement\Models\Follow;
use AIArmada\Engagement\Models\Reaction;
use AIArmada\Engagement\Models\Reminder;
use AIArmada\Engagement\Models\Response;
use AIArmada\Engagement\Models\Share;

interface EngagementManager
{
    public function follow(CanInteract $actor, Followable $subject, array $options = []): Follow;

    public function unfollow(CanInteract $actor, Followable $subject, array $options = []): void;

    public function muteFollow(CanInteract $actor, Followable $subject, array $options = []): Follow;

    public function unmuteFollow(CanInteract $actor, Followable $subject, array $options = []): Follow;

    public function bookmark(CanInteract $actor, Bookmarkable $subject, array $options = []): Bookmark;

    public function removeBookmark(CanInteract $actor, Bookmarkable $subject, array $options = []): void;

    public function archiveBookmark(CanInteract $actor, Bookmarkable $subject, array $options = []): void;

    public function respond(CanInteract $actor, Respondable $subject, string $responseType, array $options = []): Response;

    public function cancelResponse(CanInteract $actor, Respondable $subject, array $options = []): void;

    public function react(CanInteract $actor, Reactable $subject, string $reactionType, array $options = []): Reaction;

    public function removeReaction(CanInteract $actor, Reactable $subject, ?string $reactionType = null, array $options = []): void;

    public function remind(CanInteract $actor, Remindable $subject, array $options = []): Reminder;

    public function share(CanInteract $actor, Shareable $subject, array $options = []): Share;

    public function addBookmarkToCollection(CanInteract $actor, Bookmark $bookmark, BookmarkCollection $collection, array $options = []): void;

    public function removeBookmarkFromCollection(CanInteract $actor, Bookmark $bookmark, BookmarkCollection $collection, array $options = []): void;
}

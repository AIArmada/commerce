<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Actions;

use AIArmada\Engagement\Contracts\Bookmarkable;
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\Followable;
use AIArmada\Engagement\Contracts\Reactable;
use AIArmada\Engagement\Contracts\Respondable;
use AIArmada\Engagement\Contracts\Shareable;
use AIArmada\Engagement\Features\Interactions\Models\Bookmark;
use AIArmada\Engagement\Features\Interactions\Models\BookmarkCollection;
use AIArmada\Engagement\Features\Interactions\Models\Follow;
use AIArmada\Engagement\Features\Interactions\Models\Reaction;
use AIArmada\Engagement\Features\Interactions\Models\Response;
use AIArmada\Engagement\Features\Interactions\Models\Share;

final readonly class ManageInteractionsAction
{
    public function __construct(
        private ManageFollowsAction $manageFollows,
        private ManageBookmarksAction $manageBookmarks,
        private ManageResponsesAction $manageResponses,
        private ManageReactionsAction $manageReactions,
        private CreateShareAction $createShare,
        private ManageBookmarkCollectionItemsAction $manageBookmarkCollectionItems,
    ) {}

    public function follow(CanInteract $actor, Followable $subject, array $options = []): Follow
    {
        return $this->manageFollows->follow($actor, $subject, $options);
    }

    public function unfollow(CanInteract $actor, Followable $subject, array $options = []): void
    {
        $this->manageFollows->unfollow($actor, $subject, $options);
    }

    public function muteFollow(CanInteract $actor, Followable $subject, array $options = []): Follow
    {
        return $this->manageFollows->muteFollow($actor, $subject, $options);
    }

    public function unmuteFollow(CanInteract $actor, Followable $subject, array $options = []): Follow
    {
        return $this->manageFollows->unmuteFollow($actor, $subject, $options);
    }

    public function bookmark(CanInteract $actor, Bookmarkable $subject, array $options = []): Bookmark
    {
        return $this->manageBookmarks->bookmark($actor, $subject, $options);
    }

    public function removeBookmark(CanInteract $actor, Bookmarkable $subject, array $options = []): void
    {
        $this->manageBookmarks->removeBookmark($actor, $subject, $options);
    }

    public function archiveBookmark(CanInteract $actor, Bookmarkable $subject, array $options = []): void
    {
        $this->manageBookmarks->archiveBookmark($actor, $subject, $options);
    }

    public function respond(CanInteract $actor, Respondable $subject, string $responseType, array $options = []): Response
    {
        return $this->manageResponses->respond($actor, $subject, $responseType, $options);
    }

    public function cancelResponse(CanInteract $actor, Respondable $subject, array $options = []): void
    {
        $this->manageResponses->cancelResponse($actor, $subject, $options);
    }

    public function react(CanInteract $actor, Reactable $subject, string $reactionType, array $options = []): Reaction
    {
        return $this->manageReactions->react($actor, $subject, $reactionType, $options);
    }

    public function removeReaction(CanInteract $actor, Reactable $subject, ?string $reactionType = null, array $options = []): void
    {
        $this->manageReactions->removeReaction($actor, $subject, $reactionType, $options);
    }

    public function share(CanInteract $actor, Shareable $subject, array $options = []): Share
    {
        return $this->createShare->share($actor, $subject, $options);
    }

    public function addBookmarkToCollection(CanInteract $actor, Bookmark $bookmark, BookmarkCollection $collection, array $options = []): void
    {
        $this->manageBookmarkCollectionItems->addBookmarkToCollection($actor, $bookmark, $collection, $options);
    }

    public function removeBookmarkFromCollection(CanInteract $actor, Bookmark $bookmark, BookmarkCollection $collection, array $options = []): void
    {
        $this->manageBookmarkCollectionItems->removeBookmarkFromCollection($actor, $bookmark, $collection, $options);
    }
}

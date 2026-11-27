<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Events\BookmarkAddedToCollection;
use AIArmada\Engagement\Events\BookmarkRemovedFromCollection;
use AIArmada\Engagement\Features\Interactions\Models\Bookmark;
use AIArmada\Engagement\Features\Interactions\Models\BookmarkCollection;
use AIArmada\Engagement\Features\Interactions\Models\BookmarkCollectionItem;
use AIArmada\Engagement\Support\EngagementModelGuard;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ManageBookmarkCollectionItemsAction
{
    public function addBookmarkToCollection(CanInteract $actor, Bookmark $bookmark, BookmarkCollection $collection, array $options = []): void
    {
        EngagementModelGuard::assertContract($actor, CanInteract::class, 'actor');
        $identity = EngagementModelGuard::identity($actor, 'actor');

        $notes = EngagementModelGuard::boundedString($options['notes'] ?? null, 'notes', EngagementModelGuard::TEXT_MAX_LENGTH);
        $bookmarkId = $bookmark->getKey();
        $collectionId = $collection->getKey();

        (new BookmarkCollectionItem)->getConnection()->transaction(function () use ($bookmarkId, $collectionId, $notes, $identity): void {
            $collection = $this->lockCollection($collectionId);
            $bookmark = $this->lockBookmark($bookmarkId, $identity);

            $item = BookmarkCollectionItem::query()->forOwner()
                ->where('bookmark_collection_id', $collection->getKey())
                ->where('bookmark_id', $bookmark->getKey())
                ->lockForUpdate()
                ->first();

            if ($item instanceof BookmarkCollectionItem) {
                if ($item->removed_at !== null) {
                    $item->update([
                        'removed_at' => null,
                        'added_at' => CarbonImmutable::now(),
                        'notes' => $notes ?? $item->notes,
                    ]);
                    event(new BookmarkAddedToCollection(
                        $bookmark->id,
                        $collection->id,
                        $collection->owner_type,
                        $collection->owner_id,
                    ));
                }

                return;
            }

            BookmarkCollectionItem::query()->create([
                'bookmark_collection_id' => $collection->getKey(),
                'bookmark_id' => $bookmark->getKey(),
                'added_at' => CarbonImmutable::now(),
                'notes' => $notes,
            ]);

            event(new BookmarkAddedToCollection(
                $bookmark->id,
                $collection->id,
                $collection->owner_type,
                $collection->owner_id,
            ));
        });
    }

    public function removeBookmarkFromCollection(CanInteract $actor, Bookmark $bookmark, BookmarkCollection $collection, array $options = []): void
    {
        EngagementModelGuard::assertContract($actor, CanInteract::class, 'actor');
        $identity = EngagementModelGuard::identity($actor, 'actor');

        (new BookmarkCollectionItem)->getConnection()->transaction(function () use ($bookmark, $collection, $identity): void {
            $collection = $this->lockCollection($collection->getKey());
            $bookmark = $this->lockBookmark($bookmark->getKey(), $identity);
            $item = BookmarkCollectionItem::query()->forOwner()
                ->where('bookmark_collection_id', $collection->getKey())
                ->where('bookmark_id', $bookmark->getKey())
                ->whereNull('removed_at')
                ->lockForUpdate()
                ->first();

            if ($item) {
                $item->update(['removed_at' => CarbonImmutable::now()]);
                event(new BookmarkRemovedFromCollection(
                    $bookmark->id,
                    $collection->id,
                    $collection->owner_type,
                    $collection->owner_id,
                ));
            }
        });
    }

    /** @param array{type: string, id: string} $identity */
    private function assertBookmarker(Bookmark $bookmark, array $identity): void
    {
        if ($bookmark->bookmarker_type !== $identity['type'] || (string) $bookmark->bookmarker_id !== $identity['id']) {
            throw new AuthorizationException('This bookmark belongs to a different actor.');
        }
    }

    private function lockCollection(string $id): BookmarkCollection
    {
        if (BookmarkCollection::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(BookmarkCollection::class, $id);
        }

        return BookmarkCollection::query()->forOwner()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** @param array{type: string, id: string} $identity */
    private function lockBookmark(string $id, array $identity): Bookmark
    {
        if (Bookmark::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Bookmark::class, $id);
        }

        $bookmark = Bookmark::query()->forOwner()->whereKey($id)->lockForUpdate()->firstOrFail();
        $this->assertBookmarker($bookmark, $identity);

        return $bookmark;
    }
}

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
use Illuminate\Database\QueryException;

final readonly class ManageBookmarkCollectionItemsAction
{
    public function addBookmarkToCollection(CanInteract $actor, Bookmark $bookmark, BookmarkCollection $collection, array $options = []): void
    {
        EngagementModelGuard::assertContract($actor, CanInteract::class, 'actor');

        $notes = EngagementModelGuard::boundedString($options['notes'] ?? null, 'notes', EngagementModelGuard::TEXT_MAX_LENGTH);
        $bookmarkId = $bookmark->getKey();
        $collectionId = $collection->getKey();

        try {
            (new BookmarkCollectionItem)->getConnection()->transaction(function () use ($bookmarkId, $collectionId, $notes): void {
                $bookmark = Bookmark::ownerScopeConfig()->enabled
                    ? OwnerWriteGuard::findOrFailForOwner(Bookmark::class, $bookmarkId)
                    : Bookmark::query()->whereKey($bookmarkId)->firstOrFail();
                $collection = BookmarkCollection::ownerScopeConfig()->enabled
                    ? OwnerWriteGuard::findOrFailForOwner(BookmarkCollection::class, $collectionId)
                    : BookmarkCollection::query()->whereKey($collectionId)->firstOrFail();

                $item = BookmarkCollectionItem::query()
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
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $item = BookmarkCollectionItem::query()
                ->where('bookmark_collection_id', $collectionId)
                ->where('bookmark_id', $bookmarkId)
                ->first();

            if (! $item instanceof BookmarkCollectionItem) {
                throw $exception;
            }
        }
    }

    public function removeBookmarkFromCollection(CanInteract $actor, Bookmark $bookmark, BookmarkCollection $collection, array $options = []): void
    {
        EngagementModelGuard::assertContract($actor, CanInteract::class, 'actor');

        (new BookmarkCollectionItem)->getConnection()->transaction(function () use ($bookmark, $collection): void {
            $bookmark = Bookmark::ownerScopeConfig()->enabled
                    ? OwnerWriteGuard::findOrFailForOwner(Bookmark::class, $bookmark->getKey())
                    : Bookmark::query()->whereKey($bookmark->getKey())->firstOrFail();
            $collection = BookmarkCollection::ownerScopeConfig()->enabled
                    ? OwnerWriteGuard::findOrFailForOwner(BookmarkCollection::class, $collection->getKey())
                    : BookmarkCollection::query()->whereKey($collection->getKey())->firstOrFail();
            $item = BookmarkCollectionItem::query()
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

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}

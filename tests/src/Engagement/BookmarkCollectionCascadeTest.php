<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Engagement\Models\Bookmark;
use AIArmada\Engagement\Models\BookmarkCollection;
use AIArmada\Engagement\Models\BookmarkCollectionItem;

beforeEach(function (): void {
    $this->owner = User::query()->create([
        'name' => 'Cascade Owner',
        'email' => 'cascade-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
});

it('deletes bookmark collection items when a collection is deleted', function (): void {
    $bookmark = Bookmark::query()->create([
        'bookmarker_type' => $this->owner->getMorphClass(),
        'bookmarker_id' => $this->owner->getKey(),
        'bookmarkable_type' => $this->owner->getMorphClass(),
        'bookmarkable_id' => $this->owner->getKey(),
        'status' => Bookmark::STATUS_ACTIVE,
    ]);
    $collection = BookmarkCollection::query()->create([
        'name' => 'Cascade Test',
        'visibility' => BookmarkCollection::VISIBILITY_PRIVATE,
        'status' => BookmarkCollection::STATUS_ACTIVE,
    ]);
    BookmarkCollectionItem::query()->create([
        'bookmark_collection_id' => $collection->id,
        'bookmark_id' => $bookmark->id,
    ]);

    expect(BookmarkCollectionItem::query()->count())->toBe(1);

    $collection->delete();

    expect(BookmarkCollection::query()->count())->toBe(0)
        ->and(BookmarkCollectionItem::query()->count())->toBe(0);
});

it('does not delete other collections items when one collection is deleted', function (): void {
    $bookmark = Bookmark::query()->create([
        'bookmarker_type' => $this->owner->getMorphClass(),
        'bookmarker_id' => $this->owner->getKey(),
        'bookmarkable_type' => $this->owner->getMorphClass(),
        'bookmarkable_id' => $this->owner->getKey(),
        'status' => Bookmark::STATUS_ACTIVE,
    ]);
    $collectionA = BookmarkCollection::query()->create([
        'name' => 'A',
        'visibility' => BookmarkCollection::VISIBILITY_PRIVATE,
        'status' => BookmarkCollection::STATUS_ACTIVE,
    ]);
    $collectionB = BookmarkCollection::query()->create([
        'name' => 'B',
        'visibility' => BookmarkCollection::VISIBILITY_PRIVATE,
        'status' => BookmarkCollection::STATUS_ACTIVE,
    ]);
    BookmarkCollectionItem::query()->create([
        'bookmark_collection_id' => $collectionA->id,
        'bookmark_id' => $bookmark->id,
    ]);
    BookmarkCollectionItem::query()->create([
        'bookmark_collection_id' => $collectionB->id,
        'bookmark_id' => $bookmark->id,
    ]);

    $collectionA->delete();

    expect(BookmarkCollectionItem::query()->count())->toBe(1);
});

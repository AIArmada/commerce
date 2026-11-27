<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Engagement\Features\Interactions\Models\BookmarkCollection;
use Illuminate\Support\Arr;

final readonly class UpdateBookmarkCollectionAction
{
    /** @param array<string, mixed> $data */
    public function execute(BookmarkCollection $collection, array $data): BookmarkCollection
    {
        $guarded = BookmarkCollection::ownerScopeConfig()->enabled
            ? OwnerWriteGuard::findOrFailForOwner(BookmarkCollection::class, $collection->id)
            : BookmarkCollection::query()->whereKey($collection->id)->firstOrFail();

        return $collection->getConnection()->transaction(function () use ($guarded, $data): BookmarkCollection {
            $collection = BookmarkCollection::query()->whereKey($guarded->id)->lockForUpdate()->firstOrFail();
            $collection->fill(Arr::only($data, ['name', 'description', 'visibility', 'status', 'sort_order']))->save();

            return $collection;
        });
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Engagement\Models\BookmarkCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class UpdateBookmarkCollectionAction
{
    /** @param array<string, mixed> $data */
    public function execute(BookmarkCollection $collection, array $data): BookmarkCollection
    {
        $guarded = OwnerWriteGuard::findOrFailForOwner(BookmarkCollection::class, $collection->id);

        return DB::transaction(function () use ($guarded, $data): BookmarkCollection {
            $collection = BookmarkCollection::query()->whereKey($guarded->id)->lockForUpdate()->firstOrFail();
            $collection->fill(Arr::only($data, ['name', 'description', 'visibility', 'status', 'sort_order']))->save();

            return $collection;
        });
    }
}

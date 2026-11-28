<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Engagement\Features\Interactions\Models\BookmarkCollection;

final readonly class DeleteBookmarkCollectionItemsAction
{
    public function execute(BookmarkCollection $collection): void
    {
        if (BookmarkCollection::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(BookmarkCollection::class, $collection->getKey());
        }

        $collection->getConnection()->transaction(function () use ($collection): void {
            $collection->items()->chunkById(200, function ($items): void {
                foreach ($items as $item) {
                    $item->delete();
                }
            });
        });
    }
}

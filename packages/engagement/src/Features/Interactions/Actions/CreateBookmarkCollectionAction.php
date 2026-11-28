<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Actions;

use AIArmada\Engagement\Features\Interactions\Models\BookmarkCollection;
use Illuminate\Support\Arr;

final readonly class CreateBookmarkCollectionAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): BookmarkCollection
    {
        return (new BookmarkCollection)->getConnection()->transaction(fn (): BookmarkCollection => BookmarkCollection::query()->create(
            Arr::only($data, ['name', 'description', 'visibility', 'status', 'sort_order']),
        ));
    }
}

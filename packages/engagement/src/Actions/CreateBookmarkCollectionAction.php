<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Actions;

use AIArmada\Engagement\Models\BookmarkCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class CreateBookmarkCollectionAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): BookmarkCollection
    {
        return DB::transaction(fn (): BookmarkCollection => BookmarkCollection::query()->create(
            Arr::only($data, ['name', 'description', 'visibility', 'status', 'sort_order']),
        ));
    }
}

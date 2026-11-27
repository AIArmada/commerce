<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\Pricing\Models\PriceList;
use Illuminate\Support\Facades\DB;

final readonly class EnsurePriceListBySlug
{
    /** @param array<string, mixed> $attributes */
    public function execute(string $slug, array $attributes): PriceList
    {
        return DB::transaction(function () use ($slug, $attributes): PriceList {
            $list = PriceList::query()->createOrFirst(
                ['slug' => $slug],
                ['name' => $attributes['name']],
            );

            $list = PriceList::query()->whereKey($list->getKey())->lockForUpdate()->firstOrFail();
            $list->fill($attributes);

            if ($list->isDirty()) {
                $list->save();
            }

            return $list;
        });
    }
}

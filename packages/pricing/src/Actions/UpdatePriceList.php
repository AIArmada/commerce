<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\Pricing\Models\PriceList;
use Illuminate\Support\Facades\DB;

final readonly class UpdatePriceList
{
    /** @param array<string, mixed> $attributes */
    public function execute(PriceList $priceList, array $attributes): PriceList
    {
        return DB::transaction(function () use ($priceList, $attributes): PriceList {
            $record = PriceList::query()->forOwner()->whereKey($priceList->getKey())->lockForUpdate()->firstOrFail();
            $record->update($attributes);

            return $record;
        });
    }
}

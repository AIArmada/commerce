<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\Pricing\Models\PriceList;
use Illuminate\Support\Facades\DB;

final readonly class CreatePriceList
{
    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes): PriceList
    {
        return DB::transaction(fn (): PriceList => PriceList::query()->create($attributes));
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\Pricing\Models\PriceList;
use Illuminate\Support\Facades\DB;

final readonly class DeletePriceList
{
    public function execute(PriceList $list): bool
    {
        return DB::transaction(function () use ($list): bool {
            $locked = PriceList::query()->forOwner()->whereKey($list->getKey())->lockForUpdate()->firstOrFail();

            return (bool) $locked->delete();
        });
    }
}

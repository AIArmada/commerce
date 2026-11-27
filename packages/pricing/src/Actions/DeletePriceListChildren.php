<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\Pricing\Models\PriceList;

final readonly class DeletePriceListChildren
{
    public function execute(PriceList $list): void
    {
        $list->prices()->delete();
        $list->tiers()->delete();
    }
}

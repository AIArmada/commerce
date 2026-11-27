<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\Pricing\Models\PriceList;
use Illuminate\Database\Eloquent\Model;

final readonly class ClearOtherDefaultPriceLists
{
    public function execute(PriceList $priceList, ?Model $owner): void
    {
        $query = PriceList::query();

        if (config('pricing.features.owner.enabled', false)) {
            $query = $query->forOwner($priceList->isGlobal() ? null : $owner, false);
        }

        if ($priceList->exists) {
            $query->whereKeyNot($priceList->getKey());
        }

        $query->where('is_default', true)->update(['is_default' => false]);
    }
}

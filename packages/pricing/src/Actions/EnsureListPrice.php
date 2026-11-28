<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use Illuminate\Support\Facades\DB;

final readonly class EnsureListPrice
{
    public function execute(
        PriceList $priceList,
        string $priceableType,
        string $priceableId,
        int $amount,
        ?int $compareAmount,
        string $currency,
    ): Price {
        return DB::transaction(function () use ($priceList, $priceableType, $priceableId, $amount, $compareAmount, $currency): Price {
            $priceList = PriceList::query()->forOwner()->whereKey($priceList->getKey())->lockForUpdate()->firstOrFail();
            $price = Price::query()->forOwner()->createOrFirst(
                [
                    'price_list_id' => $priceList->getKey(),
                    'priceable_type' => $priceableType,
                    'priceable_id' => $priceableId,
                    'min_quantity' => 1,
                ],
                [
                    'amount' => $amount,
                    'compare_amount' => $compareAmount,
                    'currency' => $currency,
                ],
            );

            $price = Price::query()->forOwner()->whereKey($price->getKey())->lockForUpdate()->firstOrFail();
            $price->fill([
                'amount' => $amount,
                'compare_amount' => $compareAmount,
                'currency' => $currency,
            ]);

            if ($price->isDirty()) {
                $price->save();
            }

            return $price;
        });
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use Illuminate\Database\UniqueConstraintViolationException;
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
            $identity = [
                'price_list_id' => $priceList->getKey(),
                'priceable_type' => $priceableType,
                'priceable_id' => $priceableId,
                'min_quantity' => 1,
            ];
            $values = [
                'amount' => $amount,
                'compare_amount' => $compareAmount,
                'currency' => $currency,
            ];

            $query = Price::query()->forOwner()->where($identity);
            $price = (clone $query)->lockForUpdate()->first();

            if ($price === null) {
                try {
                    return app(SavePrice::class)->create([...$identity, ...$values]);
                } catch (UniqueConstraintViolationException $exception) {
                    $price = (clone $query)->lockForUpdate()->first();

                    if ($price === null) {
                        throw $exception;
                    }
                }
            }

            $price->fill($values);

            if ($price->isDirty()) {
                $price->save();
            }

            return $price;
        }, 3);
    }
}

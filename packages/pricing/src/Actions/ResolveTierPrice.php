<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\Pricing\Models\PriceTier;
use Illuminate\Support\Arr;

final class ResolveTierPrice
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{price: int, tier: string}|null
     */
    public function resolve(string $tierableType, string $tierableId, int $quantity, array $context): ?array
    {
        if ($quantity <= 1) {
            return null;
        }

        $priceListId = Arr::get($context, 'price_list_id');

        $query = PriceTier::query()
            ->where('tierable_type', $tierableType)
            ->where('tierable_id', $tierableId)
            ->forQuantity($quantity)
            ->when(is_string($priceListId) && $priceListId !== '', function ($q) use ($priceListId): void {
                $q->where(function ($inner) use ($priceListId): void {
                    $inner->where('price_list_id', $priceListId)->orWhereNull('price_list_id');
                })->orderByRaw('CASE WHEN price_list_id IS NULL THEN 1 ELSE 0 END');
            }, function ($q): void {
                $q->whereNull('price_list_id');
            });

        $tier = $query->orderBy('min_quantity', 'desc')->first();

        if (! $tier) {
            return null;
        }

        return [
            'price' => $tier->amount,
            'tier' => $tier->getDescription(),
        ];
    }
}

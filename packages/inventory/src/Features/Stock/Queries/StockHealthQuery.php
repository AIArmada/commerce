<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Features\Stock\Api\StockHealth;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;

final readonly class StockHealthQuery implements StockHealth
{
    /** @return array{low_stock: int, out_of_stock: int} */
    public function counts(int $threshold): array
    {
        $counts = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->whereHas('location', fn (Builder $query): Builder => InventoryOwnerScope::applyToLocationQuery($query)->where('is_active', true))
            ->toBase()
            ->selectRaw('COUNT(CASE WHEN quantity_on_hand - quantity_reserved <= 0 THEN 1 END) AS out_of_stock')
            ->selectRaw('COUNT(CASE WHEN quantity_on_hand - quantity_reserved > 0 AND quantity_on_hand - quantity_reserved <= ? THEN 1 END) AS low_stock', [$threshold])
            ->first();

        return [
            'low_stock' => (int) ($counts->low_stock ?? 0),
            'out_of_stock' => (int) ($counts->out_of_stock ?? 0),
        ];
    }
}

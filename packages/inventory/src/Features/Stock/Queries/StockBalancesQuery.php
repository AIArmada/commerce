<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Features\Stock\Api\StockBalances;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;

final readonly class StockBalancesQuery implements StockBalances
{
    public function rows(?array $items = null, ?string $locationId = null, bool $exactOwner = false, ?string $inventoryableType = null, ?string $inventoryableId = null): iterable
    {
        if ($items !== null) {
            $unique = [];

            foreach ($items as $item) {
                $key = json_encode([$item['inventoryable_type'], $item['inventoryable_id'], $item['location_id']], JSON_THROW_ON_ERROR);
                $unique[$key] = $item;
            }

            $items = array_values($unique);
        }

        foreach ($items === null ? [null] : array_chunk($items, 200) as $chunk) {
            $query = $exactOwner
                ? InventoryOwnerScope::forMutation(InventoryLevel::query())
                : InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query());
            $query->where('quantity_on_hand', '>', 0);

            if ($locationId !== null) {
                $query->where('location_id', $locationId);
            }

            if ($inventoryableType !== null) {
                $query->where('inventoryable_type', $inventoryableType);
            }

            if ($inventoryableId !== null) {
                $query->where('inventoryable_id', $inventoryableId);
            }

            if ($chunk !== null) {
                $query->where(function (Builder $query) use ($chunk): void {
                    foreach ($chunk as $item) {
                        $query->orWhere(function (Builder $match) use ($item): void {
                            $match->where('inventoryable_type', $item['inventoryable_type'])
                                ->where('inventoryable_id', $item['inventoryable_id'])
                                ->where('location_id', $item['location_id']);
                        });
                    }
                });
            }

            foreach ($query->lazyById(200) as $level) {
                yield [
                    'inventoryable_type' => $level->inventoryable_type,
                    'inventoryable_id' => $level->inventoryable_id,
                    'location_id' => $level->location_id,
                    'owner_type' => $level->owner_type,
                    'owner_id' => $level->owner_id === null ? null : (string) $level->owner_id,
                    'quantity' => $level->quantity_on_hand,
                ];
            }
        }
    }
}

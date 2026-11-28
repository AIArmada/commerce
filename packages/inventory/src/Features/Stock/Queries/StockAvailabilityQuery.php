<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Features\Stock\Api\StockAvailability;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;

final readonly class StockAvailabilityQuery implements StockAvailability
{
    public function byItem(array $items): iterable
    {
        $unique = [];

        foreach ($items as $item) {
            $unique[serialize([$item['type'], $item['id']])] = $item;
        }

        foreach (array_chunk(array_values($unique), 200) as $chunk) {
            $byType = [];

            foreach ($chunk as $item) {
                $byType[$item['type']][] = $item['id'];
            }

            $query = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
                ->where(function (Builder $query) use ($byType): void {
                    foreach ($byType as $type => $ids) {
                        $query->orWhere(function (Builder $match) use ($type, $ids): void {
                            $match->where('inventoryable_type', $type)->whereIn('inventoryable_id', $ids);
                        });
                    }
                })
                ->whereHas('location', fn (Builder $query): Builder => InventoryOwnerScope::applyToLocationQuery($query)->where('is_active', true))
                ->select(['inventoryable_type', 'inventoryable_id', 'location_id'])
                ->selectRaw('SUM(CASE WHEN quantity_on_hand > quantity_reserved THEN quantity_on_hand - quantity_reserved ELSE 0 END) as available')
                ->groupBy('inventoryable_type', 'inventoryable_id', 'location_id');

            foreach ($query->toBase()->cursor() as $row) {
                yield [
                    'type' => (string) $row->inventoryable_type,
                    'id' => (string) $row->inventoryable_id,
                    'location_id' => (string) $row->location_id,
                    'available' => (int) $row->available,
                ];
            }
        }
    }
}

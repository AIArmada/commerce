<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Features\Stock\Api\ReorderStock;
use AIArmada\Inventory\Features\Stock\Api\ReorderStockLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final readonly class ReorderStockQuery implements ReorderStock
{
    public function candidates(?string $locationId = null): iterable
    {
        $query = InventoryOwnerScope::forMutation(InventoryLevel::query()->needsReorder())
            ->whereHas('location', fn (Builder $query): Builder => InventoryOwnerScope::forMutation($query)->where('is_active', true));

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        foreach ($query->lazyById(200) as $level) {
            yield $this->snapshot($level);
        }
    }

    public function locked(string $levelId): ReorderStockLevel
    {
        if ((new InventoryLevel)->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Reorder stock reads require an active transaction.');
        }

        $level = InventoryOwnerScope::forMutation(InventoryLevel::query())
            ->whereKey($levelId)
            ->lockForUpdate()
            ->firstOrFail();

        return $this->snapshot($level);
    }

    private function snapshot(InventoryLevel $level): ReorderStockLevel
    {
        return new ReorderStockLevel(
            id: $level->id,
            inventoryableType: $level->inventoryable_type,
            inventoryableId: $level->inventoryable_id,
            locationId: $level->location_id,
            available: $level->available,
            reserved: $level->quantity_reserved,
            safetyStock: $level->safety_stock,
            maxStock: $level->max_stock,
            leadTimeDays: $level->lead_time_days,
        );
    }
}

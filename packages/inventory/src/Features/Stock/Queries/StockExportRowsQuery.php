<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Features\Stock\Api\StockExportRows;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class StockExportRowsQuery implements StockExportRows
{
    public function levels(array $filters): iterable
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()->with('location:id,name')
        );

        if (isset($filters['location_id'])) {
            $locationId = (string) $filters['location_id'];

            if (InventoryOwnerScope::isEnabled()) {
                $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                    ->whereKey($locationId)
                    ->exists();

                if (! $isAllowed) {
                    throw new InvalidArgumentException('Invalid location for current owner');
                }
            }

            $query->where('location_id', $locationId);
        }

        if (isset($filters['low_stock_only']) && $filters['low_stock_only']) {
            $query->lowStock();
        }

        if (isset($filters['out_of_stock_only']) && $filters['out_of_stock_only']) {
            $query->where('quantity_on_hand', '<=', 0);
        }

        foreach ($query->lazyById(500) as $level) {
            $available = $level->available;
            $status = match (true) {
                $level->quantity_on_hand <= 0 => 'Out of Stock',
                $level->isLowStock() => 'Low Stock',
                $level->needsReorder() => 'Reorder Needed',
                default => 'In Stock',
            };

            yield [
                $level->inventoryable_type,
                $level->inventoryable_id,
                $level->location->name ?? 'Unknown',
                $level->quantity_on_hand,
                $level->quantity_reserved,
                $available,
                $level->safety_stock ?? 0,
                $level->max_stock ?? 0,
                $level->reorder_point ?? 0,
                $status,
                $level->updated_at->format('Y-m-d H:i:s'),
            ];
        }
    }

    public function movements(CarbonImmutable $startDate, CarbonImmutable $endDate, ?string $movementType, ?string $locationId): iterable
    {
        $query = InventoryMovement::query()
            ->with(['fromLocation:id,name', 'toLocation:id,name'])
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->orderBy('occurred_at', 'desc');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($query);
        }

        if ($movementType !== null) {
            $query->where('type', $movementType);
        }

        if ($locationId !== null) {
            if (InventoryOwnerScope::isEnabled()) {
                $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                    ->whereKey($locationId)
                    ->exists();

                if (! $isAllowed) {
                    throw new InvalidArgumentException('Invalid location for current owner');
                }
            }

            $query->where(function ($q) use ($locationId): void {
                $q->where('from_location_id', $locationId)
                    ->orWhere('to_location_id', $locationId);
            });
        }

        foreach ($query->orderBy('id')->lazy(500) as $movement) {
            yield [
                $movement->id,
                $movement->type,
                $movement->inventoryable_type,
                $movement->inventoryable_id,
                $movement->fromLocation?->name ?? '-',
                $movement->toLocation?->name ?? '-',
                $movement->quantity,
                $movement->reason,
                $movement->reference,
                $movement->note,
                $movement->occurred_at->format('Y-m-d H:i:s'),
                $movement->created_at->format('Y-m-d H:i:s'),
            ];
        }
    }
}

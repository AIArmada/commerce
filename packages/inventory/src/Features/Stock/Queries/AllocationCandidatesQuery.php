<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Data\AllocationStrategy;
use AIArmada\Inventory\Features\Stock\Api\AllocationCandidates;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class AllocationCandidatesQuery implements AllocationCandidates
{
    public function locked(string $inventoryableType, string $inventoryableId, AllocationStrategy $strategy): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Allocation candidates require an active transaction.');
        }

        $levels = InventoryOwnerScope::forMutation(InventoryLevel::query())
            ->where('inventoryable_type', $inventoryableType)
            ->where('inventoryable_id', $inventoryableId)
            ->whereRaw('quantity_on_hand > quantity_reserved')
            ->whereHas('location', fn (Builder $query): Builder => InventoryOwnerScope::forMutation($query)->where('is_active', true))
            ->with('location')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $ordered = $levels->sort(static function (InventoryLevel $left, InventoryLevel $right) use ($strategy): int {
            $comparison = match ($strategy) {
                AllocationStrategy::Priority, AllocationStrategy::SingleLocation => ($right->location?->priority ?? 0) <=> ($left->location?->priority ?? 0),
                AllocationStrategy::FIFO => $left->created_at <=> $right->created_at,
                AllocationStrategy::LeastStock => $right->available <=> $left->available,
            };

            return $comparison ?: strcmp($left->id, $right->id);
        });

        return $ordered->map(static fn (InventoryLevel $level): array => [
            'id' => $level->id,
            'location_id' => $level->location_id,
            'available' => $level->available,
        ])->values()->all();
    }
}

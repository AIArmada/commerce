<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Features\Stock\Api\DeductionCandidates;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class DeductionCandidatesQuery implements DeductionCandidates
{
    public function locked(array $items): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Deduction candidates require an active transaction.');
        }

        if ($items === []) {
            return [];
        }

        $byType = [];

        foreach ($items as $item) {
            $byType[$item['inventoryable_type']][] = $item['inventoryable_id'];
        }

        $levels = InventoryOwnerScope::forMutation(InventoryLevel::query())
            ->where(function (Builder $query) use ($byType): void {
                foreach ($byType as $type => $ids) {
                    $query->orWhere(fn (Builder $group): Builder => $group
                        ->where('inventoryable_type', $type)
                        ->whereIn('inventoryable_id', $ids));
                }
            })
            ->whereRaw('quantity_on_hand > quantity_reserved')
            ->whereHas('location', fn (Builder $query): Builder => InventoryOwnerScope::forMutation($query)->where('is_active', true))
            ->with('location')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $ordered = $levels->sort(static function (InventoryLevel $left, InventoryLevel $right): int {
            return (($right->location?->priority ?? 0) <=> ($left->location?->priority ?? 0))
                ?: strcmp($left->id, $right->id);
        });

        $availability = [];

        foreach ($ordered as $level) {
            $key = $level->inventoryable_type . ':' . $level->inventoryable_id;
            $availability[$key][] = [
                'location_id' => $level->location_id,
                'available' => $level->available,
            ];
        }

        return $availability;
    }
}

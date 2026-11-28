<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class InventoryStockQuery
{
    /**
     * Get availability across all locations.
     * Availability is read directly from committed inventory levels.
     *
     * @return array<string, int> Location ID => available quantity
     */
    public function getAvailability(Model $model): array
    {
        $rows = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
        )
            ->select('location_id')
            ->selectRaw($this->availableAggregateExpression() . ' as available_total')
            ->groupBy('location_id')
            ->get();

        $availability = [];

        foreach ($rows as $row) {
            $availability[$row->location_id] = (int) $row->getAttribute('available_total');
        }

        return $availability;
    }

    /**
     * Get total available quantity across all locations.
     */
    public function getTotalAvailable(Model $model): int
    {
        $total = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
        )->sum(DB::raw($this->availablePerLevelExpression()));

        return (int) $total;
    }

    /**
     * Get total available quantities for many models in bounded batches.
     *
     * Powers per-variant fan-out reads (product pages, carts) without the
     * N+1 cost of calling getTotalAvailable() per variant. Models without
     * any active-location level are reported as zero.
     *
     * @param  iterable<int, Model>  $models
     * @return array<string, int> Keyed by "{morph-class}:{key}".
     */
    public function getAvailabilityForMany(iterable $models): array
    {
        $pairs = [];

        foreach ($models as $model) {
            $pairs[$model->getMorphClass() . ':' . $model->getKey()] = [
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
            ];
        }

        $totals = array_fill_keys(array_keys($pairs), 0);

        if ($pairs === []) {
            return $totals;
        }

        foreach (array_chunk($pairs, 200) as $chunk) {
            $byType = [];

            foreach ($chunk as $pair) {
                $byType[$pair['inventoryable_type']][] = $pair['inventoryable_id'];
            }

            $rows = InventoryOwnerScope::applyToLocationQuery(
                InventoryLevel::query()
                    ->where(function (Builder $query) use ($byType): void {
                        foreach ($byType as $type => $ids) {
                            $query->orWhere(function (Builder $group) use ($type, $ids): void {
                                $group->where('inventoryable_type', $type)
                                    ->whereIn('inventoryable_id', $ids);
                            });
                        }
                    })
                    ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
            )
                ->select('inventoryable_type', 'inventoryable_id')
                ->selectRaw($this->availableAggregateExpression() . ' as available_total')
                ->groupBy('inventoryable_type', 'inventoryable_id')
                ->get();

            foreach ($rows as $row) {
                $key = $row->inventoryable_type . ':' . $row->inventoryable_id;

                if (array_key_exists($key, $totals)) {
                    $totals[$key] = (int) $row->getAttribute('available_total');
                }
            }
        }

        return $totals;
    }

    /**
     * Per-level available quantity, mirroring InventoryLevel::available
     * (never negative) in portable SQL.
     */
    private function availablePerLevelExpression(): string
    {
        return 'CASE WHEN quantity_on_hand > quantity_reserved THEN quantity_on_hand - quantity_reserved ELSE 0 END';
    }

    private function availableAggregateExpression(): string
    {
        return 'SUM(' . $this->availablePerLevelExpression() . ')';
    }

    /**
     * Get total on-hand quantity across all locations.
     */
    public function getTotalOnHand(Model $model): int
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->whereHas('location', fn (Builder $locationQuery): Builder => $locationQuery->where('is_active', true))
        );

        return (int) $query->sum('quantity_on_hand');
    }

    /**
     * Check if sufficient inventory exists.
     */
    public function hasInventory(Model $model, int $quantity): bool
    {
        return $this->getTotalAvailable($model) >= $quantity;
    }

    /**
     * Get inventory level at a specific location.
     */
    public function getLevel(Model $model, string $locationId): ?InventoryLevel
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->where('location_id', $locationId)
        );

        return $query->first();
    }

    /**
     * Get movement history for a model.
     *
     * @return Collection<int, InventoryMovement>
     */
    public function getMovementHistory(Model $model, int $limit = 50): Collection
    {
        $query = InventoryMovement::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey());

        InventoryOwnerScope::applyToMovementQuery($query);

        return $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}

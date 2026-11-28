<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation\Queries;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Features\Valuation\Api\InventoryValues;
use AIArmada\Inventory\Features\Valuation\Models\InventoryCostLayer;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class InventoryValuesQuery implements InventoryValues
{
    public function __construct(private StandardStockValuesQuery $standard) {}

    public function total(CostingMethod $method, string $currency): int
    {
        if ($method === CostingMethod::Standard) {
            $total = 0;

            foreach ($this->standard->rows($currency) as $row) {
                $total += $row['total_value'];
            }

            return $total;
        }

        return (int) $this->layers($method, $currency)
            ->toBase()
            ->sum(DB::raw('remaining_quantity * unit_cost_minor'));
    }

    public function byLocation(CostingMethod $method, string $currency): array
    {
        if ($method === CostingMethod::Standard) {
            $values = [];

            foreach ($this->standard->rows($currency) as $row) {
                $values[$row['location_id']] = ($values[$row['location_id']] ?? 0) + $row['total_value'];
            }

            return $values;
        }

        return $this->layers($method, $currency)
            ->whereNotNull('location_id')
            ->toBase()
            ->selectRaw('location_id, SUM(remaining_quantity * unit_cost_minor) AS total_value')
            ->groupBy('location_id')
            ->pluck('total_value', 'location_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();
    }

    public function rankedItems(CostingMethod $method, string $currency, int $limit): array
    {
        if ($method === CostingMethod::Standard) {
            return $this->rankStandardItems($currency, $limit);
        }

        return $this->layers($method, $currency)
            ->toBase()
            ->select('inventoryable_type', 'inventoryable_id')
            ->selectRaw('SUM(remaining_quantity * unit_cost_minor) AS total_value')
            ->selectRaw('SUM(SUM(remaining_quantity * unit_cost_minor)) OVER () AS overall_value')
            ->groupBy('inventoryable_type', 'inventoryable_id')
            ->havingRaw('SUM(remaining_quantity * unit_cost_minor) > 0')
            ->orderByDesc('total_value')
            ->orderBy('inventoryable_type')
            ->orderBy('inventoryable_id')
            ->limit(max(1, $limit))
            ->get()
            ->map(static fn ($row): array => [
                'inventoryable_type' => (string) $row->inventoryable_type,
                'inventoryable_id' => (string) $row->inventoryable_id,
                'total_value' => (int) $row->total_value,
                'overall_value' => (int) $row->overall_value,
            ])
            ->values()
            ->all();
    }

    public function byItemsAtLocations(CostingMethod $method, string $currency, array $items): array
    {
        $values = [];

        if ($method === CostingMethod::Standard) {
            foreach ($this->standard->rows($currency, $items) as $row) {
                $values[$row['inventoryable_type']][$row['inventoryable_id']][$row['location_id']] = $row['total_value'];
            }

            return $values;
        }

        foreach (array_chunk($items, 200) as $chunk) {
            $rows = $this->layers($method, $currency)
                ->where(function (Builder $query) use ($chunk): void {
                    foreach ($chunk as $item) {
                        $query->orWhere(function (Builder $match) use ($item): void {
                            $match->where('inventoryable_type', $item['inventoryable_type'])
                                ->where('inventoryable_id', $item['inventoryable_id'])
                                ->where('location_id', $item['location_id']);
                        });
                    }
                })
                ->toBase()
                ->select('inventoryable_type', 'inventoryable_id', 'location_id')
                ->selectRaw('SUM(remaining_quantity * unit_cost_minor) AS total_value')
                ->groupBy('inventoryable_type', 'inventoryable_id', 'location_id')
                ->get();

            foreach ($rows as $row) {
                $values[$row->inventoryable_type][$row->inventoryable_id][$row->location_id] = (int) $row->total_value;
            }
        }

        return $values;
    }

    /** @return list<array{inventoryable_type: string, inventoryable_id: string, total_value: int, overall_value: int}> */
    private function rankStandardItems(string $currency, int $limit): array
    {
        $grouped = [];
        $total = 0;

        foreach ($this->standard->rows($currency) as $row) {
            $type = $row['inventoryable_type'];
            $id = $row['inventoryable_id'];
            $value = $row['total_value'];

            if ($value <= 0) {
                continue;
            }

            $grouped[$type][$id] = ($grouped[$type][$id] ?? 0) + $value;
            $total += $value;
        }

        $items = [];

        foreach ($grouped as $type => $values) {
            foreach ($values as $id => $value) {
                $items[] = [
                    'inventoryable_type' => $type,
                    'inventoryable_id' => (string) $id,
                    'total_value' => $value,
                    'overall_value' => $total,
                ];
            }
        }

        usort($items, static fn (array $a, array $b): int =>
            ($b['total_value'] <=> $a['total_value'])
            ?: ($a['inventoryable_type'] <=> $b['inventoryable_type'])
            ?: ($a['inventoryable_id'] <=> $b['inventoryable_id']));

        return array_slice($items, 0, max(1, $limit));
    }

    /** @return Builder<InventoryCostLayer> */
    private function layers(CostingMethod $method, string $currency): Builder
    {
        if (! in_array($method, [CostingMethod::Fifo, CostingMethod::WeightedAverage], true)) {
            throw new InvalidArgumentException('Cost-layer reports require FIFO or weighted average costing.');
        }

        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryCostLayer::query()->withRemainingQuantity()->usingMethod($method)
        )->where('currency', $currency);

        if (InventoryOwnerScope::isEnabled() && ! InventoryOwnerScope::includeGlobal() && ! InventoryOwnerScope::isCurrentContextGlobalOnly()) {
            $query->whereNotNull('location_id');
        }

        return $query;
    }
}

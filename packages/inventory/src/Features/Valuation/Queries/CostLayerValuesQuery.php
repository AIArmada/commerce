<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation\Queries;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Features\Valuation\Api\CostLayerValues;
use AIArmada\Inventory\Features\Valuation\Models\InventoryCostLayer;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class CostLayerValuesQuery implements CostLayerValues
{
    public function total(CostingMethod $method, string $currency): int
    {
        return (int) $this->layers($method, $currency)
            ->toBase()
            ->sum(DB::raw('remaining_quantity * unit_cost_minor'));
    }

    public function byLocation(CostingMethod $method, string $currency): array
    {
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

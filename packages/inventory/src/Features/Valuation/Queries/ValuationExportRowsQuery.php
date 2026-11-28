<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation\Queries;

use AIArmada\Inventory\Features\Valuation\Api\ValuationExportRows;
use AIArmada\Inventory\Features\Valuation\Models\InventoryValuationSnapshot;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;

final readonly class ValuationExportRowsQuery implements ValuationExportRows
{
    public function rows(CarbonImmutable $startDate, CarbonImmutable $endDate): iterable
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryValuationSnapshot::query()
                ->with('location:id,name')
                ->whereDate('snapshot_date', '>=', $startDate->toDateString())
                ->whereDate('snapshot_date', '<=', $endDate->toDateString())
                ->orderBy('snapshot_date', 'desc')
        );

        foreach ($query->orderBy('id')->lazy(500) as $snapshot) {
            yield [
                $snapshot->snapshot_date->format('Y-m-d'),
                $snapshot->costing_method->label(),
                $snapshot->location_id === null ? 'All Locations' : ($snapshot->location?->name ?? 'Unknown'),
                $snapshot->sku_count,
                $snapshot->total_quantity,
                $snapshot->total_value_minor / 100,
                $snapshot->average_unit_cost_minor / 100,
                $snapshot->currency,
                $snapshot->variance_from_previous_minor !== null ? $snapshot->variance_from_previous_minor / 100 : null,
                $snapshot->created_at?->format('Y-m-d H:i:s'),
            ];
        }
    }
}

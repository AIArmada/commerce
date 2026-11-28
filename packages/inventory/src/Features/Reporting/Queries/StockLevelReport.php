<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reporting\Queries;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Features\Replenishment\Api\ReorderSuggestionCounts;
use AIArmada\Inventory\Features\Reservations\Api\ActiveAllocationCount;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Features\Traceability\Api\BatchAging;
use AIArmada\Inventory\Features\Valuation\Api\InventoryValues;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates stock level and availability reports.
 */
final class StockLevelReport
{
    /**
     * Get the summary used by inventory dashboards.
     *
     * @return array{total_locations: int, active_locations: int, total_skus: int, total_on_hand: int, total_reserved: int, active_allocations: int}
     */
    public function getOverview(): array
    {
        $locationTotals = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->selectRaw('COUNT(*) as total_locations')
            ->selectRaw('SUM(CASE WHEN is_active = ? THEN 1 ELSE 0 END) as active_locations', [true])
            ->toBase()
            ->first();

        $levelTotals = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->selectRaw('COALESCE(SUM(quantity_on_hand), 0) as total_on_hand')
            ->selectRaw('COALESCE(SUM(quantity_reserved), 0) as total_reserved')
            ->toBase()
            ->first();

        $distinctSkus = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->select('inventoryable_type', 'inventoryable_id')
            ->distinct();

        return [
            'total_locations' => (int) ($locationTotals->total_locations ?? 0),
            'active_locations' => (int) ($locationTotals->active_locations ?? 0),
            'total_skus' => (int) DB::query()
                ->fromSub($distinctSkus->toBase(), 'distinct_skus')
                ->count(),
            'total_on_hand' => (int) ($levelTotals->total_on_hand ?? 0),
            'total_reserved' => (int) ($levelTotals->total_reserved ?? 0),
            'active_allocations' => app(ActiveAllocationCount::class)->count(),
        ];
    }

    /**
     * Count active levels below an available-quantity threshold.
     */
    public function getLowInventoryCount(?int $threshold = null): int
    {
        $threshold ??= config('inventory.default_reorder_point', 10);

        return InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= ?', [$threshold])
            ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
            ->count();
    }

    /**
     * Count active levels with no available quantity.
     */
    public function getOutOfStockCount(): int
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= 0')
            ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
            ->count();
    }

    /**
     * Count active levels below their configured reorder point.
     */
    public function getLowStockCount(): int
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->whereRaw('quantity_on_hand - quantity_reserved <= reorder_point')
            ->where('reorder_point', '>', 0)
            ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
            ->count();
    }

    /**
     * Get active levels that need replenishment.
     *
     * @return Builder<InventoryLevel>
     */
    public function getLowStockQuery(): Builder
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->with('location')
            ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
            ->whereRaw('quantity_on_hand - quantity_reserved <= reorder_point')
            ->where('reorder_point', '>', 0)
            ->select('*')
            ->selectRaw('(reorder_point - CASE WHEN quantity_on_hand > quantity_reserved THEN quantity_on_hand - quantity_reserved ELSE 0 END) AS deficit')
            ->orderByDesc('deficit')
            ->orderBy('id');
    }

    /**
     * Get current stock summary by location.
     *
     * @return Collection<int, array{
     *     location_id: string,
     *     location_name: string,
     *     sku_count: int,
     *     total_quantity: int,
     *     total_value: int,
     *     low_stock_count: int,
     *     out_of_stock_count: int,
     * }>
     */
    public function getStockByLocation(CostingMethod $method = CostingMethod::Fifo): Collection
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->select([
                    'location_id',
                    DB::raw('COUNT(*) as sku_count'),
                    DB::raw('SUM(quantity_on_hand) as total_quantity'),
                    DB::raw('COUNT(CASE WHEN quantity_on_hand - quantity_reserved > 0 AND reorder_point > 0 AND quantity_on_hand - quantity_reserved <= reorder_point THEN 1 END) as low_stock_count'),
                    DB::raw('COUNT(CASE WHEN quantity_on_hand - quantity_reserved <= 0 THEN 1 END) as out_of_stock_count'),
                ])
                ->with('location:id,name')
                ->groupBy('location_id')
        );

        $values = app(InventoryValues::class)->byLocation($method, (string) config('inventory.defaults.currency', 'MYR'));

        /** @var Collection<int, array{location_id: string, location_name: string, sku_count: int, total_quantity: int, total_value: int, low_stock_count: int, out_of_stock_count: int}> $stockByLocation */
        $stockByLocation = $query->get()
            ->map(fn ($row): array => [
                'location_id' => (string) $row->location_id,
                'location_name' => (string) ($row->location?->name ?? 'Unknown'),
                'sku_count' => (int) $row->sku_count,
                'total_quantity' => (int) $row->total_quantity,
                'total_value' => $values[$row->location_id] ?? 0,
                'low_stock_count' => (int) $row->getAttribute('low_stock_count'),
                'out_of_stock_count' => (int) $row->getAttribute('out_of_stock_count'),
            ]);

        return $stockByLocation;
    }

    /**
     * Get ABC analysis (Pareto classification).
     *
     * @return Collection<int, array{
     *     inventoryable_type: string,
     *     inventoryable_id: string,
     *     total_value: int,
     *     cumulative_percentage: float,
     *     classification: string,
     * }>
     */
    public function getAbcAnalysis(int $limit = 500, CostingMethod $method = CostingMethod::Fifo): Collection
    {
        $items = app(InventoryValues::class)->rankedItems(
            $method,
            (string) config('inventory.defaults.currency', 'MYR'),
            $limit,
        );

        $cumulativeValue = 0;

        return collect($items)->map(function (array $item) use (&$cumulativeValue): array {
            $previousPercentage = ($cumulativeValue / $item['overall_value']) * 100;
            $cumulativeValue += $item['total_value'];

            return [
                'inventoryable_type' => $item['inventoryable_type'],
                'inventoryable_id' => $item['inventoryable_id'],
                'total_value' => $item['total_value'],
                'cumulative_percentage' => round(($cumulativeValue / $item['overall_value']) * 100, 2),
                'classification' => match (true) {
                    $previousPercentage < 80 => 'A',
                    $previousPercentage < 95 => 'B',
                    default => 'C',
                },
            ];
        });
    }

    /**
     * Get aging analysis for batches.
     *
     * @return Collection<int, array{
     *     age_range: string,
     *     batch_count: int,
     *     total_quantity: int,
     *     total_value: int,
     *     expiring_soon: int,
     * }>
     */
    public function getBatchAgingAnalysis(): Collection
    {
        return collect(app(BatchAging::class)->summarize());
    }

    /**
     * Get reorder status report.
     *
     * @return array{
     *     items_below_reorder_point: int,
     *     pending_suggestions: int,
     *     approved_suggestions: int,
     *     total_suggested_value: int,
     *     unpriced_suggestions: int,
     *     urgent_reorders: int,
     * }
     */
    public function getReorderStatus(): array
    {
        $belowReorderPointQuery = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()->needsReorder()
        )->select('inventoryable_type', 'inventoryable_id')->distinct();

        $belowReorderPoint = DB::query()
            ->fromSub($belowReorderPointQuery->toBase(), 'reorder_items')
            ->count();

        $counts = app(ReorderSuggestionCounts::class)->counts((string) config('inventory.defaults.currency', 'MYR'));

        return [
            'items_below_reorder_point' => $belowReorderPoint,
            'pending_suggestions' => $counts['pending'],
            'approved_suggestions' => $counts['approved'],
            'total_suggested_value' => $counts['suggested_value'],
            'unpriced_suggestions' => $counts['unpriced'],
            'urgent_reorders' => $counts['urgent'],
        ];
    }

    /**
     * Get stock distribution analysis.
     *
     * @return Collection<int, array{
     *     inventoryable_type: string,
     *     inventoryable_id: string,
     *     location_count: int,
     *     total_quantity: int,
     *     max_location_quantity: int,
     *     min_location_quantity: int,
     *     concentration_ratio: float,
     * }>
     */
    public function getStockDistribution(int $limit = 20): Collection
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->select([
                    'inventoryable_type',
                    'inventoryable_id',
                    DB::raw('COUNT(DISTINCT location_id) as location_count'),
                    DB::raw('SUM(quantity_on_hand) as total_quantity'),
                    DB::raw('MAX(quantity_on_hand) as max_quantity'),
                    DB::raw('MIN(quantity_on_hand) as min_quantity'),
                ])
                ->groupBy('inventoryable_type', 'inventoryable_id')
                ->havingRaw('COUNT(DISTINCT location_id) > ?', [1])
                ->orderByDesc('total_quantity')
                ->limit($limit)
        );

        return $query->get()
            ->map(fn ($row): array => [
                'inventoryable_type' => (string) $row->inventoryable_type,
                'inventoryable_id' => (string) $row->inventoryable_id,
                'location_count' => (int) $row->getAttribute('location_count'),
                'total_quantity' => (int) $row->total_quantity,
                'max_location_quantity' => (int) $row->max_quantity,
                'min_location_quantity' => (int) $row->min_quantity,
                'concentration_ratio' => (int) $row->total_quantity > 0
                    ? round(((int) $row->max_quantity / (int) $row->total_quantity) * 100, 2)
                    : 0.0,
            ]);
    }

    /**
     * Get stagnant stock levels by last update and their recorded cost-layer value.
     *
     * @return Collection<int, array{
     *     inventoryable_type: string,
     *     inventoryable_id: string,
     *     quantity: int,
     *     value: int|null,
     *     location_id: string,
     *     days_stagnant: int,
     * }>
     */
    public function getDeadStock(int $daysThreshold = 90, int $limit = 50, CostingMethod $method = CostingMethod::Fifo): Collection
    {
        $cutoffDate = CarbonImmutable::now()->subDays($daysThreshold);
        $tableName = config('inventory.database.tables.levels', 'inventory_levels');

        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->select([
                    "{$tableName}.inventoryable_type",
                    "{$tableName}.inventoryable_id",
                    "{$tableName}.quantity_on_hand as quantity",
                    "{$tableName}.location_id",
                    "{$tableName}.updated_at",
                ])
                ->where("{$tableName}.quantity_on_hand", '>', 0)
                ->where("{$tableName}.updated_at", '<', $cutoffDate)
                ->orderBy("{$tableName}.updated_at")
                ->orderBy("{$tableName}.id")
                ->limit(max(1, $limit))
        );

        $rows = $query->get();
        $items = $rows->map(static fn (InventoryLevel $row): array => [
            'inventoryable_type' => (string) $row->inventoryable_type,
            'inventoryable_id' => (string) $row->inventoryable_id,
            'location_id' => (string) $row->location_id,
        ])->values()->all();

        $values = app(InventoryValues::class)->byItemsAtLocations(
            $method,
            (string) config('inventory.defaults.currency', 'MYR'),
            $items,
        );

        /** @var Collection<int, array{inventoryable_type: string, inventoryable_id: string, quantity: int, value: int|null, location_id: string, days_stagnant: int}> $deadStock */
        $deadStock = $rows
            ->map(fn ($row): array => [
                'inventoryable_type' => (string) $row->inventoryable_type,
                'inventoryable_id' => (string) $row->inventoryable_id,
                'quantity' => (int) $row->quantity,
                'value' => $values[$row->inventoryable_type][$row->inventoryable_id][$row->location_id] ?? null,
                'location_id' => (string) $row->location_id,
                'days_stagnant' => (int) CarbonImmutable::parse($row->updated_at)->diffInDays(CarbonImmutable::now()),
            ]);

        return $deadStock;
    }

    /**
     * Get stock accuracy metrics from cycle counts.
     *
     * @return array{
     *     total_counts: int,
     *     accurate_counts: int,
     *     accuracy_percentage: float,
     *     total_variance_units: int,
     *     total_variance_value: int|null,
     *     avg_variance_percentage: float|null,
     * }
     */
    public function getCycleCountMetrics(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): array {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        $countsQuery = InventoryMovement::query()
            ->where('type', MovementType::Adjustment->value)
            ->where('reason', 'cycle_count')
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($countsQuery);
        }

        $aggregate = $countsQuery
            ->selectRaw('COUNT(*) as total_counts')
            ->selectRaw('SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as accurate_counts')
            ->selectRaw('SUM(ABS(quantity)) as total_variance')
            ->selectRaw('AVG(CASE WHEN quantity_before > 0 THEN ABS(quantity) * 100.0 / quantity_before WHEN quantity_before = 0 AND quantity = 0 THEN 0.0 ELSE NULL END) as avg_variance_percentage')
            ->selectRaw('COUNT(CASE WHEN quantity_before IS NULL OR (quantity_before = 0 AND quantity <> 0) THEN 1 END) as undefined_variance_counts')
            ->toBase()
            ->first();

        $totalCounts = (int) ($aggregate->total_counts ?? 0);
        $accurateCounts = (int) ($aggregate->accurate_counts ?? 0);
        $totalVariance = (int) ($aggregate->total_variance ?? 0);

        return [
            'total_counts' => $totalCounts,
            'accurate_counts' => $accurateCounts,
            'accuracy_percentage' => $totalCounts > 0
                ? round(($accurateCounts / $totalCounts) * 100, 2)
                : 100.0,
            'total_variance_units' => (int) $totalVariance,
            'total_variance_value' => $totalVariance === 0 ? 0 : null,
            'avg_variance_percentage' => $totalCounts === 0 ? 0.0 : ((int) ($aggregate->undefined_variance_counts ?? 0) > 0 ? null : round((float) $aggregate->avg_variance_percentage, 2)),
        ];
    }
}

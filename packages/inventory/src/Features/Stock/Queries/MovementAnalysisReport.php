<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Generates inventory movement analysis reports.
 */
final readonly class MovementAnalysisReport
{
    /**
     * Get movement totals for the dashboard period.
     *
     * @return array{receipts: int, shipments: int, transfers: int, adjustments: int, total: int}
     */
    public function getStats(int $days = 30): array
    {
        $since = CarbonImmutable::now()->subDays($days);

        $query = InventoryMovement::query()
            ->where('occurred_at', '>=', $since)
            ->selectRaw('type, SUM(quantity) as total, COUNT(*) as movement_count')
            ->groupBy('type');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($query);
        }

        $movements = $query->toBase()->get()->keyBy('type');

        return [
            'receipts' => (int) ($movements->get(MovementType::Receipt->value)?->total ?? 0),
            'shipments' => (int) ($movements->get(MovementType::Shipment->value)?->total ?? 0),
            'transfers' => (int) ($movements->get(MovementType::Transfer->value)?->total ?? 0),
            'adjustments' => (int) ($movements->get(MovementType::Adjustment->value)?->total ?? 0),
            'total' => (int) $movements->sum('movement_count'),
        ];
    }

    /**
     * Get movement summary by type for a period.
     *
     * @return Collection<int, array{
     *     movement_type: string,
     *     count: int,
     *     total_quantity: int,
     *     total_value: int,
     * }>
     */
    public function getMovementSummaryByType(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
        ?string $locationId = null,
    ): Collection {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        if ($locationId !== null) {
            $this->getScopedLocationOrFail($locationId);
        }

        $query = InventoryMovement::query()
            ->select([
                'type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(quantity) as total_quantity'),
            ])
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->when($locationId, fn ($q) => $q->where(function ($query) use ($locationId): void {
                $query->where('from_location_id', $locationId)
                    ->orWhere('to_location_id', $locationId);
            }))
            ->groupBy('type')
            ->orderByDesc('count')
            ->orderBy('type');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($query);
        }

        /** @var Collection<int, array{movement_type: string, count: int, total_quantity: int, total_value: int}> $summary */
        $summary = $query->toBase()->get()
            ->map(fn ($row): array => [
                'movement_type' => (string) $row->type,
                'count' => (int) $row->count,
                'total_quantity' => (int) $row->total_quantity,
                'total_value' => (int) 0, // Value requires cost layer integration
            ]);

        return $summary;
    }

    /**
     * Get daily movement trends.
     *
     * @return Collection<int, array{
     *     date: string,
     *     receipts: int,
     *     shipments: int,
     *     adjustments: int,
     *     transfers: int,
     * }>
     */
    public function getDailyMovementTrends(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): Collection {
        $startDate ??= CarbonImmutable::now()->subDays(30);
        $endDate ??= CarbonImmutable::now();

        $movementsQuery = InventoryMovement::query()
            ->select([
                DB::raw('DATE(occurred_at) as date'),
                'type',
                DB::raw('SUM(quantity) as total'),
            ])
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(occurred_at)'), 'type');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($movementsQuery);
        }

        $movements = $movementsQuery->toBase()->get()->groupBy('date');

        $dates = collect();
        $current = $startDate->startOfDay();

        while ($current->lte($endDate)) {
            $dateStr = $current->format('Y-m-d');
            $dayMovements = $movements->get($dateStr, collect());

            $dates->push([
                'date' => $dateStr,
                'receipts' => (int) $dayMovements->where('type', MovementType::Receipt->value)->sum('total'),
                'shipments' => (int) $dayMovements->where('type', MovementType::Shipment->value)->sum('total'),
                'adjustments' => (int) $dayMovements->where('type', MovementType::Adjustment->value)->sum('total'),
                'transfers' => (int) $dayMovements->where('type', MovementType::Transfer->value)->sum('total'),
            ]);

            $current = $current->addDay();
        }

        return $dates;
    }

    /**
     * Get top movers (most active SKUs).
     *
     * @return Collection<int, array{
     *     inventoryable_type: string,
     *     inventoryable_id: string,
     *     movement_count: int,
     *     total_quantity_moved: int,
     *     avg_quantity_per_movement: float,
     * }>
     */
    public function getTopMovers(
        int $limit = 10,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): Collection {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        $query = InventoryMovement::query()
            ->select([
                'inventoryable_type',
                'inventoryable_id',
                DB::raw('COUNT(*) as movement_count'),
                DB::raw('SUM(ABS(quantity)) as total_quantity_moved'),
                DB::raw('AVG(ABS(quantity)) as avg_quantity'),
            ])
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->groupBy('inventoryable_type', 'inventoryable_id')
            ->orderByDesc('movement_count')
            ->orderBy('inventoryable_type')
            ->orderBy('inventoryable_id')
            ->limit(max(0, $limit));

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($query);
        }

        return $query->toBase()->get()
            ->map(fn ($row): array => [
                'inventoryable_type' => (string) $row->inventoryable_type,
                'inventoryable_id' => (string) $row->inventoryable_id,
                'movement_count' => (int) $row->movement_count,
                'total_quantity_moved' => (int) $row->total_quantity_moved,
                'avg_quantity_per_movement' => round((float) $row->avg_quantity, 2),
            ]);
    }

    /**
     * Get slow movers (least active SKUs with stock).
     *
     * @return Collection<int, array{
     *     inventoryable_type: string,
     *     inventoryable_id: string,
     *     current_quantity: int,
     *     last_movement_at: string|null,
     *     days_since_movement: int,
     * }>
     */
    public function getSlowMovers(
        int $limit = 10,
        int $minDaysSinceMovement = 30,
    ): Collection {
        $cutoffDate = CarbonImmutable::now()->subDays($minDaysSinceMovement);
        $levelsTable = config('inventory.database.tables.levels', 'inventory_levels');

        $lastMovements = InventoryMovement::query()
            ->select([
                'inventoryable_type',
                'inventoryable_id',
                DB::raw('MAX(occurred_at) as last_occurred_at'),
            ])
            ->groupBy('inventoryable_type', 'inventoryable_id');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($lastMovements);
        }

        // One bounded query: stocked SKUs joined to their last movement,
        // keeping SKUs with stale or no movements, stalest first.
        $query = InventoryLevel::query()
            ->select([
                "{$levelsTable}.inventoryable_type",
                "{$levelsTable}.inventoryable_id",
                DB::raw("SUM({$levelsTable}.quantity_on_hand) as current_quantity"),
                'sku_last_movement.last_occurred_at',
            ])
            ->where("{$levelsTable}.quantity_on_hand", '>', 0)
            ->leftJoinSub($lastMovements, 'sku_last_movement', function (JoinClause $join) use ($levelsTable): void {
                $join->on('sku_last_movement.inventoryable_type', '=', "{$levelsTable}.inventoryable_type")
                    ->on('sku_last_movement.inventoryable_id', '=', "{$levelsTable}.inventoryable_id");
            })
            ->where(function (Builder $query) use ($cutoffDate): void {
                $query->whereNull('sku_last_movement.last_occurred_at')
                    ->orWhere('sku_last_movement.last_occurred_at', '<', $cutoffDate);
            })
            ->groupBy(
                "{$levelsTable}.inventoryable_type",
                "{$levelsTable}.inventoryable_id",
                'sku_last_movement.last_occurred_at'
            )
            ->orderByRaw('CASE WHEN sku_last_movement.last_occurred_at IS NULL THEN 0 ELSE 1 END, sku_last_movement.last_occurred_at ASC')
            ->limit(max(0, $limit));

        $query = InventoryOwnerScope::applyToLocationQuery($query);

        $now = CarbonImmutable::now();

        /** @var Collection<int, array{inventoryable_type: string, inventoryable_id: string, current_quantity: int, last_movement_at: string|null, days_since_movement: int}> $slowMovers */
        $slowMovers = $query->toBase()->get()->map(static function ($row) use ($now): array {
            $lastOccurredAt = $row->last_occurred_at;

            return [
                'inventoryable_type' => (string) $row->inventoryable_type,
                'inventoryable_id' => (string) $row->inventoryable_id,
                'current_quantity' => (int) $row->current_quantity,
                'last_movement_at' => $lastOccurredAt !== null
                    ? CarbonImmutable::parse($lastOccurredAt)->toDateTimeString()
                    : null,
                'days_since_movement' => $lastOccurredAt !== null
                    ? (int) CarbonImmutable::parse($lastOccurredAt)->diffInDays($now)
                    : 999,
            ];
        });

        return $slowMovers;
    }

    /**
     * Get movement velocity (rate of change).
     *
     * @return array{
     *     receipts_per_day: float,
     *     shipments_per_day: float,
     *     net_change_per_day: float,
     *     days_of_stock_at_current_rate: float,
     * }
     */
    public function getMovementVelocity(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): array {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();
        $days = max(1, $startDate->diffInDays($endDate));

        $movementQuery = InventoryMovement::query()
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->whereIn('type', [MovementType::Receipt->value, MovementType::Shipment->value]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($movementQuery);
        }

        $totals = $movementQuery->toBase()
            ->selectRaw('type, SUM(ABS(quantity)) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $receipts = (int) $totals->get(MovementType::Receipt->value, 0);
        $shipments = (int) $totals->get(MovementType::Shipment->value, 0);

        $currentStockQuery = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query());

        $currentStock = (int) $currentStockQuery->sum('quantity_on_hand');

        $shipmentsPerDay = $shipments / $days;
        $netChangePerDay = ($receipts - $shipments) / $days;

        return [
            'receipts_per_day' => round($receipts / $days, 2),
            'shipments_per_day' => round($shipmentsPerDay, 2),
            'net_change_per_day' => round($netChangePerDay, 2),
            'days_of_stock_at_current_rate' => $shipmentsPerDay > 0
                ? round($currentStock / $shipmentsPerDay, 1)
                : 0.0,
        ];
    }

    /**
     * Get adjustment analysis (reasons and frequency).
     *
     * @return Collection<int, array{
     *     reason: string,
     *     count: int,
     *     total_positive: int,
     *     total_negative: int,
     *     net_adjustment: int,
     * }>
     */
    public function getAdjustmentAnalysis(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): Collection {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        $query = InventoryMovement::query()
            ->where('type', MovementType::Adjustment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($query);
        }

        /** @var Collection<int, array{reason: string, count: int, total_positive: int, total_negative: int, net_adjustment: int}> $analysis */
        $delta = 'COALESCE(quantity_after - quantity_before, quantity)';
        $analysis = $query->toBase()
            ->selectRaw("COALESCE(reason, 'unknown') as reason")
            ->selectRaw('COUNT(*) as count')
            ->selectRaw("SUM(CASE WHEN {$delta} > 0 THEN {$delta} ELSE 0 END) as total_positive")
            ->selectRaw("SUM(CASE WHEN {$delta} < 0 THEN -({$delta}) ELSE 0 END) as total_negative")
            ->selectRaw("SUM({$delta}) as net_adjustment")
            ->groupByRaw("COALESCE(reason, 'unknown')")
            ->orderByDesc('count')
            ->orderBy('reason')
            ->get()
            ->map(static fn ($row): array => [
                'reason' => (string) $row->reason,
                'count' => (int) $row->count,
                'total_positive' => (int) $row->total_positive,
                'total_negative' => (int) $row->total_negative,
                'net_adjustment' => (int) $row->net_adjustment,
            ]);

        return $analysis;
    }

    private function getScopedLocationOrFail(string $locationId): InventoryLocation
    {
        $query = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query());

        $location = $query->whereKey($locationId)->first();

        if ($location === null) {
            throw new InvalidArgumentException('Invalid location for current owner');
        }

        return $location;
    }
}

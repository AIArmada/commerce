<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reporting\Queries;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Features\Valuation\Api\InventoryValues;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Calculates key inventory performance indicators.
 */
final class InventoryKpiService
{
    /**
     * Calculate inventory turnover ratio.
     * Formula: COGS / Average Inventory Value
     */
    public function calculateTurnoverRatio(
        string $inventoryableType,
        string $inventoryableId,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $startDate ??= CarbonImmutable::now()->subYear();
        $endDate ??= CarbonImmutable::now();

        $cogs = $this->calculateCostOfGoodsSold(
            $inventoryableType,
            $inventoryableId,
            $startDate,
            $endDate,
        );

        $averageInventory = $this->calculateAverageInventoryValue(
            $inventoryableType,
            $inventoryableId,
            $startDate,
            $endDate,
        );

        if ($averageInventory === 0) {
            return 0.0;
        }

        return round($cogs / $averageInventory, 2);
    }

    /**
     * Calculate days of inventory on hand.
     * Formula: (Average Inventory / COGS) × Days in Period
     */
    public function calculateDaysOnHand(
        string $inventoryableType,
        string $inventoryableId,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $startDate ??= CarbonImmutable::now()->subYear();
        $endDate ??= CarbonImmutable::now();

        $turnover = $this->calculateTurnoverRatio(
            $inventoryableType,
            $inventoryableId,
            $startDate,
            $endDate,
        );

        if ($turnover === 0.0) {
            return 0.0;
        }

        $daysInPeriod = $startDate->diffInDays($endDate);

        return round($daysInPeriod / $turnover, 1);
    }

    /**
     * Calculate order fill rate.
     * Formula: Orders Completely Filled / Total Orders × 100
     */
    public function calculateFillRate(
        string $inventoryableType,
        string $inventoryableId,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        $query = InventoryMovement::query()
            ->where('inventoryable_type', $inventoryableType)
            ->where('inventoryable_id', $inventoryableId)
            ->where('type', MovementType::Shipment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($query);
        }

        $counts = $query->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("COUNT(CASE WHEN reason IS NULL OR reason <> 'partial' THEN 1 END) AS fulfilled")
            ->first();

        $total = (int) ($counts->total ?? 0);

        return $total === 0 ? 100.0 : round(((int) ($counts->fulfilled ?? 0) / $total) * 100, 2);
    }

    /**
     * Calculate stockout rate.
     * Formula: Days Out of Stock / Total Days × 100
     */
    public function calculateStockoutRate(
        string $inventoryableType,
        string $inventoryableId,
        ?string $locationId = null,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        $totalDays = $startDate->diffInDays($endDate);
        if ($totalDays === 0) {
            return 0.0;
        }

        if ($locationId !== null) {
            $this->getScopedLocationOrFail($locationId);
        }

        // Count days where stock went to zero
        $stockoutEventsQuery = InventoryMovement::query()
            ->where('inventoryable_type', $inventoryableType)
            ->where('inventoryable_id', $inventoryableId)
            ->when($locationId, fn ($q) => $q->where('from_location_id', $locationId))
            ->where('type', MovementType::Shipment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($stockoutEventsQuery);
        }

        $stockoutEvents = $stockoutEventsQuery->count();

        return round(($stockoutEvents / $totalDays) * 100, 2);
    }

    /**
     * Calculate inventory accuracy.
     * Formula: Correct Counts / Total Counts × 100
     */
    public function calculateInventoryAccuracy(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        // Count cycle count adjustments
        $query = InventoryMovement::query()
            ->where('type', MovementType::Adjustment->value)
            ->where('reason', 'cycle_count')
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($query);
        }

        $counts = $query->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(CASE WHEN quantity = 0 THEN 1 END) AS accurate')
            ->first();

        $total = (int) ($counts->total ?? 0);

        return $total === 0 ? 100.0 : round(((int) ($counts->accurate ?? 0) / $total) * 100, 2);
    }

    /**
     * Calculate carrying cost as percentage of inventory value.
     */
    public function calculateCarryingCostRate(
        int $annualCarryingCostMinor,
        int $averageInventoryValueMinor,
    ): float {
        if ($averageInventoryValueMinor === 0) {
            return 0.0;
        }

        return round(($annualCarryingCostMinor / $averageInventoryValueMinor) * 100, 2);
    }

    /**
     * Get comprehensive KPI dashboard data. SKU counts reflect active locations;
     * each SKU is classified once using its combined sellable quantity.
     *
     * @return array{
     *     total_sku_count: int,
     *     total_inventory_value: int,
     *     average_turnover_ratio: float,
     *     average_days_on_hand: float,
     *     overall_fill_rate: float,
     *     inventory_accuracy: float,
     *     low_stock_items: int,
     *     out_of_stock_items: int,
     * }
     */
    public function getDashboardKpis(CostingMethod $method = CostingMethod::Fifo): array
    {
        $stockLevelsQuery = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->select('inventoryable_type', 'inventoryable_id')
                ->selectRaw('SUM(CASE WHEN quantity_on_hand > quantity_reserved THEN quantity_on_hand - quantity_reserved ELSE 0 END) AS available')
                ->whereHas('location', fn ($query) => $query->where('is_active', true))
                ->groupBy('inventoryable_type', 'inventoryable_id')
        );

        $threshold = (int) config('inventory.default_reorder_point', 10);
        $counts = DB::query()->fromSub($stockLevelsQuery->toBase(), 'grouped_skus')
            ->selectRaw('COUNT(*) AS total_skus')
            ->selectRaw('COUNT(CASE WHEN available > 0 AND available <= ? THEN 1 END) AS low_stock', [$threshold])
            ->selectRaw('COUNT(CASE WHEN available = 0 THEN 1 END) AS out_of_stock')
            ->first();

        $totalValue = app(InventoryValues::class)->total($method, (string) config('inventory.defaults.currency', 'MYR'));

        return [
            'total_sku_count' => (int) ($counts->total_skus ?? 0),
            'total_inventory_value' => (int) $totalValue,
            'average_turnover_ratio' => $this->calculateOverallTurnoverRatio(),
            'average_days_on_hand' => $this->calculateOverallDaysOnHand(),
            'overall_fill_rate' => $this->calculateOverallFillRate(),
            'inventory_accuracy' => $this->calculateInventoryAccuracy(),
            'low_stock_items' => (int) ($counts->low_stock ?? 0),
            'out_of_stock_items' => (int) ($counts->out_of_stock ?? 0),
        ];
    }

    /**
     * Get trend data for KPIs over time.
     *
     * @return Collection<int, array{date: string, turnover: float, fill_rate: float, accuracy: float}>
     */
    public function getKpiTrends(int $months = 6): Collection
    {
        $trends = collect();
        $endDate = CarbonImmutable::now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = $endDate->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->endOfMonth();

            $trends->push([
                'date' => $monthStart->format('Y-m'),
                'turnover' => $this->calculateOverallTurnoverRatio($monthStart, $monthEnd),
                'fill_rate' => $this->calculateOverallFillRate($monthStart, $monthEnd),
                'accuracy' => $this->calculateInventoryAccuracy($monthStart, $monthEnd),
            ]);
        }

        return $trends;
    }

    /**
     * Calculate COGS from shipment movements.
     */
    private function calculateCostOfGoodsSold(
        string $inventoryableType,
        string $inventoryableId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): int {
        // Sum quantity from shipment movements
        $query = InventoryMovement::query()
            ->where('inventoryable_type', $inventoryableType)
            ->where('inventoryable_id', $inventoryableId)
            ->where('type', MovementType::Shipment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($query);
        }

        return (int) $query->sum('quantity');
    }

    /**
     * Calculate average inventory value over a period.
     */
    private function calculateAverageInventoryValue(
        string $inventoryableType,
        string $inventoryableId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): int {
        // Get current stock level as approximation
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $inventoryableType)
                ->where('inventoryable_id', $inventoryableId)
        );

        $currentStock = (int) $query->sum('quantity_on_hand');

        return $currentStock;
    }

    /**
     * Calculate overall turnover ratio across all inventory.
     */
    private function calculateOverallTurnoverRatio(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $startDate ??= CarbonImmutable::now()->subYear();
        $endDate ??= CarbonImmutable::now();

        $totalCogsQuery = InventoryMovement::query()
            ->where('type', MovementType::Shipment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($totalCogsQuery);
        }

        $totalCogs = (int) $totalCogsQuery->sum('quantity');

        $totalInventoryQuery = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query());

        $totalInventory = (int) $totalInventoryQuery->sum('quantity_on_hand');

        if ($totalInventory === 0) {
            return 0.0;
        }

        return round($totalCogs / $totalInventory, 2);
    }

    /**
     * Calculate overall days on hand.
     */
    private function calculateOverallDaysOnHand(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $turnover = $this->calculateOverallTurnoverRatio($startDate, $endDate);

        if ($turnover === 0.0) {
            return 0.0;
        }

        return round(365 / $turnover, 1);
    }

    /**
     * Calculate overall fill rate.
     */
    private function calculateOverallFillRate(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        $query = InventoryMovement::query()
            ->where('type', MovementType::Shipment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($query);
        }

        $counts = $query->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("COUNT(CASE WHEN reason IS NULL OR reason <> 'partial' THEN 1 END) AS fulfilled")
            ->first();

        $total = (int) ($counts->total ?? 0);

        return $total === 0 ? 100.0 : round(((int) ($counts->fulfilled ?? 0) / $total) * 100, 2);
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

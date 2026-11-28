<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reporting\Queries;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Features\Stock\Api\StockKpiMetrics;
use AIArmada\Inventory\Features\Valuation\Api\InventoryValues;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Calculates key inventory performance indicators.
 */
final readonly class InventoryKpiService
{
    public function __construct(private StockKpiMetrics $stock, private InventoryValues $values) {}

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

        $counts = $this->stock->shipments($startDate, $endDate, $inventoryableType, $inventoryableId);

        return $counts['total'] === 0 ? 100.0 : round(($counts['fulfilled'] / $counts['total']) * 100, 2);

    }

    /**
     * Calculate stockout rate.
     * Measures calendar days with recorded shipment-driven depletion, not the duration of stockouts.
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

        if ($endDate->lt($startDate)) {
            return 0.0;
        }

        $totalDays = (int) $startDate->startOfDay()->diffInDays($endDate->startOfDay()) + 1;
        $days = $this->stock->depletionDays($inventoryableType, $inventoryableId, $locationId, $startDate, $endDate);

        return round(($days / $totalDays) * 100, 2);

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

        $counts = $this->stock->cycleCounts($startDate, $endDate);

        return $counts['total'] === 0 ? 100.0 : round(($counts['accurate'] / $counts['total']) * 100, 2);

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
        $counts = $this->stock->skuCounts((int) config('inventory.default_reorder_point', 10));
        $totalValue = $this->values->total($method, (string) config('inventory.defaults.currency', 'MYR'));
        $turnover = $this->calculateOverallTurnoverRatio();

        return [
            'total_sku_count' => $counts['total_skus'],
            'total_inventory_value' => (int) $totalValue,
            'average_turnover_ratio' => $turnover,
            'average_days_on_hand' => $turnover === 0.0 ? 0.0 : round(365 / $turnover, 1),
            'overall_fill_rate' => $this->calculateOverallFillRate(),
            'inventory_accuracy' => $this->calculateInventoryAccuracy(),
            'low_stock_items' => $counts['low_stock'],
            'out_of_stock_items' => $counts['out_of_stock'],
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
        return $this->stock->shipments($startDate, $endDate, $inventoryableType, $inventoryableId)['quantity'];

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
        return $this->stock->onHand($inventoryableType, $inventoryableId);

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

        $totalInventory = $this->stock->onHand();

        if ($totalInventory === 0) {
            return 0.0;
        }

        return round($this->stock->shipments($startDate, $endDate)['quantity'] / $totalInventory, 2);

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

        $counts = $this->stock->shipments($startDate, $endDate);

        return $counts['total'] === 0 ? 100.0 : round(($counts['fulfilled'] / $counts['total']) * 100, 2);

    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Services;

use AIArmada\FilamentInventory\Persistence\InventoryStatsCache;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Reports\MovementAnalysisReport;
use AIArmada\Inventory\Reports\StockLevelReport;
use Illuminate\Database\Eloquent\Builder;

final class InventoryStatsAggregator
{
    public function __construct(
        private readonly StockLevelReport $stockLevelReport,
        private readonly MovementAnalysisReport $movementAnalysisReport,
    ) {}

    /**
     * Get overview statistics from the domain reporting service.
     *
     * @return array{total_locations: int, active_locations: int, total_skus: int, total_on_hand: int, total_reserved: int, active_allocations: int}
     */
    public function overview(): array
    {
        return $this->stockLevelReport->getOverview();
    }

    /**
     * Get movement statistics from the domain reporting service.
     *
     * @return array{receipts: int, shipments: int, transfers: int, adjustments: int, total: int}
     */
    public function movementStats(int $days = 30): array
    {
        return $this->movementAnalysisReport->getStats($days);
    }

    public function lowInventoryCount(?int $threshold = null): int
    {
        return $this->stockLevelReport->getLowInventoryCount($threshold);
    }

    public function outOfStockCount(): int
    {
        return $this->stockLevelReport->getOutOfStockCount();
    }

    /**
     * Get overview stats for the widget.
     *
     * @return array{active_locations: int, total_skus: int, total_on_hand: int, total_reserved: int, total_available: int, low_stock_count: int}
     */
    public function getOverviewStats(): array
    {
        return app(InventoryStatsCache::class)->remember('overview_stats', function (): array {
            $overview = $this->stockLevelReport->getOverview();

            return [
                'active_locations' => $overview['active_locations'],
                'total_skus' => $overview['total_skus'],
                'total_on_hand' => $overview['total_on_hand'],
                'total_reserved' => $overview['total_reserved'],
                'total_available' => $overview['total_on_hand'] - $overview['total_reserved'],
                'low_stock_count' => $this->stockLevelReport->getLowStockCount(),
            ];
        });
    }

    public function lowStockCount(): int
    {
        return app(InventoryStatsCache::class)->remember('low_stock_count', fn (): int => $this->stockLevelReport->getLowStockCount());
    }

    /**
     * Get low stock query for the widget table.
     *
     * @return Builder<InventoryLevel>
     */
    public function getLowStockQuery(): Builder
    {
        return $this->stockLevelReport->getLowStockQuery();
    }

    public function clearCache(): void
    {
        app(InventoryStatsCache::class)->clearCache();
    }
}

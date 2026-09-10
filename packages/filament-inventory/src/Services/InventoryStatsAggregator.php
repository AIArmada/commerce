<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Services;

use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Reports\MovementAnalysisReport;
use AIArmada\Inventory\Reports\StockLevelReport;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final class InventoryStatsAggregator
{
    private const CACHE_TTL_SECONDS = 60;

    private const CACHE_PREFIX = 'inventory_stats_';

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
        return $this->cached('overview_stats', function (): array {
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
        return $this->cached('low_stock_count', fn (): int => $this->stockLevelReport->getLowStockCount());
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
        $suffix = InventoryOwnerScope::cacheKeySuffix();

        Cache::forget(self::CACHE_PREFIX . 'overview_stats|' . $suffix);
        Cache::forget(self::CACHE_PREFIX . 'low_stock_count|' . $suffix);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function cached(string $key, Closure $callback): mixed
    {
        $ttl = config('filament-inventory.cache.stats_ttl', self::CACHE_TTL_SECONDS);

        if ($ttl <= 0) {
            return $callback();
        }

        /** @var T $cached */
        $cached = Cache::remember(
            self::CACHE_PREFIX . $key . '|' . InventoryOwnerScope::cacheKeySuffix(),
            $ttl,
            $callback,
        );

        return $cached;
    }
}

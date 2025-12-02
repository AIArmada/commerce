<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Services;

use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use Illuminate\Support\Facades\Cache;

final class InventoryStatsAggregator
{
    private const CACHE_TTL_SECONDS = 60;

    private const CACHE_PREFIX = 'inventory_stats_';

    /**
     * Get overview statistics.
     *
     * @return array{total_locations: int, active_locations: int, total_skus: int, total_on_hand: int, total_reserved: int, active_allocations: int}
     */
    public function overview(): array
    {
        return [
            'total_locations' => InventoryLocation::count(),
            'active_locations' => InventoryLocation::active()->count(),
            'total_skus' => $this->countDistinctSkus(),
            'total_on_hand' => (int) InventoryLevel::sum('quantity_on_hand'),
            'total_reserved' => (int) InventoryLevel::sum('quantity_reserved'),
            'active_allocations' => InventoryAllocation::active()->count(),
        ];
    }

    /**
     * Get movement statistics for a period.
     *
     * @return array{receipts: int, shipments: int, transfers: int, adjustments: int, total: int}
     */
    public function movementStats(int $days = 30): array
    {
        $since = now()->subDays($days);

        $movements = InventoryMovement::query()
            ->where('occurred_at', '>=', $since)
            ->selectRaw('type, SUM(quantity) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        return [
            'receipts' => (int) ($movements['receipt'] ?? 0),
            'shipments' => (int) ($movements['shipment'] ?? 0),
            'transfers' => (int) ($movements['transfer'] ?? 0),
            'adjustments' => (int) ($movements['adjustment'] ?? 0),
            'total' => InventoryMovement::where('occurred_at', '>=', $since)->count(),
        ];
    }

    /**
     * Get low inventory items count.
     */
    public function lowInventoryCount(?int $threshold = null): int
    {
        $threshold ??= config('inventory.default_reorder_point', 10);

        return InventoryLevel::query()
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= ?', [$threshold])
            ->whereHas('location', fn ($q) => $q->where('is_active', true))
            ->count();
    }

    /**
     * Get out of stock items count.
     */
    public function outOfStockCount(): int
    {
        return InventoryLevel::query()
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= 0')
            ->whereHas('location', fn ($q) => $q->where('is_active', true))
            ->count();
    }

    /**
     * Get overview stats for the widget.
     *
     * @return array{active_locations: int, total_skus: int, total_on_hand: int, total_reserved: int, total_available: int, low_stock_count: int}
     */
    public function getOverviewStats(): array
    {
        return $this->cached('overview_stats', function (): array {
            $totalOnHand = (int) InventoryLevel::sum('quantity_on_hand');
            $totalReserved = (int) InventoryLevel::sum('quantity_reserved');

            return [
                'active_locations' => InventoryLocation::active()->count(),
                'total_skus' => $this->countDistinctSkus(),
                'total_on_hand' => $totalOnHand,
                'total_reserved' => $totalReserved,
                'total_available' => $totalOnHand - $totalReserved,
                'low_stock_count' => $this->lowStockCount(),
            ];
        });
    }

    /**
     * Get low stock count based on reorder points.
     */
    public function lowStockCount(): int
    {
        return $this->cached('low_stock_count', fn (): int => InventoryLevel::query()
            ->whereRaw('quantity_on_hand - quantity_reserved <= reorder_point')
            ->where('reorder_point', '>', 0)
            ->count());
    }

    /**
     * Get low stock query for the widget table.
     *
     * @return \Illuminate\Database\Eloquent\Builder<InventoryLevel>
     */
    public function getLowStockQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return InventoryLevel::query()
            ->with('location')
            ->whereRaw('quantity_on_hand - quantity_reserved <= reorder_point')
            ->where('reorder_point', '>', 0)
            ->orderByRaw('reorder_point - (quantity_on_hand - quantity_reserved) DESC');
    }

    /**
     * Clear all cached inventory stats.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'overview_stats');
        Cache::forget(self::CACHE_PREFIX . 'low_stock_count');
    }

    /**
     * Count distinct SKUs (unique inventoryable_type + inventoryable_id combinations).
     * Uses a subquery approach for SQLite compatibility.
     */
    private function countDistinctSkus(): int
    {
        return (int) InventoryLevel::query()
            ->selectRaw('COUNT(*) as count')
            ->fromSub(
                InventoryLevel::query()
                    ->select('inventoryable_type', 'inventoryable_id')
                    ->distinct(),
                'distinct_skus'
            )
            ->value('count');
    }

    /**
     * Cache helper for stats queries.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function cached(string $key, callable $callback): mixed
    {
        $ttl = config('filament-inventory.cache.stats_ttl', self::CACHE_TTL_SECONDS);

        if ($ttl <= 0) {
            return $callback();
        }

        return Cache::remember(
            self::CACHE_PREFIX . $key,
            $ttl,
            $callback
        );
    }
}

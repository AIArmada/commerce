<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Persistence;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Closure;
use Illuminate\Support\Facades\Cache;

final readonly class InventoryStatsCache
{
    private const CACHE_TTL_SECONDS = 60;

    private const CACHE_PREFIX = 'inventory_stats_';

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
    public function remember(string $key, Closure $callback): mixed
    {
        if (InventoryOwnerScope::isEnabled()) {
            OwnerContext::assertResolvedOrExplicitGlobal(InventoryOwnerScope::resolveOwner());
        }

        $ttl = config('filament-inventory.cache.stats_ttl', self::CACHE_TTL_SECONDS);

        if ($ttl <= 0) {
            return $callback();
        }

        /** @var T $cached */
        $cached = Cache::flexible(
            self::CACHE_PREFIX . $key . '|' . InventoryOwnerScope::cacheKeySuffix(),
            [$ttl, $ttl],
            $callback,
        );

        return $cached;
    }
}

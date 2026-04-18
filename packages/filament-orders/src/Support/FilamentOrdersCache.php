<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Support;

use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

final class FilamentOrdersCache
{
    public static function forgetForOrder(Order $order): void
    {
        $ownerKey = self::ownerKeyFromOrder($order);

        foreach (['owner-only', 'with-global'] as $scopeKey) {
            Cache::forget(sprintf('filament-orders.status-distribution.%s.%s', $ownerKey, $scopeKey));

            $now = CarbonImmutable::now();
            $today = $now->toDateString();
            $yesterday = $now->subDay()->toDateString();

            Cache::forget(sprintf('filament-orders.stats.%s.%s.%s', $ownerKey, $scopeKey, $today));
            Cache::forget(sprintf('filament-orders.stats.%s.%s.%s', $ownerKey, $scopeKey, $yesterday));
        }

        // NOTE: If the order is a global record (owner=null) and include-global is enabled,
        // this cannot invalidate every tenant's cache key without enumerating owners.
        // Short TTLs on these caches still guarantee eventual consistency.
    }

    private static function ownerKeyFromOrder(Order $order): string
    {
        /** @var string|null $ownerType */
        $ownerType = $order->getAttribute('owner_type');

        /** @var string|int|null $ownerId */
        $ownerId = $order->getAttribute('owner_id');

        if ($ownerType === null && $ownerId === null) {
            return 'global';
        }

        if ($ownerType === null || $ownerId === null || $ownerType === '' || (is_string($ownerId) && $ownerId === '')) {
            throw new InvalidArgumentException('Order owner type and owner id must be either both null or both non-empty.');
        }

        return $ownerType . ':' . (string) $ownerId;
    }
}

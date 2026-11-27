<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\WebhookDelivery\Persistence;

use AIArmada\Chip\Features\WebhookDelivery\Api\PublicKeyCache;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;

final class CachePublicKey implements PublicKeyCache
{
    /** @param Closure(): string $fetch */
    public function remember(string $cacheKey, Closure $fetch): string
    {
        $ttl = config('chip.cache.ttl.public_key', 86400);
        $store = Cache::getStore();

        if (! $store instanceof LockProvider) {
            return Cache::remember($cacheKey, $ttl, $fetch);
        }

        return Cache::lock($cacheKey . ':lock', 10)->block(5, static fn (): string => Cache::remember($cacheKey, $ttl, $fetch));
    }
}

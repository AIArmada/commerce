<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Persistence;

use AIArmada\CommerceSupport\Persistence\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Features\Tracking\Api\TrackingPollCache;

final readonly class TrackingPollCacheStore implements TrackingPollCache
{
    public function get(string $cacheKey): ?array
    {
        $cached = OwnerCache::get(OwnerContext::resolve(), $cacheKey);

        return is_array($cached) ? $cached : null;
    }

    public function put(string $cacheKey, array $payload, int $ttlSeconds): void
    {
        OwnerCache::put(OwnerContext::resolve(), $cacheKey, $payload, $ttlSeconds);
    }
}

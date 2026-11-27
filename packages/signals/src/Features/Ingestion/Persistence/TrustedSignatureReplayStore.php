<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Ingestion\Persistence;

use Illuminate\Support\Facades\Cache;

final readonly class TrustedSignatureReplayStore
{
    public function reserve(string $timestamp, string $signature, int $ttlSeconds): bool
    {
        $key = 'signals:trusted:signature:' . hash('sha256', $timestamp . ':' . $signature);
        $store = config('cache.limiter');

        return Cache::store(is_string($store) ? $store : null)->add($key, true, $ttlSeconds);
    }
}

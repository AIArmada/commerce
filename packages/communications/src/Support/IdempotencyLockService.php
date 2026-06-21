<?php

declare(strict_types=1);

namespace AIArmada\Communications\Support;

use AIArmada\Communications\Contracts\IdempotencyLock;
use Illuminate\Support\Facades\Cache;

class IdempotencyLockService implements IdempotencyLock
{
    public function acquire(string $key, int $ttlSeconds): bool
    {
        $store = Cache::store(config('communications.cache.idempotency_store', config('cache.default', 'array')));

        return $store->add("communications:idempotency:{$key}", true, $ttlSeconds);
    }

    public function release(string $key): void
    {
        Cache::store(config('communications.cache.idempotency_store', config('cache.default', 'array')))
            ->forget("communications:idempotency:{$key}");
    }

    public function exists(string $key): bool
    {
        return Cache::store(config('communications.cache.idempotency_store', config('cache.default', 'array')))
            ->has("communications:idempotency:{$key}");
    }
}

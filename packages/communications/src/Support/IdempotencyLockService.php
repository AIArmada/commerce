<?php

declare(strict_types=1);

namespace AIArmada\Communications\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\Communications\Contracts\IdempotencyLock;
use AIArmada\Communications\Models\Communication;
use Illuminate\Support\Facades\Cache;

class IdempotencyLockService implements IdempotencyLock
{
    public function acquire(string $key, int $ttlSeconds): bool
    {
        $store = Cache::store(config('communications.cache.idempotency_store', config('cache.default', 'array')));

        return $store->add($this->cacheKey($key), true, $ttlSeconds);
    }

    public function release(string $key): void
    {
        Cache::store(config('communications.cache.idempotency_store', config('cache.default', 'array')))
            ->forget($this->cacheKey($key));
    }

    public function exists(string $key): bool
    {
        return Cache::store(config('communications.cache.idempotency_store', config('cache.default', 'array')))
            ->has($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        $config = Communication::ownerScopeConfig();

        if (! $config->enabled) {
            return "communications:idempotency:{$key}";
        }

        $scope = OwnerScopeKey::forOwner(OwnerContext::resolveConfigured($config));

        return "communications:idempotency:{$scope}:{$key}";
    }
}

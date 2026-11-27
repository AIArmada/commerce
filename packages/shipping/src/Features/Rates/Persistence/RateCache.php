<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Rates\Persistence;

use AIArmada\Shipping\Data\RateQuoteData;
use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class RateCache
{
    /** @var array<string, array<string, true>> */
    private array $keys = [];

    /**
     * @param  list<string>  $tags
     * @param  Closure(): Collection<int, RateQuoteData>  $load
     * @return Collection<int, RateQuoteData>
     */
    public function remember(array $tags, string $key, int $ttl, Closure $load): Collection
    {
        $repository = Cache::store();

        if ($repository->getStore() instanceof TaggableStore) {
            return $repository->tags($tags)->remember($key, $ttl, $load);
        }

        $this->keys[implode('|', $tags)][$key] = true;

        return $repository->remember($key, $ttl, $load);
    }

    /** @param list<string> $tags */
    public function clear(array $tags): void
    {
        $repository = Cache::store();

        if ($repository->getStore() instanceof TaggableStore) {
            $repository->tags($tags)->flush();

            return;
        }

        $scope = implode('|', $tags);

        foreach (array_keys($this->keys[$scope] ?? []) as $key) {
            $repository->forget($key);
        }

        unset($this->keys[$scope]);
    }

    public function forgetFailure(string $key): void
    {
        Cache::store()->forget($key);
    }

    public function recordFailure(string $key, int $ttl): void
    {
        $repository = Cache::store();
        $repository->put($key, (int) $repository->get($key, 0) + 1, $ttl);
    }

    public function failures(string $key): int
    {
        return (int) Cache::store()->get($key, 0);
    }
}

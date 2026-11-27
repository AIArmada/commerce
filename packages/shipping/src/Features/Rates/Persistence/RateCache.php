<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Rates\Persistence;

use AIArmada\Shipping\Data\RateQuoteData;
use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class RateCache
{
    private const int GENERATION_TTL = 86400;

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

        $generationKey = $this->generationKey($tags);
        $generation = $repository->get($generationKey);

        if (! is_string($generation)) {
            $repository->add($generationKey, (string) Str::uuid(), self::GENERATION_TTL);
            $generation = $repository->get($generationKey);
        }

        if (! is_string($generation)) {
            return $load();
        }

        return $repository->remember($key . ':' . $generation, $ttl, $load);
    }

    /** @param list<string> $tags */
    public function clear(array $tags): void
    {
        $repository = Cache::store();

        if ($repository->getStore() instanceof TaggableStore) {
            $repository->tags($tags)->flush();

            return;
        }

        $repository->put($this->generationKey($tags), (string) Str::uuid(), self::GENERATION_TTL);
    }

    /** @param list<string> $tags */
    private function generationKey(array $tags): string
    {
        return 'shipping:rates:generation:' . hash('sha256', implode('|', $tags));
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

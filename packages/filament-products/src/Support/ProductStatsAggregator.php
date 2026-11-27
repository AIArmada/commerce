<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Support;

use AIArmada\CommerceSupport\Persistence\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Queries\CatalogStatistics;

final readonly class ProductStatsAggregator
{
    /** @return array<string, int|float> */
    public function getStats(): array
    {
        $owner = OwnerContext::resolve();
        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            'Product statistics require an owner context or explicit global context.',
        );

        return OwnerCache::remember($owner, ProductStatsCache::KEY, 60, fn (): array => (new CatalogStatistics)->execute());
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Queries;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentProducts\Persistence\ProductStatsCache;
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

        return ProductStatsCache::remember($owner, fn (): array => (new CatalogStatistics)->execute());
    }
}

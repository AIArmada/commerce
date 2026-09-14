<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Unit\Services;

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Services\RateShoppingEngine;
use AIArmada\Shipping\ShippingManager;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Str;
use Mockery;

function rateCacheAddresses(): array
{
    // Unique per run: the file store persists across suite runs, so a fixed
    // payload would hit leftovers cached by previous runs.
    $suffix = Str::lower(Str::random(12));

    $origin = new AddressData(
        name: 'Cache Origin ' . $suffix,
        phone: '+60123456789',
        line1: '1 Cache Road',
        postcode: '50000',
        country: 'MY',
    );

    $destination = new AddressData(
        name: 'Cache Dest',
        phone: '+60198765432',
        line1: '2 Cache Lane',
        postcode: '40000',
        country: 'MY',
    );

    return [$origin, $destination, [new PackageData(weight: 1000)]];
}

describe('Rate cache invalidation on non-taggable stores', function (): void {
    it('forgets instance-cached rates when tags are unavailable', function (): void {
        config(['cache.default' => 'file']);

        $repository = Cache::store();

        expect($repository->getStore())->not->toBeInstanceOf(TaggableStore::class);

        [$origin, $destination, $packages] = rateCacheAddresses();

        $rates = collect([
            new RateQuoteData(carrier: 'manual', service: 'standard', rate: 500, currency: 'MYR', estimatedDays: 3),
        ]);

        $shippingManager = Mockery::mock(ShippingManager::class);
        $shippingManager->shouldReceive('getAvailableDrivers')->andReturn(['manual']);

        Concurrency::shouldReceive('run')
            ->twice()
            ->andReturn(['manual' => $rates]);

        $engine = new RateShoppingEngine($shippingManager, ['cache_ttl' => 300]);

        $first = $engine->getAllRates($origin, $destination, $packages);

        expect($first)->toHaveCount(1);

        $engine->clearCache();

        $second = $engine->getAllRates($origin, $destination, $packages);

        expect($second)->toHaveCount(1);

        $engine->clearCache();
    });
});

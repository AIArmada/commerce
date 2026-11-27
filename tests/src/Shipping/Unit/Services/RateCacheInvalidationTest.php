<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Unit\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Features\Rates\Queries\RateShoppingEngine;
use AIArmada\Shipping\ShippingManager;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Str;
use Mockery;
use ReflectionMethod;

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
    it('invalidates cached rates across instances when tags are unavailable', function (): void {
        config(['cache.default' => 'file', 'shipping.features.owner.enabled' => false]);

        $repository = Cache::store();

        expect($repository->getStore())->not->toBeInstanceOf(TaggableStore::class);

        [$origin, $destination, $packages] = rateCacheAddresses();

        $rates = [
            new RateQuoteData(carrier: 'manual', service: 'standard', rate: 500, currency: 'MYR', estimatedDays: 3),
        ];

        $shippingManager = Mockery::mock(ShippingManager::class);
        $shippingManager->shouldReceive('getAvailableDrivers')->andReturn(['manual']);

        $updatedRates = [
            new RateQuoteData(carrier: 'manual', service: 'standard', rate: 700, currency: 'MYR', estimatedDays: 3),
        ];

        Concurrency::shouldReceive('run')
            ->twice()
            ->andReturn(['manual' => $rates], ['manual' => $updatedRates]);

        $engine = new RateShoppingEngine($shippingManager, ['cache_ttl' => 300]);

        $first = $engine->getAllRates($origin, $destination, $packages);

        $otherEngine = new RateShoppingEngine($shippingManager, ['cache_ttl' => 300]);

        expect($first->first()->rate)->toBe(500)
            ->and($otherEngine->getAllRates($origin, $destination, $packages)->first()->rate)->toBe(500);

        $otherEngine->clearCache();

        expect($engine->getAllRates($origin, $destination, $packages)->first()->rate)->toBe(700)
            ->and($otherEngine->getAllRates($origin, $destination, $packages)->first()->rate)->toBe(700);

        $engine->clearCache();
    });
});

it('separates rate cache keys by effective global visibility', function (): void {
    config(['shipping.features.owner.enabled' => true, 'shipping.features.owner.include_global' => true]);

    [$origin, $destination, $packages] = rateCacheAddresses();
    $engine = new RateShoppingEngine(Mockery::mock(ShippingManager::class));
    $method = new ReflectionMethod($engine, 'buildCacheKey');

    OwnerContext::withOwner(null, function () use ($method, $engine, $origin, $destination, $packages): void {
        $inclusive = $method->invoke($engine, $origin, $destination, $packages);
        $strict = OwnerScopeOverride::withoutIncludeGlobal(
            fn () => $method->invoke($engine, $origin, $destination, $packages),
        );

        expect($inclusive)->not->toBe($strict);
    });
});

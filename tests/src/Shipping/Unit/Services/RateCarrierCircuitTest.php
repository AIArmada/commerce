<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Unit\Services;

use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Features\Rates\Queries\RateShoppingEngine;
use AIArmada\Shipping\ShippingManager;
use Illuminate\Support\Facades\Concurrency;
use Mockery;
use RuntimeException;

function circuitTestAddresses(): array
{
    $address = new AddressData(
        name: 'Circuit Tester',
        phone: '+60123456789',
        line1: '1 Circuit Road',
        postcode: '50000',
        country: 'MY',
    );

    return [$address, $address, [new PackageData(weight: 1000)]];
}

describe('Rate carrier circuit breaker and fan-out timeout', function (): void {
    beforeEach(function (): void {
        config(['shipping.features.owner.enabled' => false]);
    });

    it('skips carriers past the consecutive failure threshold', function (): void {
        [$origin, $destination, $packages] = circuitTestAddresses();

        $driver = Mockery::mock(ShippingDriverInterface::class);
        $driver->shouldReceive('servicesDestination')->andReturn(true);
        $driver->shouldReceive('getRates')->twice()->andThrow(new RuntimeException('Carrier down'));

        $shippingManager = Mockery::mock(ShippingManager::class);
        $shippingManager->shouldReceive('hasDriver')->with('flaky')->andReturn(true);
        $shippingManager->shouldReceive('driver')->with('flaky')->andReturn($driver);

        $engine = new RateShoppingEngine($shippingManager, [
            'cache_ttl' => 0,
            'circuit_failure_threshold' => 2,
            'circuit_cooldown_seconds' => 300,
        ]);

        expect($engine->getRatesFromCarriers(['flaky'], $origin, $destination, $packages))->toBeEmpty();
        expect($engine->getRatesFromCarriers(['flaky'], $origin, $destination, $packages))->toBeEmpty();

        // Third call skips the tripped carrier without invoking the driver again.
        expect($engine->getRatesFromCarriers(['flaky'], $origin, $destination, $packages))->toBeEmpty();
    });

    it('isolates driver initialization and destination failures from healthy carriers', function (): void {
        [$origin, $destination, $packages] = circuitTestAddresses();

        $unavailable = Mockery::mock(ShippingDriverInterface::class);
        $unavailable->shouldReceive('servicesDestination')->once()->andThrow(new RuntimeException('Destination lookup failed'));
        $unavailable->shouldNotReceive('getRates');

        $healthy = Mockery::mock(ShippingDriverInterface::class);
        $healthy->shouldReceive('servicesDestination')->twice()->andReturnTrue();
        $quote = new RateQuoteData(carrier: 'healthy', service: 'standard', rate: 500, currency: 'MYR', estimatedDays: 2);
        $healthy->shouldReceive('getRates')->twice()->andReturn([$quote]);

        $manager = Mockery::mock(ShippingManager::class);
        $manager->shouldReceive('hasDriver')->andReturnTrue();
        $manager->shouldReceive('driver')->with('broken-init')->once()->andThrow(new RuntimeException('Driver initialization failed'));
        $manager->shouldReceive('driver')->with('broken-destination')->once()->andReturn($unavailable);
        $manager->shouldReceive('driver')->with('healthy')->twice()->andReturn($healthy);

        $engine = new RateShoppingEngine($manager, [
            'cache_ttl' => 0,
            'circuit_failure_threshold' => 1,
        ]);
        $carriers = ['broken-init', 'broken-destination', 'healthy'];

        expect($engine->getRatesFromCarriers($carriers, $origin, $destination, $packages)->values()->all())->toBe([$quote])
            ->and($engine->getRatesFromCarriers($carriers, $origin, $destination, $packages)->values()->all())->toBe([$quote]);
    });

    it('returns no fallback quote if the manual driver is absent', function (): void {
        [$origin, $destination, $packages] = circuitTestAddresses();
        $manager = Mockery::mock(ShippingManager::class);
        $manager->shouldReceive('getAvailableDrivers')->once()->andReturn([]);
        $manager->shouldReceive('hasDriver')->with('manual')->once()->andReturnFalse();
        $manager->shouldNotReceive('driver');

        $engine = new RateShoppingEngine($manager, ['cache_ttl' => 0]);

        expect($engine->getBestRate($origin, $destination, $packages))->toBeNull();
    });

    it('passes the configured timeout to concurrent carrier fan-out', function (): void {
        [$origin, $destination, $packages] = circuitTestAddresses();

        $rates = [
            new RateQuoteData(carrier: 'fedex', service: 'ground', rate: 1000, currency: 'USD', estimatedDays: 3),
        ];

        $seenTimeout = null;

        Concurrency::shouldReceive('run')
            ->once()
            ->withArgs(function ($tasks, $timeout = null) use (&$seenTimeout): bool {
                $seenTimeout = $timeout;

                return true;
            })
            ->andReturn(['fedex' => $rates]);

        $shippingManager = Mockery::mock(ShippingManager::class);
        $shippingManager->shouldReceive('getAvailableDrivers')->andReturn(['fedex']);

        $engine = new RateShoppingEngine($shippingManager, [
            'cache_ttl' => 0,
            'concurrency_timeout' => 15,
        ]);

        expect($engine->getAllRates($origin, $destination, $packages))->toHaveCount(1)
            ->and($seenTimeout)->toBe(15);
    });
});

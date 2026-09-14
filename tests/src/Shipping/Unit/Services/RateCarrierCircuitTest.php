<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Unit\Services;

use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Services\RateShoppingEngine;
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

    it('passes the configured timeout to concurrent carrier fan-out', function (): void {
        [$origin, $destination, $packages] = circuitTestAddresses();

        $rates = collect([
            new RateQuoteData(carrier: 'fedex', service: 'ground', rate: 1000, currency: 'USD', estimatedDays: 3),
        ]);

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

<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Exceptions\Gateway\GatewayNotFoundException;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('GatewayManager - Additional Coverage', function (): void {
    describe('dynamic method calls', function (): void {
        it('proxies method calls to default driver', function (): void {
            $manager = app(GatewayManager::class);

            // name() should be proxied to the default driver
            $name = $manager->name();

            expect($name)->toBe('stripe');
        });
    });

    /* getDefaultDriver/gateway(list)/supportedGateways/supports-true removed; covered by GatewayManagerTest. */

    describe('supportsGateway', function (): void {
        it('returns false for unconfigured gateways', function (): void {
            $manager = app(GatewayManager::class);

            expect($manager->supportsGateway('paypal'))->toBeFalse()
                ->and($manager->supportsGateway('braintree'))->toBeFalse();
        });
    });

    describe('getGatewayConfig', function (): void {
        it('returns configuration array for gateway', function (): void {
            $manager = app(GatewayManager::class);
            $config = $manager->getGatewayConfig('stripe');

            expect($config)->toBeArray()
                ->and($config)->toHaveKey('driver')
                ->and($config)->toHaveKey('secret')
                ->and($config)->toHaveKey('webhook_secret');
        });

        it('returns empty array for unconfigured gateway', function (): void {
            $manager = app(GatewayManager::class);
            $config = $manager->getGatewayConfig('nonexistent');

            expect($config)->toBeArray()
                ->and($config)->toBeEmpty();
        });
    });

    describe('driver', function (): void {
        it('returns same instance for same driver', function (): void {
            $manager = app(GatewayManager::class);

            $driver1 = $manager->driver('stripe');
            $driver2 = $manager->driver('stripe');

            expect($driver1)->toBe($driver2);
        });

        it('can forget resolved drivers between long-lived requests', function (): void {
            $manager = app(GatewayManager::class);

            $manager->driver('stripe');
            expect($manager->getDrivers())->toHaveKey('stripe');

            $manager->forgetDrivers();

            expect($manager->getDrivers())->toBeEmpty();
        });
    });

    /* extend method_exists removed; GatewayManagerTest extends + resolves a custom gateway. */

    describe('buildGateway exception', function (): void {
        it('throws GatewayNotFoundException when gateway class does not exist', function (): void {
            $manager = app(GatewayManager::class);

            // Use reflection to test the protected buildGateway method
            $reflection = new ReflectionClass($manager);
            $method = $reflection->getMethod('buildGateway');

            expect(fn () => $method->invoke($manager, 'test', 'NonExistent\\Gateway\\Class', []))
                ->toThrow(GatewayNotFoundException::class, 'Gateway class [NonExistent\\Gateway\\Class] not found');
        });
    });
});

<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('Cashier Facade', function (): void {
    it('resolves to GatewayManager', function (): void {
        expect(Cashier::getFacadeRoot())->toBeInstanceOf(GatewayManager::class);
    });

    it('proxies gateway method to manager', function (): void {
        $gateway = Cashier::gateway('stripe');

        expect($gateway)->toBeInstanceOf(GatewayContract::class)
            ->and($gateway->name())->toBe('stripe');
    });

    it('proxies getDefaultDriver method', function (): void {
        $default = Cashier::getDefaultDriver();

        expect($default)->toBe('stripe');
    });

    it('proxies supportedGateways method', function (): void {
        $gateways = Cashier::supportedGateways();

        expect($gateways)->toContain('stripe')
            ->and($gateways)->toContain('chip');
    });

    it('proxies supportsGateway method', function (): void {
        expect(Cashier::supportsGateway('stripe'))->toBeTrue()
            ->and(Cashier::supportsGateway('chip'))->toBeTrue()
            ->and(Cashier::supportsGateway('paypal'))->toBeFalse();
    });

    it('proxies getGatewayConfig method', function (): void {
        $config = Cashier::getGatewayConfig('stripe');

        expect($config)->toBeArray()
            ->and($config)->toHaveKey('driver')
            ->and($config['driver'])->toBe('stripe');
    });
});

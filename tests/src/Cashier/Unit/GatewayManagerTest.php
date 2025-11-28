<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Exceptions\GatewayNotFoundException;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\Cashier\Gateways\StripeGateway;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('GatewayManager', function () {
    it('can get the default gateway', function () {
        $gateway = $this->gatewayManager->gateway();

        expect($gateway)->toBeInstanceOf(GatewayContract::class);
    });

    it('can get stripe gateway by name', function () {
        $gateway = $this->gatewayManager->gateway('stripe');

        expect($gateway)->toBeInstanceOf(StripeGateway::class)
            ->and($gateway->name())->toBe('stripe');
    });

    it('can get chip gateway by name', function () {
        $gateway = $this->gatewayManager->gateway('chip');

        expect($gateway)->toBeInstanceOf(ChipGateway::class)
            ->and($gateway->name())->toBe('chip');
    });

    it('returns the default driver name', function () {
        $driver = $this->gatewayManager->getDefaultDriver();

        expect($driver)->toBe('stripe');
    });

    it('can list supported gateways', function () {
        $gateways = $this->gatewayManager->supportedGateways();

        expect($gateways)->toBeArray()
            ->and($gateways)->toContain('stripe')
            ->and($gateways)->toContain('chip');
    });

    it('can check if a gateway is supported', function () {
        expect($this->gatewayManager->supportsGateway('stripe'))->toBeTrue()
            ->and($this->gatewayManager->supportsGateway('chip'))->toBeTrue()
            ->and($this->gatewayManager->supportsGateway('unknown'))->toBeFalse();
    });

    it('can get gateway configuration', function () {
        $config = $this->gatewayManager->getGatewayConfig('stripe');

        expect($config)->toBeArray()
            ->and($config)->toHaveKey('key')
            ->and($config)->toHaveKey('secret');
    });

    it('throws exception for unknown gateway', function () {
        $this->gatewayManager->gateway('unknown');
    })->throws(InvalidArgumentException::class);

    it('can extend with custom gateway', function () {
        $this->gatewayManager->extend('custom', function ($app) {
            return new class extends StripeGateway {
                public function name(): string
                {
                    return 'custom';
                }
            };
        });

        $gateway = $this->gatewayManager->gateway('custom');

        expect($gateway->name())->toBe('custom');
    });

    it('caches gateway instances', function () {
        $gateway1 = $this->gatewayManager->gateway('stripe');
        $gateway2 = $this->gatewayManager->gateway('stripe');

        expect($gateway1)->toBe($gateway2);
    });
});

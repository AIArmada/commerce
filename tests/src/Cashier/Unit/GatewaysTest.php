<?php

declare(strict_types=1);

use AIArmada\Cashier\Gateways\AbstractGateway;
use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\Cashier\Gateways\StripeGateway;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('Gateways', function () {
    describe('AbstractGateway', function () {
        it('is an abstract class implementing GatewayContract', function () {
            $reflection = new ReflectionClass(AbstractGateway::class);

            expect($reflection->isAbstract())->toBeTrue()
                ->and($reflection->implementsInterface(\AIArmada\Cashier\Contracts\GatewayContract::class))->toBeTrue();
        });

        it('defines name as abstract method', function () {
            $reflection = new ReflectionClass(AbstractGateway::class);
            $method = $reflection->getMethod('name');

            expect($method->isAbstract())->toBeTrue();
        });

        it('provides currency method', function () {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway->currency())->toBe('USD');
        });
    });

    describe('StripeGateway', function () {
        it('returns correct name', function () {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway->name())->toBe('stripe');
        });

        it('extends AbstractGateway', function () {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway)->toBeInstanceOf(AbstractGateway::class);
        });

        it('implements GatewayContract', function () {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway)->toBeInstanceOf(\AIArmada\Cashier\Contracts\GatewayContract::class);
        });

        it('returns correct currency', function () {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway->currency())->toBe('USD');
        });
    });

    describe('ChipGateway', function () {
        it('returns correct name', function () {
            $gateway = $this->gatewayManager->gateway('chip');

            expect($gateway->name())->toBe('chip');
        });

        it('extends AbstractGateway', function () {
            $gateway = $this->gatewayManager->gateway('chip');

            expect($gateway)->toBeInstanceOf(AbstractGateway::class);
        });

        it('implements GatewayContract', function () {
            $gateway = $this->gatewayManager->gateway('chip');

            expect($gateway)->toBeInstanceOf(\AIArmada\Cashier\Contracts\GatewayContract::class);
        });

        it('returns correct currency', function () {
            $gateway = $this->gatewayManager->gateway('chip');

            expect($gateway->currency())->toBe('MYR');
        });
    });
});

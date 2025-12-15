<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Gateways\AbstractGateway;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('AbstractGateway', function (): void {
    beforeEach(function (): void {
        $this->stripeGateway = $this->gatewayManager->gateway('stripe');
        $this->chipGateway = $this->gatewayManager->gateway('chip');
    });

    describe('displayName', function (): void {
        it('returns capitalized gateway name for stripe', function (): void {
            expect($this->stripeGateway->displayName())->toBe('Stripe');
        });

        it('returns capitalized gateway name for chip', function (): void {
            expect($this->chipGateway->displayName())->toBe('Chip');
        });
    });

    describe('isAvailable', function (): void {
        it('returns true by default', function (): void {
            expect($this->stripeGateway->isAvailable())->toBeTrue();
            expect($this->chipGateway->isAvailable())->toBeTrue();
        });
    });

    describe('currency', function (): void {
        it('returns configured currency for stripe', function (): void {
            expect($this->stripeGateway->currency())->toBe('USD');
        });

        it('returns configured currency for chip', function (): void {
            expect($this->chipGateway->currency())->toBe('MYR');
        });
    });

    describe('currencyLocale', function (): void {
        it('returns configured currency locale', function (): void {
            expect($this->stripeGateway->currencyLocale())->toBe('en_US');
            expect($this->chipGateway->currencyLocale())->toBe('ms_MY');
        });
    });

    describe('formatAmount', function (): void {
        it('formats amount in default currency', function (): void {
            $formatted = $this->stripeGateway->formatAmount(1000);

            expect($formatted)->toBeString();
        });

        it('formats amount with specified currency', function (): void {
            $formatted = $this->stripeGateway->formatAmount(1000, 'EUR');

            expect($formatted)->toBeString();
        });
    });

    describe('isTestMode', function (): void {
        it('returns false when test_mode is not configured', function (): void {
            expect($this->stripeGateway->isTestMode())->toBeFalse();
        });
    });

    describe('webhookSecret', function (): void {
        it('returns configured webhook secret', function (): void {
            expect($this->stripeGateway->webhookSecret())->toBe('whsec_xxx');
        });
    });

    describe('structure', function (): void {
        it('is an abstract class', function (): void {
            $reflection = new ReflectionClass(AbstractGateway::class);

            expect($reflection->isAbstract())->toBeTrue();
        });

        it('implements GatewayContract', function (): void {
            expect($this->stripeGateway)->toBeInstanceOf(GatewayContract::class);
        });

        it('has required abstract methods', function (): void {
            $reflection = new ReflectionClass(AbstractGateway::class);
            $abstractMethods = array_filter(
                $reflection->getMethods(),
                fn ($m) => $m->isAbstract()
            );

            $methodNames = array_map(fn ($m) => $m->getName(), $abstractMethods);

            expect($methodNames)->toContain('name')
                ->and($methodNames)->toContain('verifyWebhookSignature')
                ->and($methodNames)->toContain('handleWebhook')
                ->and($methodNames)->toContain('subscription')
                ->and($methodNames)->toContain('customer')
                ->and($methodNames)->toContain('checkout');
        });

        it('has final methods for consistent behavior', function (): void {
            $reflection = new ReflectionClass(AbstractGateway::class);
            $finalMethods = array_filter(
                $reflection->getMethods(),
                fn ($m) => $m->isFinal()
            );

            $methodNames = array_map(fn ($m) => $m->getName(), $finalMethods);

            expect($methodNames)->toContain('displayName')
                ->and($methodNames)->toContain('isAvailable')
                ->and($methodNames)->toContain('currency')
                ->and($methodNames)->toContain('formatAmount')
                ->and($methodNames)->toContain('isTestMode')
                ->and($methodNames)->toContain('webhookSecret')
                ->and($methodNames)->toContain('newSubscription')
                ->and($methodNames)->toContain('findPayment')
                ->and($methodNames)->toContain('findInvoice')
                ->and($methodNames)->toContain('findBillable');
        });
    });
});

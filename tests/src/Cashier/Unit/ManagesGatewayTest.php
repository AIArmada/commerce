<?php

declare(strict_types=1);

use AIArmada\Cashier\Concerns\ManagesGateway;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('ManagesGateway Trait', function (): void {
    beforeEach(function (): void {
        $this->user = $this->createUser();
    });

    describe('gateway', function (): void {
        it('returns default gateway instance', function (): void {
            $gateway = $this->user->gateway();

            expect($gateway)->toBeInstanceOf(GatewayContract::class);
        });

        it('returns specific gateway instance', function (): void {
            $gateway = $this->user->gateway('chip');

            expect($gateway)->toBeInstanceOf(GatewayContract::class)
                ->and($gateway->name())->toBe('chip');
        });
    });

    describe('preferredGateway', function (): void {
        it('returns default when no preference is set', function (): void {
            $gateway = $this->user->preferredGateway();

            expect($gateway)->toBe('stripe');
        });

        it('returns preferred gateway when set on model', function (): void {
            $this->user->preferred_gateway = 'chip';

            $gateway = $this->user->preferredGateway();

            expect($gateway)->toBe('chip');
        });
    });

    describe('setPreferredGateway', function (): void {
        it('sets the preferred gateway', function (): void {
            $result = $this->user->setPreferredGateway('chip');

            expect($this->user->preferred_gateway)->toBe('chip')
                ->and($this->user->preferredGateway())->toBe('chip');
        });

        it('persists to database', function (): void {
            $this->user->setPreferredGateway('chip');

            $freshUser = $this->user->fresh();

            expect($freshUser->preferred_gateway)->toBe('chip');
        });

        it('returns self for chaining', function (): void {
            $result = $this->user->setPreferredGateway('chip');

            expect($result)->toBe($this->user);
        });
    });

    describe('gatewayId', function (): void {
        it('returns null when no gateway id is set', function (): void {
            $id = $this->user->gatewayId();

            expect($id)->toBeNull();
        });

        it('returns stripe_id for stripe gateway', function (): void {
            $this->user->stripe_id = 'cus_stripe_xxx';
            $this->user->save();

            $id = $this->user->gatewayId('stripe');

            expect($id)->toBe('cus_stripe_xxx');
        });

        it('returns chip_id for chip gateway', function (): void {
            $this->user->chip_id = 'cus_chip_xxx';
            $this->user->save();

            $id = $this->user->gatewayId('chip');

            expect($id)->toBe('cus_chip_xxx');
        });
    });

    describe('hasGatewayId', function (): void {
        it('returns false when no gateway id is set', function (): void {
            $hasId = $this->user->hasGatewayId();

            expect($hasId)->toBeFalse();
        });

        it('returns true when gateway id exists', function (): void {
            $this->user->stripe_id = 'cus_xxx';
            $this->user->save();

            $hasId = $this->user->hasGatewayId('stripe');

            expect($hasId)->toBeTrue();
        });
    });

    describe('trait structure', function (): void {
        it('uses ManagesGateway trait', function (): void {
            $traits = class_uses_recursive($this->user);

            expect($traits)->toContain(ManagesGateway::class);
        });

        it('has required methods', function (): void {
            expect(method_exists($this->user, 'gateway'))->toBeTrue()
                ->and(method_exists($this->user, 'preferredGateway'))->toBeTrue()
                ->and(method_exists($this->user, 'gatewayId'))->toBeTrue()
                ->and(method_exists($this->user, 'hasGatewayId'))->toBeTrue()
                ->and(method_exists($this->user, 'gatewaySubscriptions'))->toBeTrue()
                ->and(method_exists($this->user, 'allGatewaySubscriptions'))->toBeTrue()
                ->and(method_exists($this->user, 'gatewaySubscription'))->toBeTrue()
                ->and(method_exists($this->user, 'subscribedViaGateway'))->toBeTrue()
                ->and(method_exists($this->user, 'allGatewayPaymentMethods'))->toBeTrue()
                ->and(method_exists($this->user, 'gatewayPaymentMethods'))->toBeTrue()
                ->and(method_exists($this->user, 'allGatewayInvoices'))->toBeTrue()
                ->and(method_exists($this->user, 'gatewayInvoices'))->toBeTrue();
        });
    });
});

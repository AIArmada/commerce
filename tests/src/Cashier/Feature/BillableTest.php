<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Models\Subscription;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('Billable Trait', function () {
    beforeEach(function () {
        $this->user = $this->createUser();
    });

    describe('gateway resolution', function () {
        it('can get the default gateway', function () {
            $gateway = $this->user->gateway();

            expect($gateway)->toBeInstanceOf(GatewayContract::class)
                ->and($gateway->name())->toBe('stripe');
        });

        it('can get a specific gateway by name', function () {
            $gateway = $this->user->gateway('chip');

            expect($gateway)->toBeInstanceOf(GatewayContract::class)
                ->and($gateway->name())->toBe('chip');
        });

        it('can get the gateway customer id', function () {
            $this->user->update(['stripe_id' => 'cus_test123']);

            $customerId = $this->user->gatewayId('stripe');

            expect($customerId)->toBe('cus_test123');
        });

        it('can check if has gateway id', function () {
            expect($this->user->hasGatewayId('stripe'))->toBeFalse();

            $this->user->update(['stripe_id' => 'cus_test123']);

            expect($this->user->hasGatewayId('stripe'))->toBeTrue();
        });
    });

    describe('subscriptions', function () {
        it('can get a subscription by type', function () {
            $this->createSubscription($this->user, [
                'type' => 'premium',
            ]);

            $result = $this->user->subscription('premium');

            expect($result)->toBeInstanceOf(Subscription::class)
                ->and($result->type)->toBe('premium');
        });

        it('returns null for non-existent subscription', function () {
            $result = $this->user->subscription('non-existent');

            expect($result)->toBeNull();
        });

        it('can get all subscriptions', function () {
            $this->createSubscription($this->user);
            $this->createSubscription($this->user, ['type' => 'premium']);

            expect($this->user->subscriptions)->toHaveCount(2);
        });

        it('can filter subscriptions by gateway', function () {
            $this->createSubscription($this->user, ['gateway' => 'stripe']);
            $this->createSubscription($this->user, [
                'type' => 'premium',
                'gateway' => 'chip',
            ]);

            $stripeSubscriptions = $this->user->subscriptions->where('gateway', 'stripe');

            expect($stripeSubscriptions)->toHaveCount(1)
                ->and($stripeSubscriptions->first()->gateway)->toBe('stripe');
        });

        it('can check if subscribed to any plan', function () {
            $this->createSubscription($this->user, [
                'gateway_status' => Subscription::STATUS_ACTIVE,
            ]);

            expect($this->user->subscribed())->toBeTrue();
        });

        it('can check if subscribed to a specific plan type', function () {
            $this->createSubscription($this->user, [
                'type' => 'premium',
                'gateway_status' => Subscription::STATUS_ACTIVE,
            ]);

            expect($this->user->subscribed('premium'))->toBeTrue()
                ->and($this->user->subscribed('basic'))->toBeFalse();
        });
    });

    describe('trial', function () {
        it('can check if on trial', function () {
            $this->createSubscription($this->user, [
                'trial_ends_at' => now()->addDays(14),
            ]);

            expect($this->user->onTrial())->toBeTrue();
        });

        it('can check trial for specific subscription', function () {
            $this->createSubscription($this->user, [
                'type' => 'premium',
                'trial_ends_at' => now()->addDays(14),
            ]);

            expect($this->user->onTrial('premium'))->toBeTrue()
                ->and($this->user->onTrial('basic'))->toBeFalse();
        });

        it('can check generic trial', function () {
            $this->user->update(['trial_ends_at' => now()->addDays(14)]);

            expect($this->user->onGenericTrial())->toBeTrue();
        });
    });
});

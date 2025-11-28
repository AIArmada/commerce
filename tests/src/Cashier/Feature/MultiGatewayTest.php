<?php

declare(strict_types=1);

use AIArmada\Cashier\Models\Subscription;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use Carbon\Carbon;

uses(CashierTestCase::class);

describe('Multi-Gateway Subscriptions', function () {
    beforeEach(function () {
        $this->user = $this->createUser();
    });

    describe('creating subscriptions on different gateways', function () {
        it('can have subscriptions on multiple gateways', function () {
            $stripeSubscription = $this->createSubscription($this->user, [
                'type' => 'stripe-plan',
                'gateway' => 'stripe',
                'gateway_id' => 'sub_stripe123',
            ]);

            $chipSubscription = $this->createSubscription($this->user, [
                'type' => 'chip-plan',
                'gateway' => 'chip',
                'gateway_id' => 'sub_chip456',
            ]);

            expect($this->user->subscriptions)->toHaveCount(2)
                ->and($stripeSubscription->gateway)->toBe('stripe')
                ->and($chipSubscription->gateway)->toBe('chip');
        });

        it('can filter subscriptions by gateway', function () {
            $this->createSubscription($this->user, ['type' => 'plan-a', 'gateway' => 'stripe']);
            $this->createSubscription($this->user, ['type' => 'plan-b', 'gateway' => 'stripe']);
            $this->createSubscription($this->user, ['type' => 'plan-c', 'gateway' => 'chip']);

            $stripeSubscriptions = Subscription::query()->forGateway('stripe')->get();
            $chipSubscriptions = Subscription::query()->forGateway('chip')->get();

            expect($stripeSubscriptions)->toHaveCount(2)
                ->and($chipSubscriptions)->toHaveCount(1);
        });

        it('resolves correct gateway for subscription operations', function () {
            $subscription = $this->createSubscription($this->user, [
                'gateway' => 'chip',
                'gateway_id' => 'sub_chip789',
            ]);

            expect($subscription->gateway())->toBe('chip');
        });
    });

    describe('subscription lifecycle across gateways', function () {
        it('manages independent subscription states', function () {
            $stripeSubscription = $this->createSubscription($this->user, [
                'type' => 'stripe-plan',
                'gateway' => 'stripe',
                'gateway_status' => Subscription::STATUS_ACTIVE,
                'ends_at' => null,
            ]);

            $chipSubscription = $this->createSubscription($this->user, [
                'type' => 'chip-plan',
                'gateway' => 'chip',
                'gateway_status' => Subscription::STATUS_TRIALING,
                'trial_ends_at' => Carbon::now()->addDays(14),
            ]);

            expect($stripeSubscription->active())->toBeTrue()
                ->and($stripeSubscription->onTrial())->toBeFalse()
                ->and($chipSubscription->onTrial())->toBeTrue();
        });

        it('can cancel subscription on specific gateway', function () {
            $subscription = $this->createSubscription($this->user, [
                'gateway' => 'stripe',
                'gateway_status' => Subscription::STATUS_ACTIVE,
                'next_billing_at' => Carbon::now()->addMonth(),
            ]);

            $subscription->cancel();
            $subscription->refresh();

            expect($subscription->canceled())->toBeTrue()
                ->and($subscription->onGracePeriod())->toBeTrue();
        });

        it('maintains gateway association after operations', function () {
            $subscription = $this->createSubscription($this->user, [
                'gateway' => 'chip',
                'trial_ends_at' => Carbon::now()->addDays(7),
            ]);

            $subscription->cancel();
            $subscription->refresh();

            expect($subscription->gateway)->toBe('chip');
        });
    });

    describe('querying subscriptions', function () {
        it('can query active subscriptions across all gateways', function () {
            $this->createSubscription($this->user, [
                'type' => 'plan-a',
                'gateway' => 'stripe',
                'gateway_status' => Subscription::STATUS_ACTIVE,
                'ends_at' => null,
            ]);

            $this->createSubscription($this->user, [
                'type' => 'plan-b',
                'gateway' => 'chip',
                'gateway_status' => Subscription::STATUS_ACTIVE,
                'ends_at' => null,
            ]);

            $this->createSubscription($this->user, [
                'type' => 'plan-c',
                'gateway' => 'stripe',
                'gateway_status' => Subscription::STATUS_CANCELED,
                'ends_at' => Carbon::now()->subDay(),
            ]);

            $activeSubscriptions = Subscription::query()->active()->get();

            expect($activeSubscriptions)->toHaveCount(2);
        });

        it('can query subscriptions on trial for specific gateway', function () {
            $this->createSubscription($this->user, [
                'type' => 'plan-a',
                'gateway' => 'stripe',
                'trial_ends_at' => Carbon::now()->addDays(14),
            ]);

            $this->createSubscription($this->user, [
                'type' => 'plan-b',
                'gateway' => 'chip',
                'trial_ends_at' => null,
            ]);

            $onTrial = Subscription::query()->forGateway('stripe')->onTrial()->get();

            expect($onTrial)->toHaveCount(1)
                ->and($onTrial->first()->gateway)->toBe('stripe');
        });
    });

    describe('customer ids per gateway', function () {
        it('stores separate customer ids for each gateway', function () {
            $this->user->update([
                'stripe_id' => 'cus_stripe_abc',
                'chip_id' => 'cus_chip_xyz',
            ]);

            expect($this->user->gatewayId('stripe'))->toBe('cus_stripe_abc')
                ->and($this->user->gatewayId('chip'))->toBe('cus_chip_xyz');
        });

        it('returns null for gateway without customer id', function () {
            $this->user->update(['stripe_id' => 'cus_stripe_abc']);

            expect($this->user->gatewayId('stripe'))->toBe('cus_stripe_abc')
                ->and($this->user->gatewayId('chip'))->toBeNull();
        });
    });
});

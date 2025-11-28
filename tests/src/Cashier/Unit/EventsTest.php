<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Events\PaymentEvent;
use AIArmada\Cashier\Events\PaymentFailed;
use AIArmada\Cashier\Events\PaymentRefunded;
use AIArmada\Cashier\Events\PaymentSucceeded;
use AIArmada\Cashier\Events\SubscriptionCanceled;
use AIArmada\Cashier\Events\SubscriptionCreated;
use AIArmada\Cashier\Events\SubscriptionEvent;
use AIArmada\Cashier\Events\SubscriptionRenewed;
use AIArmada\Cashier\Events\SubscriptionResumed;
use AIArmada\Cashier\Events\SubscriptionTrialEnding;
use AIArmada\Cashier\Events\SubscriptionUpdated;
use AIArmada\Cashier\Models\Subscription;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('Events', function () {
    beforeEach(function () {
        $this->user = $this->createUser();
        $this->subscription = $this->createSubscription($this->user);
    });

    describe('PaymentEvent (base)', function () {
        it('provides access to payment data', function () {
            $payment = Mockery::mock(PaymentContract::class);
            $event = new class($payment, 'stripe', $this->user) extends PaymentEvent {};

            expect($event->payment())->toBe($payment)
                ->and($event->gateway())->toBe('stripe')
                ->and($event->billable())->toBe($this->user);
        });
    });

    describe('PaymentSucceeded', function () {
        it('can be instantiated', function () {
            $payment = Mockery::mock(PaymentContract::class);
            $event = new PaymentSucceeded($payment, 'stripe', $this->user);

            expect($event)->toBeInstanceOf(PaymentSucceeded::class)
                ->and($event)->toBeInstanceOf(PaymentEvent::class);
        });
    });

    describe('PaymentFailed', function () {
        it('can be instantiated', function () {
            $payment = Mockery::mock(PaymentContract::class);
            $event = new PaymentFailed($payment, 'chip', $this->user);

            expect($event)->toBeInstanceOf(PaymentFailed::class)
                ->and($event->gateway())->toBe('chip');
        });
    });

    describe('PaymentRefunded', function () {
        it('can be instantiated', function () {
            $payment = Mockery::mock(PaymentContract::class);
            $event = new PaymentRefunded($payment, 'stripe', $this->user);

            expect($event)->toBeInstanceOf(PaymentRefunded::class);
        });
    });

    describe('SubscriptionEvent (base)', function () {
        it('provides access to subscription data', function () {
            $event = new class($this->subscription, $this->user) extends SubscriptionEvent {};

            expect($event->subscription())->toBe($this->subscription)
                ->and($event->gateway())->toBe('stripe')
                ->and($event->billable())->toBe($this->user);
        });

        it('accepts subscription without explicit billable', function () {
            // When no billable is passed, the event should still be creatable
            $event = new class($this->subscription) extends SubscriptionEvent {};

            expect($event->subscription())->toBe($this->subscription);
        });
    });

    describe('SubscriptionCreated', function () {
        it('can be instantiated', function () {
            $event = new SubscriptionCreated($this->subscription, $this->user);

            expect($event)->toBeInstanceOf(SubscriptionCreated::class)
                ->and($event)->toBeInstanceOf(SubscriptionEvent::class);
        });
    });

    describe('SubscriptionUpdated', function () {
        it('can be instantiated', function () {
            $event = new SubscriptionUpdated($this->subscription);

            expect($event)->toBeInstanceOf(SubscriptionUpdated::class)
                ->and($event->subscription())->toBe($this->subscription);
        });
    });

    describe('SubscriptionCanceled', function () {
        it('can be instantiated', function () {
            $event = new SubscriptionCanceled($this->subscription);

            expect($event)->toBeInstanceOf(SubscriptionCanceled::class);
        });
    });

    describe('SubscriptionResumed', function () {
        it('can be instantiated', function () {
            $event = new SubscriptionResumed($this->subscription);

            expect($event)->toBeInstanceOf(SubscriptionResumed::class);
        });
    });

    describe('SubscriptionRenewed', function () {
        it('can be instantiated', function () {
            $event = new SubscriptionRenewed($this->subscription);

            expect($event)->toBeInstanceOf(SubscriptionRenewed::class);
        });
    });

    describe('SubscriptionTrialEnding', function () {
        it('can be instantiated', function () {
            $event = new SubscriptionTrialEnding($this->subscription);

            expect($event)->toBeInstanceOf(SubscriptionTrialEnding::class);
        });
    });
});

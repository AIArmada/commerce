<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
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
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('Events', function (): void {
    beforeEach(function (): void {
        $this->user = $this->createUser();

        // Mock BillableContract for the owner return
        $this->billableMock = Mockery::mock(BillableContract::class);

        // Mock SubscriptionContract for testing events
        // The owner() method now returns BillableContract directly (not a relation)
        $this->subscription = Mockery::mock(SubscriptionContract::class);
        $this->subscription->shouldReceive('gateway')->andReturn('stripe');
        $this->subscription->shouldReceive('owner')->andReturn($this->billableMock);
    });

    describe('PaymentEvent (base)', function (): void {
        it('provides access to payment data', function (): void {
            $payment = Mockery::mock(PaymentContract::class);
            $event = new class($payment, 'stripe', $this->user) extends PaymentEvent {};

            expect($event->payment())->toBe($payment)
                ->and($event->gateway())->toBe('stripe')
                ->and($event->billable())->toBe($this->user);
        });
    });

    describe('PaymentSucceeded', function (): void {
        it('can be instantiated', function (): void {
            $payment = Mockery::mock(PaymentContract::class);
            $event = new PaymentSucceeded($payment, 'stripe', $this->user);

            expect($event)->toBeInstanceOf(PaymentSucceeded::class)
                ->and($event)->toBeInstanceOf(PaymentEvent::class);
        });
    });

    describe('PaymentFailed', function (): void {
        it('can be instantiated', function (): void {
            $payment = Mockery::mock(PaymentContract::class);
            $event = new PaymentFailed($payment, 'chip', $this->user);

            expect($event)->toBeInstanceOf(PaymentFailed::class)
                ->and($event->gateway())->toBe('chip');
        });
    });

    describe('PaymentRefunded', function (): void {
        it('can be instantiated', function (): void {
            $payment = Mockery::mock(PaymentContract::class);
            $event = new PaymentRefunded($payment, 'stripe', $this->user);

            expect($event)->toBeInstanceOf(PaymentRefunded::class);
        });
    });

    describe('SubscriptionEvent (base)', function (): void {
        it('provides access to subscription data', function (): void {
            $event = new class($this->subscription, $this->user) extends SubscriptionEvent {};

            expect($event->subscription())->toBe($this->subscription)
                ->and($event->gateway())->toBe('stripe')
                ->and($event->billable())->toBe($this->user);
        });

<<<<<<< Updated upstream
<<<<<<< Updated upstream
        it('accepts subscription without explicit billable', function (): void {
            // When no billable is passed, the event should still be creatable
=======
        it('accepts subscription without explicit billable', function () {
>>>>>>> Stashed changes
=======
        it('accepts subscription without explicit billable', function () {
>>>>>>> Stashed changes
            $event = new class($this->subscription) extends SubscriptionEvent {};

            expect($event->subscription())->toBe($this->subscription)
                ->and($event->billable())->toBe($this->billableMock);
        });
    });

    describe('SubscriptionCreated', function (): void {
        it('can be instantiated', function (): void {
            $event = new SubscriptionCreated($this->subscription, $this->user);

            expect($event)->toBeInstanceOf(SubscriptionCreated::class)
                ->and($event)->toBeInstanceOf(SubscriptionEvent::class);
        });
    });

    describe('SubscriptionUpdated', function (): void {
        it('can be instantiated', function (): void {
            $event = new SubscriptionUpdated($this->subscription);

            expect($event)->toBeInstanceOf(SubscriptionUpdated::class)
                ->and($event->subscription())->toBe($this->subscription);
        });
    });

    describe('SubscriptionCanceled', function (): void {
        it('can be instantiated', function (): void {
            $event = new SubscriptionCanceled($this->subscription);

            expect($event)->toBeInstanceOf(SubscriptionCanceled::class);
        });
    });

    describe('SubscriptionResumed', function (): void {
        it('can be instantiated', function (): void {
            $event = new SubscriptionResumed($this->subscription);

            expect($event)->toBeInstanceOf(SubscriptionResumed::class);
        });
    });

    describe('SubscriptionRenewed', function (): void {
        it('can be instantiated', function (): void {
            $event = new SubscriptionRenewed($this->subscription);

            expect($event)->toBeInstanceOf(SubscriptionRenewed::class);
        });
    });

    describe('SubscriptionTrialEnding', function (): void {
        it('can be instantiated', function (): void {
            $event = new SubscriptionTrialEnding($this->subscription);

            expect($event)->toBeInstanceOf(SubscriptionTrialEnding::class);
        });
    });
});

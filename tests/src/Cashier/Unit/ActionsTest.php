<?php

declare(strict_types=1);

use AIArmada\Cashier\Actions\CancelSubscription;
use AIArmada\Cashier\Actions\CreatePayment;
use AIArmada\Cashier\Actions\CreateSubscription;
use AIArmada\Cashier\Actions\RefundPayment;
use AIArmada\Cashier\Actions\SyncWebhook;
use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Contracts\SubscriptionBuilderContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Events\PaymentFailed;
use AIArmada\Cashier\Events\PaymentRefunded;
use AIArmada\Cashier\Events\PaymentSucceeded;
use AIArmada\Cashier\Events\SubscriptionCanceled;
use AIArmada\Cashier\Events\SubscriptionCreated;
use AIArmada\Cashier\Events\WebhookHandled;
use AIArmada\Cashier\Events\WebhookReceived;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use Illuminate\Support\Facades\Event;

uses(CashierTestCase::class);

describe('Actions', function (): void {
    beforeEach(function (): void {
        $this->billable = Mockery::mock(BillableContract::class);
        $this->gatewayMock = Mockery::mock(GatewayContract::class);
        $this->paymentMock = Mockery::mock(PaymentContract::class);
        $this->subscriptionMock = Mockery::mock(SubscriptionContract::class);
        $this->builderMock = Mockery::mock(SubscriptionBuilderContract::class);
    });

    describe('CreatePayment', function (): void {
        it('charges via gateway and dispatches PaymentSucceeded on success', function (): void {
            $this->paymentMock->shouldReceive('isSucceeded')->once()->andReturnTrue();
            $this->paymentMock->shouldReceive('id')->andReturn('pi_test');
            $this->paymentMock->shouldReceive('errorCode')->andReturn(null);

            $this->gatewayMock->shouldReceive('charge')
                ->once()
                ->with($this->billable, 5000, 'pm_test', [])
                ->andReturn($this->paymentMock);

            Cashier::shouldReceive('gateway')
                ->once()
                ->with('stripe')
                ->andReturn($this->gatewayMock);

            Event::fake();

            $payment = CreatePayment::run($this->billable, 5000, 'pm_test');

            expect($payment)->toBe($this->paymentMock);

            Event::assertDispatched(PaymentSucceeded::class, fn (PaymentSucceeded $event) => $event->payment === $this->paymentMock && $event->gateway === 'stripe');
        });

        it('throws PaymentFailedException and dispatches PaymentFailed on failure', function (): void {
            $this->paymentMock->shouldReceive('isSucceeded')->once()->andReturnFalse();
            $this->paymentMock->shouldReceive('isFailed')->once()->andReturnTrue();
            $this->paymentMock->shouldReceive('id')->andReturn('pi_test');
            $this->paymentMock->shouldReceive('errorCode')->andReturn('card_declined');

            $this->gatewayMock->shouldReceive('charge')
                ->once()
                ->andReturn($this->paymentMock);

            Cashier::shouldReceive('gateway')
                ->once()
                ->with('stripe')
                ->andReturn($this->gatewayMock);

            Event::fake();

            expect(fn () => CreatePayment::run($this->billable, 5000, 'pm_test'))
                ->toThrow(\AIArmada\Cashier\Exceptions\PaymentFailedException::class);

            Event::assertDispatched(PaymentFailed::class);
        });
    });

    describe('RefundPayment', function (): void {
        it('refunds via gateway and dispatches PaymentRefunded', function (): void {
            $this->gatewayMock->shouldReceive('refund')
                ->once()
                ->with('pi_test', null)
                ->andReturn($this->paymentMock);

            Cashier::shouldReceive('gateway')
                ->once()
                ->with('stripe')
                ->andReturn($this->gatewayMock);

            Event::fake();

            $payment = RefundPayment::run('pi_test');

            expect($payment)->toBe($this->paymentMock);

            Event::assertDispatched(PaymentRefunded::class, fn (PaymentRefunded $event) => $event->payment === $this->paymentMock && $event->gateway === 'stripe');
        });

        it('can refund a partial amount', function (): void {
            $this->gatewayMock->shouldReceive('refund')
                ->once()
                ->with('pi_test', 2000)
                ->andReturn($this->paymentMock);

            Cashier::shouldReceive('gateway')
                ->once()
                ->with('stripe')
                ->andReturn($this->gatewayMock);

            Event::fake();

            $payment = RefundPayment::run('pi_test', 2000);

            expect($payment)->toBe($this->paymentMock);

            Event::assertDispatched(PaymentRefunded::class);
        });

        it('uses specified gateway', function (): void {
            $this->gatewayMock->shouldReceive('refund')
                ->once()
                ->andReturn($this->paymentMock);

            Cashier::shouldReceive('gateway')
                ->once()
                ->with('chip')
                ->andReturn($this->gatewayMock);

            Event::fake();

            RefundPayment::run('pi_test', null, 'chip');

            Event::assertDispatched(PaymentRefunded::class);
        });
    });

    describe('CreateSubscription', function (): void {
        it('creates subscription via gateway and dispatches SubscriptionCreated', function (): void {
            $this->builderMock->shouldReceive('create')
                ->once()
                ->with('pm_test', [])
                ->andReturn($this->subscriptionMock);

            $this->gatewayMock->shouldReceive('newSubscription')
                ->once()
                ->with($this->billable, 'default', ['price_monthly'])
                ->andReturn($this->builderMock);

            Cashier::shouldReceive('gateway')
                ->once()
                ->with('stripe')
                ->andReturn($this->gatewayMock);

            Event::fake();

            $subscription = CreateSubscription::run($this->billable, 'default', ['price_monthly'], 'pm_test');

            expect($subscription)->toBe($this->subscriptionMock);

            Event::assertDispatched(SubscriptionCreated::class, fn (SubscriptionCreated $event) => $event->subscription === $this->subscriptionMock);
        });
    });

    describe('CancelSubscription', function (): void {
        it('cancels at period end and dispatches SubscriptionCanceled', function (): void {
            $this->subscriptionMock->shouldReceive('cancel')->once()->andReturn($this->subscriptionMock);
            $this->subscriptionMock->shouldNotReceive('cancelNow');

            Event::fake();

            $subscription = CancelSubscription::run($this->subscriptionMock);

            expect($subscription)->toBe($this->subscriptionMock);

            Event::assertDispatched(SubscriptionCanceled::class);
        });

        it('cancels immediately when requested', function (): void {
            $this->subscriptionMock->shouldReceive('cancelNow')->once()->andReturn($this->subscriptionMock);
            $this->subscriptionMock->shouldNotReceive('cancel');

            Event::fake();

            $subscription = CancelSubscription::run($this->subscriptionMock, immediate: true);

            expect($subscription)->toBe($this->subscriptionMock);

            Event::assertDispatched(SubscriptionCanceled::class);
        });
    });

    describe('SyncWebhook', function (): void {
        it('dispatches WebhookReceived and WebhookHandled', function (): void {
            $payload = ['type' => 'payment_intent.succeeded', 'data' => []];
            $headers = ['Stripe-Signature' => 'test_sig'];

            $this->gatewayMock->shouldReceive('handleWebhook')
                ->once()
                ->with($payload, $headers)
                ->andReturnNull();

            Cashier::shouldReceive('gateway')
                ->once()
                ->with('stripe')
                ->andReturn($this->gatewayMock);

            Event::fake();

            SyncWebhook::run('stripe', $payload, $headers);

            Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event) => $event->gateway === 'stripe' && $event->payload === $payload);
            Event::assertDispatched(WebhookHandled::class, fn (WebhookHandled $event) => $event->gateway === 'stripe' && $event->payload === $payload);
        });
    });
});

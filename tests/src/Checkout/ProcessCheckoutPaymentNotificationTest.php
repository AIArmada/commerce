<?php

declare(strict_types=1);

use AIArmada\Checkout\Actions\ProcessCheckoutPaymentNotification;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Data\CheckoutResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Cancelled;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use Illuminate\Support\Str;

use function Pest\Laravel\mock;

describe('ProcessCheckoutPaymentNotification', function (): void {
    it('is idempotent for already completed sessions', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-notification-completed',
            'status' => Completed::class,
            'completed_at' => now(),
            'selected_payment_gateway' => 'chip',
        ]);

        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')->never();
        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        $action = app(ProcessCheckoutPaymentNotification::class);
        $action->handle(
            payload: ['reference' => $session->id],
            callbackType: 'success',
        );
    });

    it('skips sessions using a different payment gateway', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-notification-gateway-filter',
            'status' => AwaitingPayment::class,
            'selected_payment_gateway' => 'cashier',
        ]);

        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')->never();
        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        $action = app(ProcessCheckoutPaymentNotification::class);
        $action->handle(
            payload: ['reference' => $session->id],
            callbackType: 'success',
            expectedGateways: ['chip', 'cashier-chip'],
        );
    });

    it('processes success callbacks for sessions in awaiting payment state', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-notification-awaiting-success',
            'status' => AwaitingPayment::class,
            'selected_payment_gateway' => 'chip',
        ]);

        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')
            ->once()
            ->withArgs(fn (CheckoutSession $s, string $type): bool => $s->id === $session->id && $type === 'success')
            ->andReturn(CheckoutResult::success($session));
        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        $action = app(ProcessCheckoutPaymentNotification::class);
        $action->handle(
            payload: ['reference' => $session->id],
            callbackType: 'success',
        );
    });

    it('skips success callbacks for sessions in pending state', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-notification-pending-skip-success',
            'status' => Pending::class,
            'selected_payment_gateway' => 'chip',
        ]);

        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')->never();
        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        $action = app(ProcessCheckoutPaymentNotification::class);
        $action->handle(
            payload: ['reference' => $session->id],
            callbackType: 'success',
        );
    });

    it('processes failure callbacks for sessions in pending state', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-notification-pending-failure',
            'status' => Pending::class,
            'selected_payment_gateway' => 'chip',
        ]);

        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')
            ->once()
            ->withArgs(fn (CheckoutSession $s, string $type): bool => $s->id === $session->id && $type === 'failure')
            ->andReturn(CheckoutResult::failed($session, 'Payment failed'));
        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        $action = app(ProcessCheckoutPaymentNotification::class);
        $action->handle(
            payload: ['reference' => $session->id],
            callbackType: 'failure',
        );
    });

    it('processes cancel callbacks for sessions in pending state', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-notification-pending-cancel',
            'status' => Pending::class,
            'selected_payment_gateway' => 'chip',
        ]);

        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')
            ->once()
            ->withArgs(fn (CheckoutSession $s, string $type): bool => $s->id === $session->id && $type === 'cancel')
            ->andReturn(CheckoutResult::failed($session, 'Payment cancelled'));
        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        $action = app(ProcessCheckoutPaymentNotification::class);
        $action->handle(
            payload: ['reference' => $session->id],
            callbackType: 'cancel',
        );
    });

    it('silently returns when the session reference is missing', function (): void {
        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')->never();
        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        $action = app(ProcessCheckoutPaymentNotification::class);
        $action->handle(
            payload: ['status' => 'paid'],
            callbackType: 'success',
        );
    });

    it('silently returns when the session does not exist in the database', function (): void {
        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')->never();
        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        $action = app(ProcessCheckoutPaymentNotification::class);
        $action->handle(
            payload: ['reference' => (string) Str::uuid()],
            callbackType: 'success',
        );
    });

    it('processes through sessions in payment processing state', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-notification-payment-processing',
            'selected_payment_gateway' => 'chip',
        ]);
        $session->transitionStatus(Processing::class);
        $session = $session->fresh();
        $session->transitionStatus(PaymentProcessing::class);

        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')
            ->once()
            ->withArgs(fn (CheckoutSession $s, string $type): bool => $s->id === $session->id && $type === 'success')
            ->andReturn(CheckoutResult::success($session));
        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        $action = app(ProcessCheckoutPaymentNotification::class);
        $action->handle(
            payload: ['reference' => $session->id],
            callbackType: 'success',
        );
    });

    it('skips callbacks for sessions in cancelled state', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-notification-cancelled',
            'status' => Pending::class,
            'selected_payment_gateway' => 'chip',
        ]);
        $session->transitionStatus(Cancelled::class);

        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')->never();
        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        $action = app(ProcessCheckoutPaymentNotification::class);
        $action->handle(
            payload: ['reference' => $session->id],
            callbackType: 'success',
        );
    });

    it('skips callbacks for sessions in payment failed state', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-notification-payment-failed-skip',
            'status' => Pending::class,
            'selected_payment_gateway' => 'chip',
        ]);
        $session->transitionStatus(PaymentFailed::class);

        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')->never();
        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        $action = app(ProcessCheckoutPaymentNotification::class);
        $action->handle(
            payload: ['reference' => $session->id],
            callbackType: 'success',
        );
    });

    it('resolves session reference from metadata.checkout_session_id', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'test-notification-metadata-ref',
            'status' => AwaitingPayment::class,
            'selected_payment_gateway' => 'chip',
        ]);

        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')
            ->once()
            ->withArgs(fn (CheckoutSession $s): bool => $s->id === $session->id)
            ->andReturn(CheckoutResult::success($session));
        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        $action = app(ProcessCheckoutPaymentNotification::class);
        $action->handle(
            payload: ['metadata' => ['checkout_session_id' => $session->id]],
            callbackType: 'success',
        );
    });
});

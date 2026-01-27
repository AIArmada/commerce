<?php

declare(strict_types=1);

use AIArmada\Checkout\Data\CheckoutResult;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed;

describe('StepResult', function (): void {
    it('can create a success result', function (): void {
        $result = StepResult::success('test_step', 'Success message', ['key' => 'value']);

        expect($result->status)->toBe(StepStatus::Completed)
            ->and($result->stepIdentifier)->toBe('test_step')
            ->and($result->message)->toBe('Success message')
            ->and($result->data)->toBe(['key' => 'value'])
            ->and($result->errors)->toBe([])
            ->and($result->isSuccessful())->toBeTrue();
    });

    it('can create a skipped result', function (): void {
        $result = StepResult::skipped('test_step', 'Skipped message');

        expect($result->status)->toBe(StepStatus::Skipped)
            ->and($result->stepIdentifier)->toBe('test_step')
            ->and($result->message)->toBe('Skipped message')
            ->and($result->isSuccessful())->toBeTrue();
    });

    it('can create a failed result', function (): void {
        $result = StepResult::failed('test_step', 'Error message', ['field' => 'error']);

        expect($result->status)->toBe(StepStatus::Failed)
            ->and($result->stepIdentifier)->toBe('test_step')
            ->and($result->message)->toBe('Error message')
            ->and($result->errors)->toBe(['field' => 'error'])
            ->and($result->isSuccessful())->toBeFalse();
    });

    it('has default skipped message', function (): void {
        $result = StepResult::skipped('test_step');

        expect($result->message)->toBe('Step skipped');
    });
});

describe('PaymentResult', function (): void {
    it('can create a success result', function (): void {
        $result = PaymentResult::success('pay_123', 'txn_456', 10000);

        expect($result->status)->toBe(PaymentStatus::Completed)
            ->and($result->paymentId)->toBe('pay_123')
            ->and($result->transactionId)->toBe('txn_456')
            ->and($result->amount)->toBe(10000)
            ->and($result->isSuccessful())->toBeTrue()
            ->and($result->requiresRedirect())->toBeFalse();
    });

    it('can create a pending result with redirect', function (): void {
        $result = PaymentResult::pending('pay_123', 'https://pay.example.com');

        expect($result->status)->toBe(PaymentStatus::Pending)
            ->and($result->paymentId)->toBe('pay_123')
            ->and($result->redirectUrl)->toBe('https://pay.example.com')
            ->and($result->requiresRedirect())->toBeTrue()
            ->and($result->isSuccessful())->toBeFalse();
    });

    it('can create a processing result', function (): void {
        $result = PaymentResult::processing('pay_123');

        expect($result->status)->toBe(PaymentStatus::Processing)
            ->and($result->paymentId)->toBe('pay_123')
            ->and($result->isSuccessful())->toBeFalse();
    });

    it('can create a failed result', function (): void {
        $result = PaymentResult::failed('Payment declined', ['card' => 'invalid'], 'pay_123');

        expect($result->status)->toBe(PaymentStatus::Failed)
            ->and($result->message)->toBe('Payment declined')
            ->and($result->errors)->toBe(['card' => 'invalid'])
            ->and($result->paymentId)->toBe('pay_123')
            ->and($result->isSuccessful())->toBeFalse();
    });

    it('does not require redirect for completed payments', function (): void {
        $result = new PaymentResult(
            status: PaymentStatus::Completed,
            paymentId: 'pay_123',
            redirectUrl: 'https://pay.example.com',
        );

        expect($result->requiresRedirect())->toBeFalse();
    });
});

describe('PaymentRequest', function (): void {
    it('can be created with constructor', function (): void {
        $request = new PaymentRequest(
            amount: 10000,
            currency: 'MYR',
            gateway: 'chip',
            description: 'Test payment',
            customerEmail: 'test@example.com',
            customerName: 'Test User',
            customerPhone: '+60123456789',
            successUrl: 'https://example.com/success',
            failureUrl: 'https://example.com/failure',
            cancelUrl: 'https://example.com/cancel',
            metadata: ['order_id' => 'order_123'],
        );

        expect($request->amount)->toBe(10000)
            ->and($request->currency)->toBe('MYR')
            ->and($request->gateway)->toBe('chip')
            ->and($request->description)->toBe('Test payment')
            ->and($request->customerEmail)->toBe('test@example.com')
            ->and($request->customerName)->toBe('Test User')
            ->and($request->metadata)->toBe(['order_id' => 'order_123']);
    });

    it('can be created from array', function (): void {
        $request = PaymentRequest::fromArray([
            'amount' => 5000,
            'currency' => 'USD',
            'gateway' => 'stripe',
            'description' => 'Order payment',
            'customer_email' => 'user@example.com',
            'customer_name' => 'John Doe',
            'metadata' => ['source' => 'checkout'],
        ]);

        expect($request->amount)->toBe(5000)
            ->and($request->currency)->toBe('USD')
            ->and($request->gateway)->toBe('stripe')
            ->and($request->customerEmail)->toBe('user@example.com')
            ->and($request->customerName)->toBe('John Doe');
    });

    it('uses default currency from config', function (): void {
        config()->set('checkout.defaults.currency', 'SGD');

        $request = PaymentRequest::fromArray([
            'amount' => 1000,
        ]);

        expect($request->currency)->toBe('SGD');
    });
});

describe('CheckoutResult', function (): void {
    it('can be created with success status', function (): void {
        $session = new CheckoutSession;
        $result = new CheckoutResult(
            success: true,
            status: new Completed($session),
            sessionId: 'session_123',
            orderId: 'order_456',
            paymentId: 'pay_789',
            message: 'Checkout completed successfully',
        );

        expect($result->success)->toBeTrue()
            ->and($result->status)->toBeInstanceOf(Completed::class)
            ->and($result->sessionId)->toBe('session_123')
            ->and($result->orderId)->toBe('order_456')
            ->and($result->paymentId)->toBe('pay_789');
    });

    it('can be created with failed status', function (): void {
        $session = new CheckoutSession;
        $result = new CheckoutResult(
            success: false,
            status: new PaymentFailed($session),
            sessionId: 'session_123',
            message: 'Payment failed',
            errors: ['payment' => 'declined'],
        );

        expect($result->success)->toBeFalse()
            ->and($result->status)->toBeInstanceOf(PaymentFailed::class)
            ->and($result->message)->toBe('Payment failed')
            ->and($result->errors)->toBe(['payment' => 'declined']);
    });

    it('can be created with awaiting payment status', function (): void {
        $session = new CheckoutSession;
        $result = new CheckoutResult(
            success: false,
            status: new AwaitingPayment($session),
            sessionId: 'session_123',
            redirectUrl: 'https://pay.example.com',
            message: 'Awaiting payment completion',
        );

        expect($result->success)->toBeFalse()
            ->and($result->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($result->redirectUrl)->toBe('https://pay.example.com')
            ->and($result->requiresRedirect())->toBeTrue();
    });

    it('does not require redirect when successful', function (): void {
        $session = new CheckoutSession;
        $result = new CheckoutResult(
            success: true,
            status: new Completed($session),
            sessionId: 'session_123',
        );

        expect($result->requiresRedirect())->toBeFalse();
    });

    it('does not require redirect when no redirect url', function (): void {
        $session = new CheckoutSession;
        $result = new CheckoutResult(
            success: false,
            status: new AwaitingPayment($session),
            sessionId: 'session_123',
        );

        expect($result->requiresRedirect())->toBeFalse();
    });

    it('stores metadata', function (): void {
        $session = new CheckoutSession;
        $result = new CheckoutResult(
            success: true,
            status: new Completed($session),
            sessionId: 'session_123',
            metadata: ['source' => 'web', 'version' => '1.0'],
        );

        expect($result->metadata)->toBe(['source' => 'web', 'version' => '1.0']);
    });
});

<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Checkout\CheckoutResult;
use AIArmada\Cart\Checkout\StageResult;
use AIArmada\Cart\Testing\InMemoryStorage;

describe('CheckoutResult', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->cart = new Cart($this->storage, 'checkout-test');
    });

    it('can be instantiated with required parameters', function (): void {
        $result = new CheckoutResult(
            success: true,
            cart: $this->cart
        );

        expect($result->success)->toBeTrue()
            ->and($result->cart)->toBe($this->cart)
            ->and($result->context)->toBeEmpty()
            ->and($result->completedStages)->toBeEmpty()
            ->and($result->errors)->toBeEmpty()
            ->and($result->orderId)->toBeNull()
            ->and($result->paymentUrl)->toBeNull();
    });

    it('can be instantiated with all parameters', function (): void {
        $result = new CheckoutResult(
            success: true,
            cart: $this->cart,
            context: ['key' => 'value'],
            completedStages: ['validation', 'payment'],
            errors: [],
            orderId: 'order-123',
            paymentUrl: 'https://payment.example.com'
        );

        expect($result->context)->toBe(['key' => 'value'])
            ->and($result->completedStages)->toBe(['validation', 'payment'])
            ->and($result->orderId)->toBe('order-123')
            ->and($result->paymentUrl)->toBe('https://payment.example.com');
    });

    it('gets value from context', function (): void {
        $result = new CheckoutResult(
            success: true,
            cart: $this->cart,
            context: ['transaction_id' => 'txn-456']
        );

        expect($result->get('transaction_id'))->toBe('txn-456');
    });

    it('returns default for missing context key', function (): void {
        $result = new CheckoutResult(success: true, cart: $this->cart);

        expect($result->get('missing_key', 'default'))->toBe('default');
    });

    it('checks if checkout is successful', function (): void {
        $successful = new CheckoutResult(success: true, cart: $this->cart);
        $failed = new CheckoutResult(success: false, cart: $this->cart);

        expect($successful->isSuccessful())->toBeTrue()
            ->and($failed->isSuccessful())->toBeFalse();
    });

    it('gets order ID from property', function (): void {
        $result = new CheckoutResult(
            success: true,
            cart: $this->cart,
            orderId: 'order-789'
        );

        expect($result->getOrderId())->toBe('order-789');
    });

    it('gets order ID from context fallback', function (): void {
        $result = new CheckoutResult(
            success: true,
            cart: $this->cart,
            context: ['order_id' => 'order-from-context']
        );

        expect($result->getOrderId())->toBe('order-from-context');
    });

    it('prefers orderId property over context', function (): void {
        $result = new CheckoutResult(
            success: true,
            cart: $this->cart,
            context: ['order_id' => 'from-context'],
            orderId: 'from-property'
        );

        expect($result->getOrderId())->toBe('from-property');
    });

    it('gets payment URL from property', function (): void {
        $result = new CheckoutResult(
            success: true,
            cart: $this->cart,
            paymentUrl: 'https://pay.example.com'
        );

        expect($result->getPaymentUrl())->toBe('https://pay.example.com');
    });

    it('gets payment URL from context fallback', function (): void {
        $result = new CheckoutResult(
            success: true,
            cart: $this->cart,
            context: ['payment_url' => 'https://from-context.com']
        );

        expect($result->getPaymentUrl())->toBe('https://from-context.com');
    });

    it('returns null when no order ID or payment URL', function (): void {
        $result = new CheckoutResult(success: true, cart: $this->cart);

        expect($result->getOrderId())->toBeNull()
            ->and($result->getPaymentUrl())->toBeNull();
    });
});

describe('StageResult', function (): void {
    it('can be instantiated with required parameters', function (): void {
        $result = new StageResult(success: true);

        expect($result->success)->toBeTrue()
            ->and($result->message)->toBe('')
            ->and($result->data)->toBeEmpty()
            ->and($result->errors)->toBeEmpty();
    });

    it('can be instantiated with all parameters', function (): void {
        $result = new StageResult(
            success: false,
            message: 'Validation failed',
            data: [],
            errors: ['field' => 'Required']
        );

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Validation failed')
            ->and($result->errors)->toBe(['field' => 'Required']);
    });

    it('creates successful result', function (): void {
        $result = StageResult::success('Stage completed', ['key' => 'value']);

        expect($result->success)->toBeTrue()
            ->and($result->message)->toBe('Stage completed')
            ->and($result->data)->toBe(['key' => 'value'])
            ->and($result->errors)->toBeEmpty();
    });

    it('creates successful result with defaults', function (): void {
        $result = StageResult::success();

        expect($result->success)->toBeTrue()
            ->and($result->message)->toBe('')
            ->and($result->data)->toBeEmpty();
    });

    it('creates failed result', function (): void {
        $result = StageResult::failure('Payment failed', ['payment' => 'Card declined']);

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Payment failed')
            ->and($result->errors)->toBe(['payment' => 'Card declined'])
            ->and($result->data)->toBeEmpty();
    });

    it('creates failed result with defaults', function (): void {
        $result = StageResult::failure('Something went wrong');

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Something went wrong')
            ->and($result->errors)->toBeEmpty();
    });
});

<?php

declare(strict_types=1);

use AIArmada\Checkout\Exceptions\CheckoutException;
use AIArmada\Checkout\Exceptions\CheckoutStepException;
use AIArmada\Checkout\Exceptions\InvalidCheckoutStateException;
use AIArmada\Checkout\Exceptions\InventoryException;
use AIArmada\Checkout\Exceptions\MissingPaymentGatewayException;
use AIArmada\Checkout\Exceptions\PaymentException;

describe('CheckoutException', function (): void {
    it('can be created with message and context', function (): void {
        $exception = new CheckoutException('Test error', ['key' => 'value']);

        expect($exception->getMessage())->toBe('Test error')
            ->and($exception->context)->toBe(['key' => 'value']);
    });

    it('can be created via static make method', function (): void {
        $exception = CheckoutException::make('Static error', ['foo' => 'bar']);

        expect($exception->getMessage())->toBe('Static error')
            ->and($exception->context)->toBe(['foo' => 'bar']);
    });
});

describe('InvalidCheckoutStateException', function (): void {
    it('creates session expired exception', function (): void {
        $exception = InvalidCheckoutStateException::sessionExpired('session_123');

        expect($exception->getMessage())->toContain('session_123')
            ->and($exception->getMessage())->toContain('expired')
            ->and($exception->context)->toHaveKey('session_id');
    });

    it('creates session not found exception', function (): void {
        $exception = InvalidCheckoutStateException::sessionNotFound('session_456');

        expect($exception->getMessage())->toContain('session_456')
            ->and($exception->getMessage())->toContain('not found')
            ->and($exception->context['session_id'])->toBe('session_456');
    });

    it('creates cannot modify exception', function (): void {
        $exception = InvalidCheckoutStateException::cannotModify('session_789', 'completed');

        expect($exception->getMessage())->toContain('Cannot modify')
            ->and($exception->getMessage())->toContain('session_789')
            ->and($exception->context['status'])->toBe('completed');
    });

    it('creates cannot cancel exception', function (): void {
        $exception = InvalidCheckoutStateException::cannotCancel('session_101', 'completed');

        expect($exception->getMessage())->toContain('Cannot cancel')
            ->and($exception->getMessage())->toContain('session_101')
            ->and($exception->context['status'])->toBe('completed');
    });

    it('creates cart not found exception', function (): void {
        $exception = InvalidCheckoutStateException::cartNotFound('cart_123');

        expect($exception->getMessage())->toContain('cart_123')
            ->and($exception->context['cart_id'])->toBe('cart_123');
    });

    it('creates empty cart exception', function (): void {
        $exception = InvalidCheckoutStateException::emptyCart('cart_456');

        expect($exception->getMessage())->toContain('empty')
            ->and($exception->context['cart_id'])->toBe('cart_456');
    });
});

describe('CheckoutStepException', function (): void {
    it('creates step not found exception', function (): void {
        $exception = CheckoutStepException::stepNotFound('payment');

        expect($exception->getMessage())->toContain('payment')
            ->and($exception->getMessage())->toContain('not found')
            ->and($exception->context['step_identifier'])->toBe('payment');
    });

    it('creates validation failed exception', function (): void {
        $errors = ['field' => 'error'];
        $exception = CheckoutStepException::stepValidationFailed('validation', $errors);

        expect($exception->getMessage())->toContain('Validation failed')
            ->and($exception->getMessage())->toContain('validation')
            ->and($exception->context['errors'])->toBe($errors);
    });

    it('creates dependency not met exception', function (): void {
        $exception = CheckoutStepException::dependencyNotMet('payment', 'pricing');

        expect($exception->getMessage())->toContain('payment')
            ->and($exception->getMessage())->toContain('pricing')
            ->and($exception->context['dependency'])->toBe('pricing');
    });

    it('creates step already completed exception', function (): void {
        $exception = CheckoutStepException::stepAlreadyCompleted('validation');

        expect($exception->getMessage())->toContain('already been completed')
            ->and($exception->context['step_identifier'])->toBe('validation');
    });
});

describe('MissingPaymentGatewayException', function (): void {
    it('creates no gateway installed exception', function (): void {
        $exception = MissingPaymentGatewayException::noGatewayInstalled();

        expect($exception->getMessage())->toContain('No payment gateway')
            ->and($exception->context['available_packages'])->toBeArray();
    });

    it('creates gateway not found exception', function (): void {
        $exception = MissingPaymentGatewayException::gatewayNotFound('stripe');

        expect($exception->getMessage())->toContain('stripe')
            ->and($exception->getMessage())->toContain('not available')
            ->and($exception->context['requested_gateway'])->toBe('stripe');
    });
});

describe('PaymentException', function (): void {
    it('creates payment failed exception', function (): void {
        $exception = PaymentException::paymentFailed('Card declined', 'pay_123');

        expect($exception->getMessage())->toContain('Card declined')
            ->and($exception->context['payment_id'])->toBe('pay_123');
    });

    it('creates retry limit exceeded exception', function (): void {
        $exception = PaymentException::retryLimitExceeded(3, 3);

        expect($exception->getMessage())->toContain('retry limit')
            ->and($exception->context['attempts'])->toBe(3)
            ->and($exception->context['limit'])->toBe(3);
    });

    it('creates invalid payment state exception', function (): void {
        $exception = PaymentException::invalidPaymentState('pending', 'completed');

        expect($exception->getMessage())->toContain('pending')
            ->and($exception->getMessage())->toContain('completed')
            ->and($exception->context['current_state'])->toBe('pending');
    });

    it('creates refund failed exception', function (): void {
        $exception = PaymentException::refundFailed('pay_123', 'Insufficient balance');

        expect($exception->getMessage())->toContain('pay_123')
            ->and($exception->getMessage())->toContain('Insufficient balance')
            ->and($exception->context['reason'])->toBe('Insufficient balance');
    });
});

describe('InventoryException', function (): void {
    it('creates insufficient stock exception', function (): void {
        $exception = InventoryException::insufficientStock('prod_123', 5, 2);

        expect($exception->getMessage())->toContain('prod_123')
            ->and($exception->context['requested'])->toBe(5)
            ->and($exception->context['available'])->toBe(2);
    });

    it('creates reservation failed exception', function (): void {
        $exception = InventoryException::reservationFailed('prod_456', 'Timeout');

        expect($exception->getMessage())->toContain('prod_456')
            ->and($exception->getMessage())->toContain('Timeout')
            ->and($exception->context['reason'])->toBe('Timeout');
    });

    it('creates reservation expired exception', function (): void {
        $exception = InventoryException::reservationExpired('res_789');

        expect($exception->getMessage())->toContain('res_789')
            ->and($exception->getMessage())->toContain('expired')
            ->and($exception->context['reservation_id'])->toBe('res_789');
    });

    it('creates release failed exception', function (): void {
        $exception = InventoryException::releaseFailed('res_101', 'Not found');

        expect($exception->getMessage())->toContain('res_101')
            ->and($exception->context['reason'])->toBe('Not found');
    });
});

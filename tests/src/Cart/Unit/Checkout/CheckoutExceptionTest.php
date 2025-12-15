<?php

declare(strict_types=1);

use AIArmada\Cart\Checkout\Exceptions\CheckoutException;

describe('CheckoutException', function (): void {
    it('creates stage failed exception', function (): void {
        $exception = CheckoutException::stageFailed('validation', 'Invalid cart');

        expect($exception->getMessage())->toContain('validation')
            ->and($exception->getMessage())->toContain('Invalid cart')
            ->and($exception->getStageName())->toBe('validation')
            ->and($exception->getStageErrors())->toBeEmpty();
    });

    it('creates stage failed exception with previous exception', function (): void {
        $previous = new RuntimeException('Original error');
        $exception = CheckoutException::stageFailed('payment', 'Gateway error', $previous);

        expect($exception->getPrevious())->toBe($previous)
            ->and($exception->getStageName())->toBe('payment');
    });

    it('creates validation failed exception', function (): void {
        $errors = [
            'item-1' => 'Out of stock',
            'item-2' => 'Price changed',
        ];

        $exception = CheckoutException::validationFailed($errors);

        expect($exception->getStageName())->toBe('validation')
            ->and($exception->getStageErrors())->toBe($errors)
            ->and($exception->getMessage())->toContain('Out of stock')
            ->and($exception->getMessage())->toContain('Price changed');
    });

    it('creates empty cart exception', function (): void {
        $exception = CheckoutException::emptyCart();

        expect($exception->getMessage())->toContain('empty cart')
            ->and($exception->getStageName())->toBe('validation')
            ->and($exception->getStageErrors())->toBeEmpty();
    });

    it('creates reservation failed exception', function (): void {
        $exception = CheckoutException::reservationFailed('Widget Pro', 10, 3);

        expect($exception->getMessage())->toContain('Widget Pro')
            ->and($exception->getMessage())->toContain('10')
            ->and($exception->getMessage())->toContain('3')
            ->and($exception->getStageName())->toBe('reservation');
    });

    it('creates payment failed exception', function (): void {
        $exception = CheckoutException::paymentFailed('Card declined');

        expect($exception->getMessage())->toContain('Card declined')
            ->and($exception->getStageName())->toBe('payment');
    });

    it('creates payment failed exception with gateway code', function (): void {
        $exception = CheckoutException::paymentFailed('Insufficient funds', 'ERR_001');

        expect($exception->getMessage())->toContain('ERR_001')
            ->and($exception->getMessage())->toContain('Insufficient funds')
            ->and($exception->getStageName())->toBe('payment');
    });
});

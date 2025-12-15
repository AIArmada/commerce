<?php

declare(strict_types=1);

use AIArmada\Cart\Contracts\CartValidationResult;
use AIArmada\Cart\Exceptions\ProductNotPurchasableException;

describe('CartValidationResult', function (): void {
    it('can be instantiated with required parameters', function (): void {
        $result = new CartValidationResult(isValid: true);

        expect($result->isValid)->toBeTrue()
            ->and($result->message)->toBeNull()
            ->and($result->errors)->toBeEmpty()
            ->and($result->metadata)->toBeEmpty();
    });

    it('can be instantiated with all parameters', function (): void {
        $result = new CartValidationResult(
            isValid: false,
            message: 'Cart validation failed',
            errors: ['item-1' => 'Out of stock'],
            metadata: ['checked_at' => '2024-01-15']
        );

        expect($result->isValid)->toBeFalse()
            ->and($result->message)->toBe('Cart validation failed')
            ->and($result->errors)->toBe(['item-1' => 'Out of stock'])
            ->and($result->metadata)->toBe(['checked_at' => '2024-01-15']);
    });

    it('creates valid result', function (): void {
        $result = CartValidationResult::valid();

        expect($result->isValid)->toBeTrue()
            ->and($result->message)->toBeNull()
            ->and($result->errors)->toBeEmpty();
    });

    it('creates invalid result', function (): void {
        $result = CartValidationResult::invalid(
            message: 'Validation failed',
            errors: ['item-1' => 'Error 1', 'item-2' => 'Error 2'],
            metadata: ['timestamp' => 'now']
        );

        expect($result->isValid)->toBeFalse()
            ->and($result->message)->toBe('Validation failed')
            ->and($result->errors)->toHaveCount(2)
            ->and($result->metadata)->toBe(['timestamp' => 'now']);
    });

    it('creates invalid result with defaults', function (): void {
        $result = CartValidationResult::invalid('Simple error');

        expect($result->isValid)->toBeFalse()
            ->and($result->message)->toBe('Simple error')
            ->and($result->errors)->toBeEmpty()
            ->and($result->metadata)->toBeEmpty();
    });

    it('checks for item errors', function (): void {
        $withErrors = CartValidationResult::invalid('Error', ['item-1' => 'Problem']);
        $noErrors = CartValidationResult::invalid('Error');

        expect($withErrors->hasItemErrors())->toBeTrue()
            ->and($noErrors->hasItemErrors())->toBeFalse();
    });

    it('gets specific item error', function (): void {
        $result = CartValidationResult::invalid('Error', [
            'item-1' => 'Out of stock',
            'item-2' => 'Price changed',
        ]);

        expect($result->getItemError('item-1'))->toBe('Out of stock')
            ->and($result->getItemError('item-2'))->toBe('Price changed')
            ->and($result->getItemError('item-3'))->toBeNull();
    });
});

describe('ProductNotPurchasableException', function (): void {
    it('can be instantiated with required parameters', function (): void {
        $exception = new ProductNotPurchasableException(
            productId: 'prod-123',
            productName: 'Test Product',
            reason: 'Out of stock'
        );

        expect($exception->productId)->toBe('prod-123')
            ->and($exception->productName)->toBe('Test Product')
            ->and($exception->reason)->toBe('Out of stock')
            ->and($exception->requestedQuantity)->toBeNull()
            ->and($exception->availableStock)->toBeNull()
            ->and($exception->getMessage())->toContain('Test Product')
            ->and($exception->getMessage())->toContain('Out of stock');
    });

    it('includes quantity info in message when provided', function (): void {
        $exception = new ProductNotPurchasableException(
            productId: 'prod-123',
            productName: 'Test Product',
            reason: 'Insufficient stock',
            requestedQuantity: 10,
            availableStock: 5
        );

        expect($exception->getMessage())->toContain('requested: 10')
            ->and($exception->getMessage())->toContain('available: 5');
    });

    it('creates out of stock exception', function (): void {
        $exception = ProductNotPurchasableException::outOfStock(
            'prod-456',
            'Widget',
            10,
            3
        );

        expect($exception->productId)->toBe('prod-456')
            ->and($exception->productName)->toBe('Widget')
            ->and($exception->reason)->toBe('Insufficient stock')
            ->and($exception->requestedQuantity)->toBe(10)
            ->and($exception->availableStock)->toBe(3);
    });

    it('creates inactive product exception', function (): void {
        $exception = ProductNotPurchasableException::inactive('prod-789', 'Discontinued Item');

        expect($exception->productId)->toBe('prod-789')
            ->and($exception->productName)->toBe('Discontinued Item')
            ->and($exception->reason)->toBe('Product is not available for purchase')
            ->and($exception->requestedQuantity)->toBeNull();
    });

    it('creates minimum not met exception', function (): void {
        $exception = ProductNotPurchasableException::minimumNotMet(
            'prod-min',
            'Bulk Item',
            2,
            5
        );

        expect($exception->productId)->toBe('prod-min')
            ->and($exception->reason)->toContain('Minimum quantity is 5')
            ->and($exception->requestedQuantity)->toBe(2);
    });

    it('creates maximum exceeded exception', function (): void {
        $exception = ProductNotPurchasableException::maximumExceeded(
            'prod-max',
            'Limited Item',
            100,
            10
        );

        expect($exception->productId)->toBe('prod-max')
            ->and($exception->reason)->toContain('Maximum quantity is 10')
            ->and($exception->requestedQuantity)->toBe(100);
    });

    it('creates invalid increment exception', function (): void {
        $exception = ProductNotPurchasableException::invalidIncrement(
            'prod-inc',
            'Pack Item',
            5,
            3
        );

        expect($exception->productId)->toBe('prod-inc')
            ->and($exception->reason)->toContain('increments of 3')
            ->and($exception->requestedQuantity)->toBe(5);
    });
});

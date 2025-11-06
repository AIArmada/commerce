<?php

declare(strict_types=1);

use AIArmada\Cart\Exceptions\CartConflictException;

describe('CartConflictException', function (): void {
    it('creates exception with version info', function (): void {
        $exception = new CartConflictException(
            'Cart conflict detected',
            attemptedVersion: 5,
            currentVersion: 10
        );

        expect($exception)->toBeInstanceOf(CartConflictException::class);
        expect($exception->getMessage())->toBe('Cart conflict detected');
        expect($exception->getAttemptedVersion())->toBe(5);
        expect($exception->getCurrentVersion())->toBe(10);
        expect($exception->getCode())->toBe(409);
    });

    it('calculates version difference', function (): void {
        $exception = new CartConflictException(
            'Version conflict',
            attemptedVersion: 3,
            currentVersion: 8
        );

        expect($exception->getVersionDifference())->toBe(5);
    });

    it('identifies minor conflict', function (): void {
        $exception = new CartConflictException(
            'Minor conflict',
            attemptedVersion: 9,
            currentVersion: 10
        );

        expect($exception->isMinorConflict())->toBeTrue();
    });

    it('identifies major conflict', function (): void {
        $exception = new CartConflictException(
            'Major conflict',
            attemptedVersion: 5,
            currentVersion: 10
        );

        expect($exception->isMinorConflict())->toBeFalse();
    });

    it('stores conflicted cart', function (): void {
        $manager = app(AIArmada\Cart\CartManager::class);
        $cart = $manager->getCartInstance('test-cart');

        $exception = new CartConflictException(
            'Conflict',
            attemptedVersion: 1,
            currentVersion: 2,
            conflictedCart: $cart
        );

        expect($exception->getConflictedCart())->toBe($cart);
    });

    it('stores conflicted data', function (): void {
        $data = ['items' => [], 'total' => 100.00];
        $exception = new CartConflictException(
            'Conflict',
            attemptedVersion: 1,
            currentVersion: 2,
            conflictedData: $data
        );

        expect($exception->getConflictedData())->toBe($data);
    });

    it('provides resolution suggestions for minor conflict', function (): void {
        $exception = new CartConflictException(
            'Minor conflict',
            attemptedVersion: 9,
            currentVersion: 10
        );

        $suggestions = $exception->getResolutionSuggestions();

        expect($suggestions)->toContain('retry_with_refresh');
        expect($suggestions)->toContain('merge_changes');
    });

    it('provides resolution suggestions for major conflict', function (): void {
        $exception = new CartConflictException(
            'Major conflict',
            attemptedVersion: 1,
            currentVersion: 10
        );

        $suggestions = $exception->getResolutionSuggestions();

        expect($suggestions)->toContain('reload_cart');
        expect($suggestions)->toContain('manual_resolution_required');
    });

    it('includes cart comparison suggestion when cart is present', function (): void {
        $manager = app(AIArmada\Cart\CartManager::class);
        $cart = $manager->getCartInstance('test-cart');

        $exception = new CartConflictException(
            'Conflict',
            attemptedVersion: 1,
            currentVersion: 2,
            conflictedCart: $cart
        );

        $suggestions = $exception->getResolutionSuggestions();

        expect($suggestions)->toContain('compare_with_current');
    });

    it('converts to array for API responses', function (): void {
        $exception = new CartConflictException(
            'Cart conflict',
            attemptedVersion: 5,
            currentVersion: 10
        );

        $array = $exception->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKey('error', 'cart_conflict');
        expect($array)->toHaveKey('message', 'Cart conflict');
        expect($array)->toHaveKey('attempted_version', 5);
        expect($array)->toHaveKey('current_version', 10);
        expect($array)->toHaveKey('version_difference', 5);
        expect($array)->toHaveKey('is_minor_conflict');
        expect($array)->toHaveKey('resolution_suggestions');
        expect($array)->toHaveKey('timestamp');
    });

    it('handles zero version difference', function (): void {
        $exception = new CartConflictException(
            'Same version',
            attemptedVersion: 5,
            currentVersion: 5
        );

        expect($exception->getVersionDifference())->toBe(0);
        expect($exception->isMinorConflict())->toBeFalse();
    });

    it('handles previous exception', function (): void {
        $previous = new Exception('Previous error');
        $exception = new CartConflictException(
            'Conflict',
            attemptedVersion: 1,
            currentVersion: 2,
            previous: $previous
        );

        expect($exception->getPrevious())->toBe($previous);
    });
});

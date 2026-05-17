<?php

declare(strict_types=1);

use AIArmada\Shipping\Services\FreeShippingResult;

describe('FreeShippingResult', function (): void {
    it('can create free shipping result with required fields', function (): void {
        $result = new FreeShippingResult(
            applies: true,
            message: 'Free shipping applied!',
            remainingAmount: 500,
            nearThreshold: true
        );

        expect($result->applies)->toBeTrue();
        expect($result->message)->toBe('Free shipping applied!');
        expect($result->remainingAmount)->toBe(500);
        expect($result->nearThreshold)->toBeTrue();
    });

    it('can create free shipping result with minimal fields', function (): void {
        $result = new FreeShippingResult(applies: false);

        expect($result->applies)->toBeFalse();
        expect($result->message)->toBeNull();
        expect($result->remainingAmount)->toBeNull();
        expect($result->nearThreshold)->toBeFalse();
    });

    it('formats remaining amount correctly', function (): void {
        $result = new FreeShippingResult(
            applies: false,
            remainingAmount: 2500, // RM25.00 — default currency from config
        );

        expect($result->getFormattedRemaining())->toBe('RM25.00');
    });

    it('returns null when no remaining amount', function (): void {
        $result = new FreeShippingResult(applies: true);

        expect($result->getFormattedRemaining())->toBeNull();
    });
});

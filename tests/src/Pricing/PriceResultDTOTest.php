<?php

declare(strict_types=1);

use AIArmada\Pricing\Data\PriceResultData;

describe('PriceResultData', function (): void {
    describe('hasDiscount', function (): void {
        it('returns true when there is a discount', function (): void {
            $result = new PriceResultData(
                originalPrice: 10000,
                finalPrice: 8000,
                discountAmount: 2000,
                discountSource: 'Promotion',
            );

            expect($result->hasDiscount())->toBeTrue();
        });

        it('returns false when there is no discount', function (): void {
            $result = new PriceResultData(
                originalPrice: 10000,
                finalPrice: 10000,
                discountAmount: 0,
            );

            expect($result->hasDiscount())->toBeFalse();
        });
    });

    describe('getFormattedSavings', function (): void {
        it('formats savings correctly', function (): void {
            $result = new PriceResultData(
                originalPrice: 10000,
                finalPrice: 8000,
                discountAmount: 2000,
            );

            expect($result->getFormattedSavings())->toBe('RM 20.00');
        });

        it('formats zero savings', function (): void {
            $result = new PriceResultData(
                originalPrice: 10000,
                finalPrice: 10000,
                discountAmount: 0,
            );

            expect($result->getFormattedSavings())->toBe('RM 0.00');
        });
    });

    describe('getFormattedFinalPrice', function (): void {
        it('formats final price correctly', function (): void {
            $result = new PriceResultData(
                originalPrice: 10000,
                finalPrice: 8500,
                discountAmount: 1500,
            );

            expect($result->getFormattedFinalPrice())->toBe('RM 85.00');
        });

        it('formats large amounts', function (): void {
            $result = new PriceResultData(
                originalPrice: 1000000,
                finalPrice: 999999,
                discountAmount: 1,
            );

            expect($result->getFormattedFinalPrice())->toBe('RM 9,999.99');
        });
    });

    describe('getFormattedOriginalPrice', function (): void {
        it('formats original price correctly', function (): void {
            $result = new PriceResultData(
                originalPrice: 15000,
                finalPrice: 12000,
                discountAmount: 3000,
            );

            expect($result->getFormattedOriginalPrice())->toBe('RM 150.00');
        });
    });

    describe('constructor and properties', function (): void {
        it('stores all properties correctly', function (): void {
            $breakdown = [['type' => 'base', 'price' => 10000]];

            $result = new PriceResultData(
                originalPrice: 10000,
                finalPrice: 8000,
                discountAmount: 2000,
                discountSource: 'Tier Pricing',
                discountPercentage: 20.0,
                priceListName: 'Retail',
                tierDescription: '10+ units',
                promotionName: 'Summer Sale',
                breakdown: $breakdown,
            );

            expect($result->originalPrice)->toBe(10000)
                ->and($result->finalPrice)->toBe(8000)
                ->and($result->discountAmount)->toBe(2000)
                ->and($result->discountSource)->toBe('Tier Pricing')
                ->and($result->discountPercentage)->toBe(20.0)
                ->and($result->priceListName)->toBe('Retail')
                ->and($result->tierDescription)->toBe('10+ units')
                ->and($result->promotionName)->toBe('Summer Sale')
                ->and($result->breakdown)->toBe($breakdown);
        });

        it('handles null optional properties', function (): void {
            $result = new PriceResultData(
                originalPrice: 5000,
                finalPrice: 5000,
                discountAmount: 0,
            );

            expect($result->discountSource)->toBeNull()
                ->and($result->discountPercentage)->toBeNull()
                ->and($result->priceListName)->toBeNull()
                ->and($result->tierDescription)->toBeNull()
                ->and($result->promotionName)->toBeNull()
                ->and($result->breakdown)->toBe([]);
        });
    });
});

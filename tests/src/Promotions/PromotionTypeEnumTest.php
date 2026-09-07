<?php

declare(strict_types=1);

use AIArmada\Promotions\Enums\PromotionType;

describe('PromotionType Enum', function (): void {
    describe('label method', function (): void {
        it('returns correct label for Percentage', function (): void {
            expect(PromotionType::Percentage->label())->toBe('Percentage Off');
        });

        it('returns correct label for Fixed', function (): void {
            expect(PromotionType::Fixed->label())->toBe('Fixed Amount');
        });

    });

    describe('icon method', function (): void {
        it('returns correct icon for Percentage', function (): void {
            expect(PromotionType::Percentage->icon())->toBe('heroicon-o-receipt-percent');
        });

        it('returns correct icon for Fixed', function (): void {
            expect(PromotionType::Fixed->icon())->toBe('heroicon-o-currency-dollar');
        });

    });

    describe('color method', function (): void {
        it('returns correct color for Percentage', function (): void {
            expect(PromotionType::Percentage->color())->toBe('success');
        });

        it('returns correct color for Fixed', function (): void {
            expect(PromotionType::Fixed->color())->toBe('primary');
        });

    });

    describe('formatValue method', function (): void {
        it('formats percentage value correctly', function (): void {
            expect(PromotionType::Percentage->formatValue(20))->toBe('20%');
            expect(PromotionType::Percentage->formatValue(10))->toBe('10%');
            expect(PromotionType::Percentage->formatValue(0))->toBe('0%');
        });

        it('formats fixed value correctly', function (): void {
            expect(PromotionType::Fixed->formatValue(1000))->toBe('$10.00');
            expect(PromotionType::Fixed->formatValue(2500))->toBe('$25.00');
            expect(PromotionType::Fixed->formatValue(99))->toBe('$0.99');
        });

    });

    describe('enum values', function (): void {
        it('has correct string values', function (): void {
            expect(PromotionType::Percentage->value)->toBe('percentage');
            expect(PromotionType::Fixed->value)->toBe('fixed');
        });

        it('can be created from string', function (): void {
            expect(PromotionType::from('percentage'))->toBe(PromotionType::Percentage);
            expect(PromotionType::from('fixed'))->toBe(PromotionType::Fixed);
        });

        it('returns null for invalid value with tryFrom', function (): void {
            expect(PromotionType::tryFrom('invalid'))->toBeNull();
        });
    });
});

<?php

declare(strict_types=1);

use AIArmada\Promotions\Enums\PromotionType;

describe('PromotionType Enum', function (): void {
    describe('values', function (): void {
        it('has three cases', function (): void {
            expect(PromotionType::cases())->toHaveCount(3);
        });

        it('has Percentage case', function (): void {
            expect(PromotionType::Percentage->value)->toBe('percentage');
        });

        it('has Fixed case', function (): void {
            expect(PromotionType::Fixed->value)->toBe('fixed');
        });

        it('has BuyXGetY case', function (): void {
            expect(PromotionType::BuyXGetY->value)->toBe('buy_x_get_y');
        });
    });

    describe('label', function (): void {
        it('returns label for Percentage', function (): void {
            expect(PromotionType::Percentage->label())->toBe('Percentage Off');
        });

        it('returns label for Fixed', function (): void {
            expect(PromotionType::Fixed->label())->toBe('Fixed Amount');
        });

        it('returns label for BuyXGetY', function (): void {
            expect(PromotionType::BuyXGetY->label())->toBe('Buy X Get Y');
        });
    });

    describe('icon', function (): void {
        it('returns icon for Percentage', function (): void {
            expect(PromotionType::Percentage->icon())->toBeString();
        });

        it('returns icon for Fixed', function (): void {
            expect(PromotionType::Fixed->icon())->toBeString();
        });

        it('returns icon for BuyXGetY', function (): void {
            expect(PromotionType::BuyXGetY->icon())->toBeString();
        });
    });

    describe('color', function (): void {
        it('returns success color for Percentage', function (): void {
            expect(PromotionType::Percentage->color())->toBe('success');
        });

        it('returns primary color for Fixed', function (): void {
            expect(PromotionType::Fixed->color())->toBe('primary');
        });

        it('returns warning color for BuyXGetY', function (): void {
            expect(PromotionType::BuyXGetY->color())->toBe('warning');
        });
    });
});

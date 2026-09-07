<?php

declare(strict_types=1);

use AIArmada\Promotions\Enums\PromotionType;

describe('PromotionType Enum', function (): void {
    describe('values', function (): void {
        it('has two cases', function (): void {
            expect(PromotionType::cases())->toHaveCount(2);
        });

        it('has Percentage case', function (): void {
            expect(PromotionType::Percentage->value)->toBe('percentage');
        });

        it('has Fixed case', function (): void {
            expect(PromotionType::Fixed->value)->toBe('fixed');
        });

    });

    describe('label', function (): void {
        it('returns label for Percentage', function (): void {
            expect(PromotionType::Percentage->label())->toBe('Percentage Off');
        });

        it('returns label for Fixed', function (): void {
            expect(PromotionType::Fixed->label())->toBe('Fixed Amount');
        });

    });

    describe('icon', function (): void {
        it('returns icon for Percentage', function (): void {
            expect(PromotionType::Percentage->icon())->toBeString();
        });

        it('returns icon for Fixed', function (): void {
            expect(PromotionType::Fixed->icon())->toBeString();
        });

    });

    describe('color', function (): void {
        it('returns success color for Percentage', function (): void {
            expect(PromotionType::Percentage->color())->toBe('success');
        });

        it('returns primary color for Fixed', function (): void {
            expect(PromotionType::Fixed->color())->toBe('primary');
        });

    });
});

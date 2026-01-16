<?php

declare(strict_types=1);

use AIArmada\FilamentPromotions\Enums\PromotionType;
use AIArmada\Promotions\Enums\PromotionType as BasePromotionType;

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

    describe('getLabel', function (): void {
        it('returns label for Percentage', function (): void {
            expect(PromotionType::Percentage->getLabel())->toBe('Percentage Off');
        });

        it('returns label for Fixed', function (): void {
            expect(PromotionType::Fixed->getLabel())->toBe('Fixed Amount');
        });

        it('returns label for BuyXGetY', function (): void {
            expect(PromotionType::BuyXGetY->getLabel())->toBe('Buy X Get Y');
        });
    });

    describe('getIcon', function (): void {
        it('returns icon for Percentage', function (): void {
            expect(PromotionType::Percentage->getIcon())->toBeString();
        });

        it('returns icon for Fixed', function (): void {
            expect(PromotionType::Fixed->getIcon())->toBeString();
        });

        it('returns icon for BuyXGetY', function (): void {
            expect(PromotionType::BuyXGetY->getIcon())->toBeString();
        });
    });

    describe('getColor', function (): void {
        it('returns success color for Percentage', function (): void {
            expect(PromotionType::Percentage->getColor())->toBe('success');
        });

        it('returns info color for Fixed', function (): void {
            expect(PromotionType::Fixed->getColor())->toBe('info');
        });

        it('returns warning color for BuyXGetY', function (): void {
            expect(PromotionType::BuyXGetY->getColor())->toBe('warning');
        });
    });

    describe('fromBase', function (): void {
        it('converts Percentage from base', function (): void {
            $base = BasePromotionType::Percentage;
            $filament = PromotionType::fromBase($base);

            expect($filament)->toBe(PromotionType::Percentage);
        });

        it('converts Fixed from base', function (): void {
            $base = BasePromotionType::Fixed;
            $filament = PromotionType::fromBase($base);

            expect($filament)->toBe(PromotionType::Fixed);
        });

        it('converts BuyXGetY from base', function (): void {
            $base = BasePromotionType::BuyXGetY;
            $filament = PromotionType::fromBase($base);

            expect($filament)->toBe(PromotionType::BuyXGetY);
        });
    });

    describe('toBase', function (): void {
        it('converts Percentage to base', function (): void {
            $filament = PromotionType::Percentage;
            $base = $filament->toBase();

            expect($base)->toBe(BasePromotionType::Percentage);
        });

        it('converts Fixed to base', function (): void {
            $filament = PromotionType::Fixed;
            $base = $filament->toBase();

            expect($base)->toBe(BasePromotionType::Fixed);
        });

        it('converts BuyXGetY to base', function (): void {
            $filament = PromotionType::BuyXGetY;
            $base = $filament->toBase();

            expect($base)->toBe(BasePromotionType::BuyXGetY);
        });
    });
});

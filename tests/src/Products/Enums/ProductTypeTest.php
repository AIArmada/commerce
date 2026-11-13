<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductType;

describe('ProductType Enum', function (): void {
    describe('Values', function (): void {
        it('has all expected values', function (): void {
            expect(ProductType::Simple->value)->toBe('simple')
                ->and(ProductType::Configurable->value)->toBe('configurable')
                ->and(ProductType::Composite->value)->toBe('composite')
                ->and(ProductType::Digital->value)->toBe('digital')
                ->and(ProductType::Subscription->value)->toBe('subscription');
        });

        it('can be created from string', function (): void {
            expect(ProductType::from('simple'))->toBe(ProductType::Simple)
                ->and(ProductType::from('configurable'))->toBe(ProductType::Configurable)
                ->and(ProductType::from('composite'))->toBe(ProductType::Composite)
                ->and(ProductType::from('digital'))->toBe(ProductType::Digital)
                ->and(ProductType::from('subscription'))->toBe(ProductType::Subscription);
        });

        it('no longer contains the catalog bundle type', function (): void {
            expect(ProductType::tryFrom('bundle'))->toBeNull();
        });
    });

    describe('label()', function (): void {
        it('returns translation key for simple', function (): void {
            expect(ProductType::Simple->label())->not->toBeEmpty();
        });

        it('returns translation key for configurable', function (): void {
            expect(ProductType::Configurable->label())->not->toBeEmpty();
        });

        it('returns translation key for composite', function (): void {
            expect(ProductType::Composite->label())->not->toBeEmpty();
        });

        it('returns translation key for digital', function (): void {
            expect(ProductType::Digital->label())->not->toBeEmpty();
        });

        it('returns translation key for subscription', function (): void {
            expect(ProductType::Subscription->label())->not->toBeEmpty();
        });
    });

    describe('supportsVariantsByDefault()', function (): void {
        it('returns true for configurable', function (): void {
            expect(ProductType::Configurable->supportsVariantsByDefault())->toBeTrue();
        });

        it('returns false for simple', function (): void {
            expect(ProductType::Simple->supportsVariantsByDefault())->toBeFalse();
        });

        it('returns false for composite', function (): void {
            expect(ProductType::Composite->supportsVariantsByDefault())->toBeFalse();
        });

        it('returns false for digital', function (): void {
            expect(ProductType::Digital->supportsVariantsByDefault())->toBeFalse();
        });

        it('returns false for subscription', function (): void {
            expect(ProductType::Subscription->supportsVariantsByDefault())->toBeFalse();
        });

        it('keeps hasVariants as an alias for default variant support', function (): void {
            expect(ProductType::Configurable->hasVariants())->toBeTrue()
                ->and(ProductType::Digital->hasVariants())->toBeFalse();
        });
    });

    describe('tracksInventoryByDefault()', function (): void {
        it('returns true for simple', function (): void {
            expect(ProductType::Simple->tracksInventoryByDefault())->toBeTrue();
        });

        it('returns true for configurable', function (): void {
            expect(ProductType::Configurable->tracksInventoryByDefault())->toBeTrue();
        });

        it('returns false for composite parents', function (): void {
            expect(ProductType::Composite->tracksInventoryByDefault())->toBeFalse();
        });

        it('returns false for digital', function (): void {
            expect(ProductType::Digital->tracksInventoryByDefault())->toBeFalse();
        });
    });

    describe('requiresShippingByDefault()', function (): void {
        it('returns true for physical types', function (): void {
            expect(ProductType::Simple->requiresShippingByDefault())->toBeTrue()
                ->and(ProductType::Configurable->requiresShippingByDefault())->toBeTrue()
                ->and(ProductType::Composite->requiresShippingByDefault())->toBeTrue();
        });

        it('returns false for digital fulfillment types', function (): void {
            expect(ProductType::Digital->requiresShippingByDefault())->toBeFalse()
                ->and(ProductType::Subscription->requiresShippingByDefault())->toBeFalse();
        });
    });

    describe('isPhysical()', function (): void {
        it('returns true for simple', function (): void {
            expect(ProductType::Simple->isPhysical())->toBeTrue();
        });

        it('returns true for configurable', function (): void {
            expect(ProductType::Configurable->isPhysical())->toBeTrue();
        });

        it('returns true for composite', function (): void {
            expect(ProductType::Composite->isPhysical())->toBeTrue();
        });

        it('returns false for digital', function (): void {
            expect(ProductType::Digital->isPhysical())->toBeFalse();
        });

        it('returns false for subscription', function (): void {
            expect(ProductType::Subscription->isPhysical())->toBeFalse();
        });
    });

    describe('isComposite()', function (): void {
        it('only returns true for composite', function (): void {
            expect(ProductType::Composite->isComposite())->toBeTrue()
                ->and(ProductType::Simple->isComposite())->toBeFalse();
        });
    });

    describe('icon()', function (): void {
        it('returns correct icon for simple', function (): void {
            expect(ProductType::Simple->icon())->toBe('heroicon-o-cube');
        });

        it('returns correct icon for configurable', function (): void {
            expect(ProductType::Configurable->icon())->toBe('heroicon-o-squares-2x2');
        });

        it('returns correct icon for composite', function (): void {
            expect(ProductType::Composite->icon())->toBe('heroicon-o-cube-transparent');
        });

        it('returns correct icon for digital', function (): void {
            expect(ProductType::Digital->icon())->toBe('heroicon-o-cloud-arrow-down');
        });

        it('returns correct icon for subscription', function (): void {
            expect(ProductType::Subscription->icon())->toBe('heroicon-o-arrow-path');
        });
    });
});

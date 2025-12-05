<?php

declare(strict_types=1);

use AIArmada\Vouchers\Compound\Enums\ProductMatcherType;

describe('ProductMatcherType Enum', function (): void {
    it('has all expected types', function (): void {
        expect(ProductMatcherType::cases())->toHaveCount(6);
        expect(ProductMatcherType::Sku->value)->toBe('sku');
        expect(ProductMatcherType::Category->value)->toBe('category');
        expect(ProductMatcherType::Price->value)->toBe('price');
        expect(ProductMatcherType::Attribute->value)->toBe('attribute');
        expect(ProductMatcherType::All->value)->toBe('all');
        expect(ProductMatcherType::Any->value)->toBe('any');
    });

    describe('labels', function (): void {
        it('returns correct labels', function (): void {
            expect(ProductMatcherType::Sku->label())->toBe('SKU Match');
            expect(ProductMatcherType::Category->label())->toBe('Category Match');
            expect(ProductMatcherType::Price->label())->toBe('Price Range');
            expect(ProductMatcherType::Attribute->label())->toBe('Attribute Match');
            expect(ProductMatcherType::All->label())->toBe('Match All (AND)');
            expect(ProductMatcherType::Any->label())->toBe('Match Any (OR)');
        });
    });

    describe('isComposite method', function (): void {
        it('returns false for simple types', function (): void {
            expect(ProductMatcherType::Sku->isComposite())->toBeFalse();
            expect(ProductMatcherType::Category->isComposite())->toBeFalse();
            expect(ProductMatcherType::Price->isComposite())->toBeFalse();
            expect(ProductMatcherType::Attribute->isComposite())->toBeFalse();
        });

        it('returns true for composite types', function (): void {
            expect(ProductMatcherType::All->isComposite())->toBeTrue();
            expect(ProductMatcherType::Any->isComposite())->toBeTrue();
        });
    });
});

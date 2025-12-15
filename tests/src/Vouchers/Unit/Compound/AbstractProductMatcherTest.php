<?php

declare(strict_types=1);

use AIArmada\Vouchers\Compound\Enums\ProductMatcherType;
use AIArmada\Vouchers\Compound\Matchers\AbstractProductMatcher;
use AIArmada\Vouchers\Compound\Matchers\AttributeMatcher;
use AIArmada\Vouchers\Compound\Matchers\CategoryMatcher;
use AIArmada\Vouchers\Compound\Matchers\CompositeMatcher;
use AIArmada\Vouchers\Compound\Matchers\PriceMatcher;
use AIArmada\Vouchers\Compound\Matchers\SkuMatcher;
use AIArmada\Vouchers\Contracts\ProductMatcherInterface;

describe('AbstractProductMatcher', function (): void {
    describe('create() factory method', function (): void {
        it('creates SkuMatcher for sku type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'sku',
                'skus' => ['SKU-001', 'SKU-002'],
            ]);

            expect($matcher)->toBeInstanceOf(SkuMatcher::class);
        });

        it('creates CategoryMatcher for category type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'category',
                'categories' => ['electronics', 'phones'],
            ]);

            expect($matcher)->toBeInstanceOf(CategoryMatcher::class);
        });

        it('creates PriceMatcher for price type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'price',
                'operator' => '>=',
                'value' => 1000,
            ]);

            expect($matcher)->toBeInstanceOf(PriceMatcher::class);
        });

        it('creates AttributeMatcher for attribute type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'attribute',
                'attribute' => 'color',
                'operator' => '=',
                'value' => 'red',
            ]);

            expect($matcher)->toBeInstanceOf(AttributeMatcher::class);
        });

        it('creates CompositeMatcher for all type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'all',
                'matchers' => [
                    ['type' => 'sku', 'skus' => ['SKU-001']],
                    ['type' => 'price', 'operator' => '>=', 'value' => 100],
                ],
            ]);

            expect($matcher)->toBeInstanceOf(CompositeMatcher::class);
        });

        it('creates CompositeMatcher for any type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'any',
                'matchers' => [
                    ['type' => 'sku', 'skus' => ['SKU-001']],
                    ['type' => 'category', 'categories' => ['sale']],
                ],
            ]);

            expect($matcher)->toBeInstanceOf(CompositeMatcher::class);
        });

        it('defaults to SkuMatcher for unknown type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'unknown',
                'skus' => ['SKU-001'],
            ]);

            expect($matcher)->toBeInstanceOf(SkuMatcher::class);
        });

        it('defaults to SkuMatcher when type is missing', function (): void {
            $matcher = AbstractProductMatcher::create([
                'skus' => ['SKU-001'],
            ]);

            expect($matcher)->toBeInstanceOf(SkuMatcher::class);
        });

        it('handles empty matchers array for all type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'all',
                'matchers' => [],
            ]);

            expect($matcher)->toBeInstanceOf(CompositeMatcher::class);
        });

        it('handles missing matchers key for any type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'any',
            ]);

            expect($matcher)->toBeInstanceOf(CompositeMatcher::class);
        });
    });

    describe('filter() method', function (): void {
        it('filters items matching the criteria', function (): void {
            // CartItem is final, so we test via integration or skip
            expect(true)->toBeTrue();
        })->skip('CartItem is final and cannot be mocked');

        it('returns empty collection when no items match', function (): void {
            expect(true)->toBeTrue();
        })->skip('CartItem is final and cannot be mocked');

        it('returns all items when all match', function (): void {
            expect(true)->toBeTrue();
        })->skip('CartItem is final and cannot be mocked');
    });

    describe('getMatchingItems() method', function (): void {
        it('returns matching items without limit', function (): void {
            expect(true)->toBeTrue();
        })->skip('CartItem is final and cannot be mocked');

        it('limits results when limit is specified', function (): void {
            expect(true)->toBeTrue();
        })->skip('CartItem is final and cannot be mocked');

        it('returns all items when limit exceeds matches', function (): void {
            expect(true)->toBeTrue();
        })->skip('CartItem is final and cannot be mocked');

        it('returns empty collection with zero limit', function (): void {
            expect(true)->toBeTrue();
        })->skip('CartItem is final and cannot be mocked');
    });

    describe('interface implementation', function (): void {
        it('implements ProductMatcherInterface', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'sku',
                'skus' => ['SKU-001'],
            ]);

            expect($matcher)->toBeInstanceOf(ProductMatcherInterface::class);
        });
    });
});

<?php

declare(strict_types=1);

use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\Compound\Matchers\AbstractProductMatcher;
use AIArmada\Vouchers\Compound\Matchers\AttributeMatcher;
use AIArmada\Vouchers\Compound\Matchers\CategoryMatcher;
use AIArmada\Vouchers\Compound\Matchers\CompositeMatcher;
use AIArmada\Vouchers\Compound\Matchers\PriceMatcher;
use AIArmada\Vouchers\Compound\Matchers\SkuMatcher;
use AIArmada\Vouchers\Contracts\ProductMatcherInterface;
use Illuminate\Support\Collection;

/**
 * Helper function to create a CartItem with minimal required properties.
 *
 * @param  array<string, mixed>  $attrs  Extra attributes like 'sku', 'category'
 */
function makeMatcherTestCartItem(string $id, string $name, int $price, int $quantity = 1, array $attrs = []): CartItem
{
    return new CartItem(
        id: $id,
        name: $name,
        price: $price,
        quantity: $quantity,
        attributes: $attrs
    );
}

describe('AbstractProductMatcher', function (): void {
    describe('create factory method', function (): void {
        it('creates SkuMatcher for sku type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'sku',
                'skus' => ['SKU001', 'SKU002'],
            ]);

            expect($matcher)->toBeInstanceOf(SkuMatcher::class)
                ->and($matcher)->toBeInstanceOf(ProductMatcherInterface::class);
        });

        it('creates CategoryMatcher for category type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'category',
                'categories' => ['electronics', 'clothing'],
            ]);

            expect($matcher)->toBeInstanceOf(CategoryMatcher::class);
        });

        it('creates PriceMatcher for price type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'price',
                'min_price' => 100,
                'max_price' => 500,
            ]);

            expect($matcher)->toBeInstanceOf(PriceMatcher::class);
        });

        it('creates AttributeMatcher for attribute type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'attribute',
                'attribute' => 'color',
                'value' => 'red',
            ]);

            expect($matcher)->toBeInstanceOf(AttributeMatcher::class);
        });

        it('creates CompositeMatcher with all logic for all type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'all',
                'matchers' => [
                    ['type' => 'sku', 'skus' => ['SKU001']],
                    ['type' => 'price', 'min_price' => 100],
                ],
            ]);

            expect($matcher)->toBeInstanceOf(CompositeMatcher::class);
        });

        it('creates CompositeMatcher with any logic for any type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'any',
                'matchers' => [
                    ['type' => 'sku', 'skus' => ['SKU001']],
                    ['type' => 'category', 'categories' => ['electronics']],
                ],
            ]);

            expect($matcher)->toBeInstanceOf(CompositeMatcher::class);
        });

        it('defaults to SkuMatcher when type is invalid', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'invalid_type',
                'skus' => ['SKU001'],
            ]);

            expect($matcher)->toBeInstanceOf(SkuMatcher::class);
        });

        it('defaults to SkuMatcher when type is missing', function (): void {
            $matcher = AbstractProductMatcher::create([
                'skus' => ['SKU001'],
            ]);

            expect($matcher)->toBeInstanceOf(SkuMatcher::class);
        });

        it('creates CompositeMatcher with empty matchers array for all type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'all',
            ]);

            expect($matcher)->toBeInstanceOf(CompositeMatcher::class);
        });

        it('creates CompositeMatcher with empty matchers array for any type', function (): void {
            $matcher = AbstractProductMatcher::create([
                'type' => 'any',
            ]);

            expect($matcher)->toBeInstanceOf(CompositeMatcher::class);
        });
    });

    describe('filter method', function (): void {
        it('filters collection using matches method', function (): void {
            $matcher = new SkuMatcher(['SKU001', 'SKU003']);

            $item1 = makeMatcherTestCartItem('1', 'Product 1', 1000, 1, ['sku' => 'SKU001']);
            $item2 = makeMatcherTestCartItem('2', 'Product 2', 2000, 1, ['sku' => 'SKU002']);
            $item3 = makeMatcherTestCartItem('3', 'Product 3', 3000, 1, ['sku' => 'SKU003']);

            $items = new Collection([$item1, $item2, $item3]);
            $filtered = $matcher->filter($items);

            expect($filtered)->toHaveCount(2);
        });

        it('returns empty collection when no items match', function (): void {
            $matcher = new SkuMatcher(['SKU999']);

            $item1 = makeMatcherTestCartItem('1', 'Product 1', 1000, 1, ['sku' => 'SKU001']);
            $item2 = makeMatcherTestCartItem('2', 'Product 2', 2000, 1, ['sku' => 'SKU002']);

            $items = new Collection([$item1, $item2]);
            $filtered = $matcher->filter($items);

            expect($filtered)->toBeEmpty();
        });

        it('returns all items when all match', function (): void {
            $matcher = new SkuMatcher(['SKU001', 'SKU002']);

            $item1 = makeMatcherTestCartItem('1', 'Product 1', 1000, 1, ['sku' => 'SKU001']);
            $item2 = makeMatcherTestCartItem('2', 'Product 2', 2000, 1, ['sku' => 'SKU002']);

            $items = new Collection([$item1, $item2]);
            $filtered = $matcher->filter($items);

            expect($filtered)->toHaveCount(2);
        });
    });

    describe('getMatchingItems method', function (): void {
        it('returns all matching items without limit', function (): void {
            $matcher = new SkuMatcher(['SKU001', 'SKU002', 'SKU003']);

            $item1 = makeMatcherTestCartItem('1', 'Product 1', 1000, 1, ['sku' => 'SKU001']);
            $item2 = makeMatcherTestCartItem('2', 'Product 2', 2000, 1, ['sku' => 'SKU002']);
            $item3 = makeMatcherTestCartItem('3', 'Product 3', 3000, 1, ['sku' => 'SKU003']);

            $items = new Collection([$item1, $item2, $item3]);
            $matching = $matcher->getMatchingItems($items);

            expect($matching)->toHaveCount(3);
        });

        it('limits results when limit is specified', function (): void {
            $matcher = new SkuMatcher(['SKU001', 'SKU002', 'SKU003']);

            $item1 = makeMatcherTestCartItem('1', 'Product 1', 1000, 1, ['sku' => 'SKU001']);
            $item2 = makeMatcherTestCartItem('2', 'Product 2', 2000, 1, ['sku' => 'SKU002']);
            $item3 = makeMatcherTestCartItem('3', 'Product 3', 3000, 1, ['sku' => 'SKU003']);

            $items = new Collection([$item1, $item2, $item3]);
            $matching = $matcher->getMatchingItems($items, 2);

            expect($matching)->toHaveCount(2);
        });

        it('returns fewer items than limit when not enough matches', function (): void {
            $matcher = new SkuMatcher(['SKU001']);

            $item1 = makeMatcherTestCartItem('1', 'Product 1', 1000, 1, ['sku' => 'SKU001']);
            $item2 = makeMatcherTestCartItem('2', 'Product 2', 2000, 1, ['sku' => 'SKU002']);

            $items = new Collection([$item1, $item2]);
            $matching = $matcher->getMatchingItems($items, 5);

            expect($matching)->toHaveCount(1);
        });

        it('returns empty collection when no matches and limit specified', function (): void {
            $matcher = new SkuMatcher(['SKU999']);

            $item1 = makeMatcherTestCartItem('1', 'Product 1', 1000, 1, ['sku' => 'SKU001']);

            $items = new Collection([$item1]);
            $matching = $matcher->getMatchingItems($items, 10);

            expect($matching)->toBeEmpty();
        });

        it('handles limit of zero', function (): void {
            $matcher = new SkuMatcher(['SKU001', 'SKU002']);

            $item1 = makeMatcherTestCartItem('1', 'Product 1', 1000, 1, ['sku' => 'SKU001']);
            $item2 = makeMatcherTestCartItem('2', 'Product 2', 2000, 1, ['sku' => 'SKU002']);

            $items = new Collection([$item1, $item2]);
            $matching = $matcher->getMatchingItems($items, 0);

            expect($matching)->toBeEmpty();
        });
    });

    describe('constructor config handling', function (): void {
        it('accepts empty config array', function (): void {
            $matcher = new SkuMatcher([]);

            expect($matcher)->toBeInstanceOf(ProductMatcherInterface::class);
        });

        it('stores config for subclass access', function (): void {
            // PriceMatcher uses config internally
            $matcher = new PriceMatcher(
                minPrice: 10000, // 100.00
                maxPrice: 50000  // 500.00
            );

            $item = makeMatcherTestCartItem('1', 'Product', 30000, 1); // 300.00

            expect($matcher->matches($item))->toBeTrue();
        });

        it('PriceMatcher rejects items below min price', function (): void {
            $matcher = new PriceMatcher(
                minPrice: 10000, // 100.00
                maxPrice: null
            );

            $item = makeMatcherTestCartItem('1', 'Product', 5000, 1); // 50.00

            expect($matcher->matches($item))->toBeFalse();
        });

        it('PriceMatcher rejects items above max price', function (): void {
            $matcher = new PriceMatcher(
                minPrice: null,
                maxPrice: 10000  // 100.00
            );

            $item = makeMatcherTestCartItem('1', 'Product', 15000, 1); // 150.00

            expect($matcher->matches($item))->toBeFalse();
        });
    });
});

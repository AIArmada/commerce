<?php

declare(strict_types=1);

use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\Compound\Matchers\CategoryMatcher;
use AIArmada\Vouchers\Compound\Matchers\PriceMatcher;
use AIArmada\Vouchers\Compound\Matchers\SkuMatcher;

describe('Product Matchers', function (): void {
    describe('SkuMatcher', function (): void {
        it('matches items by sku', function (): void {
            $matcher = new SkuMatcher(['SKU-001', 'SKU-002']);

            $item = createCartItem('SKU-001', 1000, 1);
            expect($matcher->matches($item))->toBeTrue();

            $item2 = createCartItem('SKU-003', 1000, 1);
            expect($matcher->matches($item2))->toBeFalse();
        });

        it('matches case insensitively', function (): void {
            $matcher = new SkuMatcher(['sku-001']);

            $item = createCartItem('SKU-001', 1000, 1);
            expect($matcher->matches($item))->toBeTrue();
        });

        it('excludes items when exclude mode is enabled', function (): void {
            $matcher = new SkuMatcher(['SKU-001'], exclude: true);

            $item = createCartItem('SKU-001', 1000, 1);
            expect($matcher->matches($item))->toBeFalse();

            $item2 = createCartItem('SKU-002', 1000, 1);
            expect($matcher->matches($item2))->toBeTrue();
        });

        it('filters collection of items', function (): void {
            $matcher = new SkuMatcher(['SKU-001', 'SKU-002']);

            $items = collect([
                createCartItem('SKU-001', 1000, 1),
                createCartItem('SKU-002', 2000, 1),
                createCartItem('SKU-003', 3000, 1),
            ]);

            $filtered = $matcher->filter($items);
            expect($filtered)->toHaveCount(2);
        });

        it('returns correct type', function (): void {
            $matcher = new SkuMatcher(['SKU-001']);
            expect($matcher->getType())->toBe('sku');
        });

        it('serializes to array', function (): void {
            $matcher = new SkuMatcher(['SKU-001', 'SKU-002'], exclude: true);
            $array = $matcher->toArray();

            expect($array['type'])->toBe('sku');
            expect($array['skus'])->toBe(['SKU-001', 'SKU-002']);
            expect($array['exclude'])->toBeTrue();
        });

        it('deserializes from array', function (): void {
            $matcher = SkuMatcher::fromArray([
                'skus' => ['SKU-001'],
                'exclude' => false,
            ]);

            expect($matcher)->toBeInstanceOf(SkuMatcher::class);
            $item = createCartItem('SKU-001', 1000, 1);
            expect($matcher->matches($item))->toBeTrue();
        });
    });

    describe('CategoryMatcher', function (): void {
        it('matches items by category', function (): void {
            $matcher = new CategoryMatcher(['electronics', 'clothing']);

            $item = createCartItem('SKU-001', 1000, 1, ['category' => 'electronics']);
            expect($matcher->matches($item))->toBeTrue();

            $item2 = createCartItem('SKU-002', 1000, 1, ['category' => 'food']);
            expect($matcher->matches($item2))->toBeFalse();
        });

        it('matches child categories when enabled', function (): void {
            $matcher = new CategoryMatcher(['electronics'], includeChildren: true);

            $item = createCartItem('SKU-001', 1000, 1, [
                'category' => 'electronics/phones',
            ]);
            expect($matcher->matches($item))->toBeTrue();
        });

        it('excludes categories when exclude mode is enabled', function (): void {
            $matcher = new CategoryMatcher(['electronics'], exclude: true);

            $item = createCartItem('SKU-001', 1000, 1, ['category' => 'electronics']);
            expect($matcher->matches($item))->toBeFalse();

            $item2 = createCartItem('SKU-002', 1000, 1, ['category' => 'clothing']);
            expect($matcher->matches($item2))->toBeTrue();
        });

        it('returns correct type', function (): void {
            $matcher = new CategoryMatcher(['electronics']);
            expect($matcher->getType())->toBe('category');
        });

        it('serializes to array', function (): void {
            $matcher = new CategoryMatcher(['electronics'], includeChildren: true, exclude: false);
            $array = $matcher->toArray();

            expect($array['type'])->toBe('category');
            expect($array['categories'])->toBe(['electronics']);
            expect($array['include_children'])->toBeTrue();
            expect($array['exclude'])->toBeFalse();
        });
    });

    describe('PriceMatcher', function (): void {
        it('matches items within price range', function (): void {
            $matcher = new PriceMatcher(minPrice: 1000, maxPrice: 5000);

            $item = createCartItem('SKU-001', 3000, 1);
            expect($matcher->matches($item))->toBeTrue();

            $item2 = createCartItem('SKU-002', 500, 1);
            expect($matcher->matches($item2))->toBeFalse();

            $item3 = createCartItem('SKU-003', 6000, 1);
            expect($matcher->matches($item3))->toBeFalse();
        });

        it('matches items at boundary prices', function (): void {
            $matcher = new PriceMatcher(minPrice: 1000, maxPrice: 5000);

            $itemMin = createCartItem('SKU-001', 1000, 1);
            expect($matcher->matches($itemMin))->toBeTrue();

            $itemMax = createCartItem('SKU-002', 5000, 1);
            expect($matcher->matches($itemMax))->toBeTrue();
        });

        it('matches with only minimum price', function (): void {
            $matcher = new PriceMatcher(minPrice: 1000);

            $item = createCartItem('SKU-001', 2000, 1);
            expect($matcher->matches($item))->toBeTrue();

            $item2 = createCartItem('SKU-002', 500, 1);
            expect($matcher->matches($item2))->toBeFalse();
        });

        it('matches with only maximum price', function (): void {
            $matcher = new PriceMatcher(maxPrice: 5000);

            $item = createCartItem('SKU-001', 3000, 1);
            expect($matcher->matches($item))->toBeTrue();

            $item2 = createCartItem('SKU-002', 6000, 1);
            expect($matcher->matches($item2))->toBeFalse();
        });

        it('uses line total when useUnitPrice is false', function (): void {
            $matcher = new PriceMatcher(minPrice: 5000, useUnitPrice: false);

            $item = createCartItem('SKU-001', 2000, 3); // 6000 total
            expect($matcher->matches($item))->toBeTrue();

            $item2 = createCartItem('SKU-002', 2000, 1); // 2000 total
            expect($matcher->matches($item2))->toBeFalse();
        });

        it('returns correct type', function (): void {
            $matcher = new PriceMatcher(minPrice: 1000);
            expect($matcher->getType())->toBe('price');
        });

        it('serializes to array', function (): void {
            $matcher = new PriceMatcher(minPrice: 1000, maxPrice: 5000, useUnitPrice: false);
            $array = $matcher->toArray();

            expect($array['type'])->toBe('price');
            expect($array['min_price'])->toBe(1000);
            expect($array['max_price'])->toBe(5000);
            expect($array['use_unit_price'])->toBeFalse();
        });
    });

    /* Attribute/composite/factory sections removed; superseded by Compound/Matchers dedicated files. */

});

/**
 * Helper function to create a cart item
 *
 * @param  array<string, mixed>  $attributes
 */
function createCartItem(string $sku, int $price, int $quantity, array $attributes = []): CartItem
{
    $attributes['sku'] = $sku;

    return new CartItem(
        id: $sku,
        name: $attributes['name'] ?? "Product {$sku}",
        price: $price,
        quantity: $quantity,
        attributes: $attributes
    );
}

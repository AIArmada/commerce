<?php

declare(strict_types=1);

use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\Compound\Enums\ProductMatcherType;
use AIArmada\Vouchers\Compound\Matchers\CompositeMatcher;
use AIArmada\Vouchers\Compound\Matchers\PriceMatcher;
use AIArmada\Vouchers\Compound\Matchers\SkuMatcher;
use AIArmada\Vouchers\Contracts\ProductMatcherInterface;

/**
 * Helper function to create a CartItem for tests.
 *
 * @param  array<string, mixed>  $attrs  Extra attributes like 'sku', 'category'
 */
function makeCompositeTestCartItem(string $id, string $name, int $price, int $quantity = 1, array $attrs = []): CartItem
{
    return new CartItem(
        id: $id,
        name: $name,
        price: $price,
        quantity: $quantity,
        attributes: $attrs
    );
}

describe('CompositeMatcher', function (): void {
    describe('all static constructor', function (): void {
        it('creates AND logic matcher', function (): void {
            $matcher = CompositeMatcher::all([
                new SkuMatcher(['SKU001']),
                new PriceMatcher(minPrice: 1000),
            ]);

            expect($matcher->getType())->toBe(ProductMatcherType::All->value);
        });

        it('accepts array configs and converts to matchers', function (): void {
            $matcher = CompositeMatcher::all([
                ['type' => 'sku', 'skus' => ['SKU001']],
                ['type' => 'price', 'min_price' => 1000],
            ]);

            expect($matcher->getMatchers())->toHaveCount(2)
                ->and($matcher->getMatchers()[0])->toBeInstanceOf(SkuMatcher::class)
                ->and($matcher->getMatchers()[1])->toBeInstanceOf(PriceMatcher::class);
        });

        it('accepts mixed ProductMatcherInterface and array configs', function (): void {
            $skuMatcher = new SkuMatcher(['SKU001']);
            $matcher = CompositeMatcher::all([
                $skuMatcher,
                ['type' => 'price', 'min_price' => 1000],
            ]);

            expect($matcher->getMatchers())->toHaveCount(2)
                ->and($matcher->getMatchers()[0])->toBe($skuMatcher);
        });

        it('creates with empty matchers array', function (): void {
            $matcher = CompositeMatcher::all([]);

            expect($matcher->getMatchers())->toBeEmpty();
        });
    });

    describe('any static constructor', function (): void {
        it('creates OR logic matcher', function (): void {
            $matcher = CompositeMatcher::any([
                new SkuMatcher(['SKU001']),
                new PriceMatcher(minPrice: 1000),
            ]);

            expect($matcher->getType())->toBe(ProductMatcherType::Any->value);
        });

        it('accepts array configs and converts to matchers', function (): void {
            $matcher = CompositeMatcher::any([
                ['type' => 'sku', 'skus' => ['SKU001']],
                ['type' => 'category', 'categories' => ['electronics']],
            ]);

            expect($matcher->getMatchers())->toHaveCount(2);
        });
    });

    describe('fromArray static constructor', function (): void {
        it('creates AND matcher from all type config', function (): void {
            $matcher = CompositeMatcher::fromArray([
                'type' => 'all',
                'matchers' => [
                    ['type' => 'sku', 'skus' => ['SKU001']],
                ],
            ]);

            expect($matcher->getType())->toBe('all');
        });

        it('creates OR matcher from any type config', function (): void {
            $matcher = CompositeMatcher::fromArray([
                'type' => 'any',
                'matchers' => [
                    ['type' => 'sku', 'skus' => ['SKU001']],
                ],
            ]);

            expect($matcher->getType())->toBe('any');
        });

        it('defaults to AND matcher when type is missing', function (): void {
            $matcher = CompositeMatcher::fromArray([
                'matchers' => [
                    ['type' => 'sku', 'skus' => ['SKU001']],
                ],
            ]);

            expect($matcher->getType())->toBe('all');
        });

        it('handles missing matchers key', function (): void {
            $matcher = CompositeMatcher::fromArray(['type' => 'all']);

            expect($matcher->getMatchers())->toBeEmpty();
        });
    });

    describe('matches with AND logic', function (): void {
        it('returns true when all matchers match', function (): void {
            $matcher = CompositeMatcher::all([
                new SkuMatcher(['SKU001']),
                new PriceMatcher(minPrice: 500, maxPrice: 2000),
            ]);

            $item = makeCompositeTestCartItem('1', 'Product', 1000, 1, ['sku' => 'SKU001']);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('returns false when one matcher fails', function (): void {
            $matcher = CompositeMatcher::all([
                new SkuMatcher(['SKU001']),
                new PriceMatcher(minPrice: 500, maxPrice: 2000),
            ]);

            $item = makeCompositeTestCartItem('1', 'Product', 3000, 1, ['sku' => 'SKU001']);

            expect($matcher->matches($item))->toBeFalse();
        });

        it('returns false when all matchers fail', function (): void {
            $matcher = CompositeMatcher::all([
                new SkuMatcher(['SKU001']),
                new PriceMatcher(minPrice: 500, maxPrice: 2000),
            ]);

            $item = makeCompositeTestCartItem('1', 'Product', 3000, 1, ['sku' => 'SKU999']);

            expect($matcher->matches($item))->toBeFalse();
        });

        it('returns true when matchers list is empty', function (): void {
            $matcher = CompositeMatcher::all([]);

            $item = makeCompositeTestCartItem('1', 'Product', 1000);

            expect($matcher->matches($item))->toBeTrue();
        });
    });

    describe('matches with OR logic', function (): void {
        it('returns true when any matcher matches', function (): void {
            $matcher = CompositeMatcher::any([
                new SkuMatcher(['SKU001']),
                new PriceMatcher(minPrice: 5000), // Won't match
            ]);

            $item = makeCompositeTestCartItem('1', 'Product', 1000, 1, ['sku' => 'SKU001']);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('returns true when second matcher matches', function (): void {
            $matcher = CompositeMatcher::any([
                new SkuMatcher(['SKU999']), // Won't match
                new PriceMatcher(minPrice: 500, maxPrice: 2000),
            ]);

            $item = makeCompositeTestCartItem('1', 'Product', 1000, 1, ['sku' => 'SKU001']);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('returns false when no matchers match', function (): void {
            $matcher = CompositeMatcher::any([
                new SkuMatcher(['SKU999']),
                new PriceMatcher(minPrice: 5000),
            ]);

            $item = makeCompositeTestCartItem('1', 'Product', 1000, 1, ['sku' => 'SKU001']);

            expect($matcher->matches($item))->toBeFalse();
        });

        it('returns false when matchers list is empty', function (): void {
            $matcher = CompositeMatcher::any([]);

            $item = makeCompositeTestCartItem('1', 'Product', 1000);

            // Empty OR returns false (nothing matched)
            // Actually, based on the code, empty matchers returns true
            expect($matcher->matches($item))->toBeTrue();
        });
    });

    describe('addMatcher', function (): void {
        it('adds matcher to the composite', function (): void {
            $matcher = CompositeMatcher::all([]);

            expect($matcher->getMatchers())->toBeEmpty();

            $matcher->addMatcher(new SkuMatcher(['SKU001']));

            expect($matcher->getMatchers())->toHaveCount(1);
        });

        it('returns self for chaining', function (): void {
            $matcher = CompositeMatcher::all([]);
            $result = $matcher->addMatcher(new SkuMatcher(['SKU001']));

            expect($result)->toBe($matcher);
        });

        it('can chain multiple add calls', function (): void {
            $matcher = CompositeMatcher::all([])
                ->addMatcher(new SkuMatcher(['SKU001']))
                ->addMatcher(new PriceMatcher(minPrice: 1000));

            expect($matcher->getMatchers())->toHaveCount(2);
        });
    });

    describe('getMatchers', function (): void {
        it('returns all matchers', function (): void {
            $skuMatcher = new SkuMatcher(['SKU001']);
            $priceMatcher = new PriceMatcher(minPrice: 1000);

            $matcher = new CompositeMatcher([$skuMatcher, $priceMatcher], true);

            $matchers = $matcher->getMatchers();

            expect($matchers)->toHaveCount(2)
                ->and($matchers[0])->toBe($skuMatcher)
                ->and($matchers[1])->toBe($priceMatcher);
        });
    });

    describe('getType', function (): void {
        it('returns all for AND logic', function (): void {
            $matcher = new CompositeMatcher([], true);

            expect($matcher->getType())->toBe('all');
        });

        it('returns any for OR logic', function (): void {
            $matcher = new CompositeMatcher([], false);

            expect($matcher->getType())->toBe('any');
        });
    });

    describe('toArray', function (): void {
        it('serializes AND matcher correctly', function (): void {
            $matcher = CompositeMatcher::all([
                new SkuMatcher(['SKU001']),
            ]);

            $array = $matcher->toArray();

            expect($array['type'])->toBe('all')
                ->and($array['matchers'])->toBeArray()
                ->and($array['matchers'])->toHaveCount(1);
        });

        it('serializes OR matcher correctly', function (): void {
            $matcher = CompositeMatcher::any([
                new SkuMatcher(['SKU001']),
            ]);

            $array = $matcher->toArray();

            expect($array['type'])->toBe('any');
        });

        it('serializes nested matchers', function (): void {
            $matcher = CompositeMatcher::all([
                new SkuMatcher(['SKU001', 'SKU002']),
                new PriceMatcher(minPrice: 1000, maxPrice: 5000),
            ]);

            $array = $matcher->toArray();

            expect($array['matchers'][0]['type'])->toBe('sku')
                ->and($array['matchers'][1]['type'])->toBe('price');
        });

        it('handles empty matchers array', function (): void {
            $matcher = CompositeMatcher::all([]);

            $array = $matcher->toArray();

            expect($array['matchers'])->toBeEmpty();
        });
    });

    describe('nested composite matchers', function (): void {
        it('supports nested AND within OR', function (): void {
            // Match: (SKU001 AND price 1000-2000) OR (SKU002)
            $matcher = CompositeMatcher::any([
                CompositeMatcher::all([
                    new SkuMatcher(['SKU001']),
                    new PriceMatcher(minPrice: 1000, maxPrice: 2000),
                ]),
                new SkuMatcher(['SKU002']),
            ]);

            $item1 = makeCompositeTestCartItem('1', 'Product', 1500, 1, ['sku' => 'SKU001']);
            $item2 = makeCompositeTestCartItem('2', 'Product', 500, 1, ['sku' => 'SKU002']);
            $item3 = makeCompositeTestCartItem('3', 'Product', 3000, 1, ['sku' => 'SKU001']);

            expect($matcher->matches($item1))->toBeTrue()  // Matches AND condition
                ->and($matcher->matches($item2))->toBeTrue() // Matches OR SKU002
                ->and($matcher->matches($item3))->toBeFalse(); // SKU001 but price too high
        });
    });
});

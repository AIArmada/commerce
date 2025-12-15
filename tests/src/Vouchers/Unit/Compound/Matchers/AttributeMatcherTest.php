<?php

declare(strict_types=1);

use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\Compound\Enums\ProductMatcherType;
use AIArmada\Vouchers\Compound\Matchers\AttributeMatcher;

/**
 * Helper function to create a CartItem for attribute tests.
 *
 * @param  array<string, mixed>  $attrs  Extra attributes
 */
function makeAttributeTestCartItem(string $id, string $name, int $price, array $attrs = []): CartItem
{
    return new CartItem(
        id: $id,
        name: $name,
        price: $price,
        quantity: 1,
        attributes: $attrs
    );
}

describe('AttributeMatcher', function (): void {
    describe('constructor and fromArray', function (): void {
        it('creates matcher with attribute, operator, and value', function (): void {
            $matcher = new AttributeMatcher('color', '=', 'red');

            $array = $matcher->toArray();

            expect($array['attribute'])->toBe('color')
                ->and($array['operator'])->toBe('=')
                ->and($array['value'])->toBe('red');
        });

        it('creates from array config', function (): void {
            $matcher = AttributeMatcher::fromArray([
                'attribute' => 'size',
                'operator' => 'in',
                'value' => ['S', 'M', 'L'],
            ]);

            $array = $matcher->toArray();

            expect($array['attribute'])->toBe('size')
                ->and($array['operator'])->toBe('in')
                ->and($array['value'])->toBe(['S', 'M', 'L']);
        });

        it('uses defaults when config values are missing', function (): void {
            $matcher = AttributeMatcher::fromArray([]);

            $array = $matcher->toArray();

            expect($array['attribute'])->toBe('')
                ->and($array['operator'])->toBe('=')
                ->and($array['value'])->toBeNull();
        });
    });

    describe('getType', function (): void {
        it('returns attribute type', function (): void {
            $matcher = new AttributeMatcher('color', '=', 'red');

            expect($matcher->getType())->toBe(ProductMatcherType::Attribute->value);
        });
    });

    describe('equality operators', function (): void {
        it('matches with equals operator', function (): void {
            $matcher = new AttributeMatcher('color', '=', 'red');

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['color' => 'red']);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('does not match with equals operator when different', function (): void {
            $matcher = new AttributeMatcher('color', '=', 'red');

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['color' => 'blue']);

            expect($matcher->matches($item))->toBeFalse();
        });

        it('matches with double equals operator', function (): void {
            $matcher = new AttributeMatcher('color', '==', 'red');

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['color' => 'red']);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('matches with triple equals operator for strict equality', function (): void {
            $matcher = new AttributeMatcher('count', '===', 5);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['count' => 5]);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('matches with not equals operator', function (): void {
            $matcher = new AttributeMatcher('color', '!=', 'red');

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['color' => 'blue']);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('does not match with != when values are equal', function (): void {
            $matcher = new AttributeMatcher('color', '!=', 'red');

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['color' => 'red']);

            expect($matcher->matches($item))->toBeFalse();
        });

        it('matches with strict not equals operator for strict inequality', function (): void {
            $matcher = new AttributeMatcher('count', '!==', 5);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['count' => 10]);

            expect($matcher->matches($item))->toBeTrue();
        });
    });

    describe('comparison operators', function (): void {
        it('matches with greater than operator', function (): void {
            $matcher = new AttributeMatcher('weight', '>', 100);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['weight' => 150]);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('does not match with greater than operator when equal', function (): void {
            $matcher = new AttributeMatcher('weight', '>', 100);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['weight' => 100]);

            expect($matcher->matches($item))->toBeFalse();
        });

        it('matches with less than operator', function (): void {
            $matcher = new AttributeMatcher('weight', '<', 100);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['weight' => 50]);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('matches with greater than or equal operator', function (): void {
            $matcher = new AttributeMatcher('weight', '>=', 100);

            $itemEqual = makeAttributeTestCartItem('1', 'Product', 1000, ['weight' => 100]);
            $itemGreater = makeAttributeTestCartItem('2', 'Product', 1000, ['weight' => 150]);

            expect($matcher->matches($itemEqual))->toBeTrue()
                ->and($matcher->matches($itemGreater))->toBeTrue();
        });

        it('matches with less than or equal operator', function (): void {
            $matcher = new AttributeMatcher('weight', '<=', 100);

            $itemEqual = makeAttributeTestCartItem('1', 'Product', 1000, ['weight' => 100]);
            $itemLess = makeAttributeTestCartItem('2', 'Product', 1000, ['weight' => 50]);

            expect($matcher->matches($itemEqual))->toBeTrue()
                ->and($matcher->matches($itemLess))->toBeTrue();
        });

        it('returns false for > with non-numeric values', function (): void {
            $matcher = new AttributeMatcher('name', '>', 'test');

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['name' => 'testing']);

            expect($matcher->matches($item))->toBeFalse();
        });
    });

    describe('array operators', function (): void {
        it('matches with in operator', function (): void {
            $matcher = new AttributeMatcher('size', 'in', ['S', 'M', 'L']);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['size' => 'M']);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('does not match with in operator when not in array', function (): void {
            $matcher = new AttributeMatcher('size', 'in', ['S', 'M', 'L']);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['size' => 'XL']);

            expect($matcher->matches($item))->toBeFalse();
        });

        it('matches with not_in operator', function (): void {
            $matcher = new AttributeMatcher('size', 'not_in', ['S', 'M', 'L']);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['size' => 'XL']);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('does not match with not_in operator when in array', function (): void {
            $matcher = new AttributeMatcher('size', 'not_in', ['S', 'M', 'L']);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['size' => 'M']);

            expect($matcher->matches($item))->toBeFalse();
        });
    });

    describe('string operators', function (): void {
        it('matches with contains operator', function (): void {
            $matcher = new AttributeMatcher('description', 'contains', 'premium');

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['description' => 'This is a premium product']);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('does not match with contains when substring not found', function (): void {
            $matcher = new AttributeMatcher('description', 'contains', 'premium');

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['description' => 'This is a basic product']);

            expect($matcher->matches($item))->toBeFalse();
        });

        it('matches with starts_with operator', function (): void {
            $matcher = new AttributeMatcher('sku', 'starts_with', 'PRD-');

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['sku' => 'PRD-12345']);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('does not match with starts_with when prefix differs', function (): void {
            $matcher = new AttributeMatcher('sku', 'starts_with', 'PRD-');

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['sku' => 'ACC-12345']);

            expect($matcher->matches($item))->toBeFalse();
        });

        it('matches with ends_with operator', function (): void {
            $matcher = new AttributeMatcher('email', 'ends_with', '@company.com');

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['email' => 'user@company.com']);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('does not match with ends_with when suffix differs', function (): void {
            $matcher = new AttributeMatcher('email', 'ends_with', '@company.com');

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['email' => 'user@other.com']);

            expect($matcher->matches($item))->toBeFalse();
        });
    });

    describe('existence operators', function (): void {
        it('matches with exists operator when attribute exists', function (): void {
            $matcher = new AttributeMatcher('color', 'exists', null);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['color' => 'red']);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('does not match with exists operator when attribute missing', function (): void {
            $matcher = new AttributeMatcher('color', 'exists', null);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['size' => 'M']);

            expect($matcher->matches($item))->toBeFalse();
        });

        it('matches with not_exists operator when attribute missing', function (): void {
            $matcher = new AttributeMatcher('color', 'not_exists', null);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['size' => 'M']);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('does not match with not_exists operator when attribute exists', function (): void {
            $matcher = new AttributeMatcher('color', 'not_exists', null);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['color' => 'red']);

            expect($matcher->matches($item))->toBeFalse();
        });
    });

    describe('nested attributes with dot notation', function (): void {
        it('matches nested attribute', function (): void {
            $matcher = new AttributeMatcher('dimensions.width', '>', 10);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, [
                'dimensions' => [
                    'width' => 20,
                    'height' => 15,
                ],
            ]);

            expect($matcher->matches($item))->toBeTrue();
        });

        it('returns null for non-existent nested path', function (): void {
            $matcher = new AttributeMatcher('dimensions.depth', 'exists', null);

            $item = makeAttributeTestCartItem('1', 'Product', 1000, [
                'dimensions' => [
                    'width' => 20,
                ],
            ]);

            expect($matcher->matches($item))->toBeFalse();
        });

        it('handles deeply nested attributes', function (): void {
            $matcher = new AttributeMatcher('meta.specs.material', '=', 'cotton');

            $item = makeAttributeTestCartItem('1', 'Product', 1000, [
                'meta' => [
                    'specs' => [
                        'material' => 'cotton',
                    ],
                ],
            ]);

            expect($matcher->matches($item))->toBeTrue();
        });
    });

    describe('unknown operator', function (): void {
        it('returns false for unknown operator', function (): void {
            $matcher = new AttributeMatcher('color', 'unknown_op', 'red');

            $item = makeAttributeTestCartItem('1', 'Product', 1000, ['color' => 'red']);

            expect($matcher->matches($item))->toBeFalse();
        });
    });

    describe('toArray', function (): void {
        it('serializes matcher to array', function (): void {
            $matcher = new AttributeMatcher('color', '=', 'red');

            $array = $matcher->toArray();

            expect($array)->toBe([
                'type' => 'attribute',
                'attribute' => 'color',
                'operator' => '=',
                'value' => 'red',
            ]);
        });

        it('serializes complex value types', function (): void {
            $matcher = new AttributeMatcher('tags', 'in', ['sale', 'new', 'featured']);

            $array = $matcher->toArray();

            expect($array['value'])->toBe(['sale', 'new', 'featured']);
        });
    });
});

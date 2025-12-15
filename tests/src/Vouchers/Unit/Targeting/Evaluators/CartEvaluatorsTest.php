<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Targeting\Evaluators;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Targeting\Evaluators\CartQuantityEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\CartValueEvaluator;
use AIArmada\Vouchers\Targeting\TargetingContext;

/**
 * Create a context with a cart that has specific subtotal and quantity.
 *
 * @param  int  $totalValue  Total cart value in minor units
 * @param  int  $totalQuantity  Total quantity of items (each item has quantity 1)
 */
function createCartContextForEvaluators(int $totalValue = 0, int $totalQuantity = 0): TargetingContext
{
    $cart = new Cart(new InMemoryStorage(), 'evaluator-test-' . uniqid());

    if ($totalQuantity > 0) {
        $pricePerItem = $totalQuantity > 0 ? (int) ($totalValue / $totalQuantity) : $totalValue;

        for ($i = 0; $i < $totalQuantity; $i++) {
            $cart->add([
                'id' => 'item-' . $i,
                'name' => 'Test Item ' . $i,
                'price' => $pricePerItem,
                'quantity' => 1,
            ]);
        }
    }

    return new TargetingContext($cart);
}

describe('CartValueEvaluator', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new CartValueEvaluator();
    });

    describe('supports', function (): void {
        it('returns true for cart_value type', function (): void {
            expect($this->evaluator->supports('cart_value'))->toBeTrue();
        });

        it('returns false for other types', function (): void {
            expect($this->evaluator->supports('user_segment'))->toBeFalse();
            expect($this->evaluator->supports('cart_quantity'))->toBeFalse();
        });
    });

    describe('getType', function (): void {
        it('returns cart_value', function (): void {
            expect($this->evaluator->getType())->toBe('cart_value');
        });
    });

    describe('evaluate', function (): void {
        // Cart with 5 items at 2000 each = 10000 total value
        it('evaluates equals operator correctly', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 10000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 5000], $context))->toBeFalse();
        });

        it('evaluates not equals operator correctly', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => '!=', 'value' => 5000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '!=', 'value' => 10000], $context))->toBeFalse();
        });

        it('evaluates greater than operator correctly', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => '>', 'value' => 5000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '>', 'value' => 10000], $context))->toBeFalse();
            expect($this->evaluator->evaluate(['operator' => '>', 'value' => 15000], $context))->toBeFalse();
        });

        it('evaluates greater than or equals operator correctly', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => '>=', 'value' => 5000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '>=', 'value' => 10000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '>=', 'value' => 15000], $context))->toBeFalse();
        });

        it('evaluates less than operator correctly', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => '<', 'value' => 15000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '<', 'value' => 10000], $context))->toBeFalse();
            expect($this->evaluator->evaluate(['operator' => '<', 'value' => 5000], $context))->toBeFalse();
        });

        it('evaluates less than or equals operator correctly', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => '<=', 'value' => 15000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '<=', 'value' => 10000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '<=', 'value' => 5000], $context))->toBeFalse();
        });

        it('evaluates between operator correctly', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => 'between', 'min' => 5000, 'max' => 15000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => 'between', 'min' => 10000, 'max' => 15000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => 'between', 'min' => 5000, 'max' => 10000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => 'between', 'min' => 11000, 'max' => 15000], $context))->toBeFalse();
        });

        it('returns false for unknown operator', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => 'unknown', 'value' => 10000], $context))->toBeFalse();
        });

        it('uses default operator >= when not specified', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['value' => 5000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['value' => 10000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['value' => 15000], $context))->toBeFalse();
        });

        it('defaults to 0 when value not specified', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => '>='], $context))->toBeTrue();
        });

        it('handles empty cart', function (): void {
            $context = createCartContextForEvaluators(0, 0);

            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 0], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '>=', 'value' => 0], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '>', 'value' => 0], $context))->toBeFalse();
        });
    });

    describe('validate', function (): void {
        it('validates between operator requires min and max', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'between']);

            expect($errors)->toContain('Min value is required for between operator');
            expect($errors)->toContain('Max value is required for between operator');
        });

        it('validates between operator with valid values', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'between', 'min' => 100, 'max' => 200]);

            expect($errors)->toBe([]);
        });

        it('validates between operator requires numeric min', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'between', 'min' => 'abc', 'max' => 200]);

            expect($errors)->toContain('Min value is required for between operator');
        });

        it('validates between operator requires numeric max', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'between', 'min' => 100, 'max' => 'abc']);

            expect($errors)->toContain('Max value is required for between operator');
        });

        it('validates other operators require numeric value', function (): void {
            $errors = $this->evaluator->validate(['operator' => '>=']);

            expect($errors)->toContain('Value must be a number');
        });

        it('validates other operators with valid value', function (): void {
            $errors = $this->evaluator->validate(['operator' => '>=', 'value' => 100]);

            expect($errors)->toBe([]);
        });

        it('validates value must be numeric', function (): void {
            $errors = $this->evaluator->validate(['operator' => '>=', 'value' => 'not-a-number']);

            expect($errors)->toContain('Value must be a number');
        });
    });
});

describe('CartQuantityEvaluator', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new CartQuantityEvaluator();
    });

    describe('supports', function (): void {
        it('returns true for cart_quantity type', function (): void {
            expect($this->evaluator->supports('cart_quantity'))->toBeTrue();
        });

        it('returns false for other types', function (): void {
            expect($this->evaluator->supports('user_segment'))->toBeFalse();
            expect($this->evaluator->supports('cart_value'))->toBeFalse();
        });
    });

    describe('getType', function (): void {
        it('returns cart_quantity', function (): void {
            expect($this->evaluator->getType())->toBe('cart_quantity');
        });
    });

    describe('evaluate', function (): void {
        // Cart with 5 items
        it('evaluates equals operator correctly', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 5], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 3], $context))->toBeFalse();
        });

        it('evaluates not equals operator correctly', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => '!=', 'value' => 3], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '!=', 'value' => 5], $context))->toBeFalse();
        });

        it('evaluates greater than operator correctly', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => '>', 'value' => 3], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '>', 'value' => 5], $context))->toBeFalse();
            expect($this->evaluator->evaluate(['operator' => '>', 'value' => 7], $context))->toBeFalse();
        });

        it('evaluates greater than or equals operator correctly', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => '>=', 'value' => 3], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '>=', 'value' => 5], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '>=', 'value' => 7], $context))->toBeFalse();
        });

        it('evaluates less than operator correctly', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => '<', 'value' => 7], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '<', 'value' => 5], $context))->toBeFalse();
            expect($this->evaluator->evaluate(['operator' => '<', 'value' => 3], $context))->toBeFalse();
        });

        it('evaluates less than or equals operator correctly', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => '<=', 'value' => 7], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '<=', 'value' => 5], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '<=', 'value' => 3], $context))->toBeFalse();
        });

        it('evaluates between operator correctly', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => 'between', 'min' => 3, 'max' => 7], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => 'between', 'min' => 5, 'max' => 7], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => 'between', 'min' => 3, 'max' => 5], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => 'between', 'min' => 6, 'max' => 10], $context))->toBeFalse();
        });

        it('returns false for unknown operator', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['operator' => 'unknown', 'value' => 5], $context))->toBeFalse();
        });

        it('uses default operator >= when not specified', function (): void {
            $context = createCartContextForEvaluators(10000, 5);

            expect($this->evaluator->evaluate(['value' => 3], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['value' => 5], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['value' => 7], $context))->toBeFalse();
        });

        it('handles empty cart', function (): void {
            $context = createCartContextForEvaluators(0, 0);

            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 0], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '>=', 'value' => 0], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '>', 'value' => 0], $context))->toBeFalse();
        });
    });

    describe('validate', function (): void {
        it('validates between operator requires min and max', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'between']);

            expect($errors)->toContain('Min value is required for between operator');
            expect($errors)->toContain('Max value is required for between operator');
        });

        it('validates other operators require value', function (): void {
            $errors = $this->evaluator->validate(['operator' => '>=']);

            expect($errors)->toContain('Value must be a number');
        });

        it('validates with valid inputs', function (): void {
            $errors = $this->evaluator->validate(['operator' => '>=', 'value' => 5]);

            expect($errors)->toBe([]);
        });
    });
});

<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Targeting\Evaluators;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Targeting\Evaluators\CategoryInCartEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\CustomerLifetimeValueEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\DateRangeEvaluator;
use AIArmada\Vouchers\Targeting\TargetingContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Mockery;

afterEach(function (): void {
    Mockery::close();
    Carbon::setTestNow();
});

/**
 * Test user with lifetime value method.
 */
class TestCLVUser extends Model
{
    protected $guarded = [];

    public function getLifetimeValue(): int
    {
        return $this->lifetime_value ?? 0;
    }
}

/**
 * Create cart with specific categories.
 */
function createCartWithCategories(array $categories): Cart
{
    $cart = new Cart(new InMemoryStorage, 'test-' . uniqid());

    foreach ($categories as $index => $category) {
        $cart->add([
            'id' => 'product-' . $index,
            'name' => 'Product ' . $index,
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['category' => $category],
        ]);
    }

    return $cart;
}

/**
 * Create context for CLV tests.
 */
function createCLVContext(?int $lifetimeValue = null): TargetingContext
{
    $cart = new Cart(new InMemoryStorage, 'test-' . uniqid());

    $user = null;
    if ($lifetimeValue !== null) {
        $user = new TestCLVUser(['lifetime_value' => $lifetimeValue]);
    }

    return new TargetingContext($cart, $user);
}

/**
 * Create context for DateRange tests with timezone.
 */
function createDateRangeContext(string $timezone = 'UTC'): TargetingContext
{
    $cart = new Cart(new InMemoryStorage, 'test-' . uniqid());

    return new TargetingContext($cart, null, null, ['timezone' => $timezone]);
}

describe('CategoryInCartEvaluator', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new CategoryInCartEvaluator;
    });

    describe('supports', function (): void {
        it('returns true for category_in_cart type', function (): void {
            expect($this->evaluator->supports('category_in_cart'))->toBeTrue();
        });

        it('returns false for other types', function (): void {
            expect($this->evaluator->supports('device'))->toBeFalse();
            expect($this->evaluator->supports('cart_value'))->toBeFalse();
        });
    });

    describe('getType', function (): void {
        it('returns category_in_cart', function (): void {
            expect($this->evaluator->getType())->toBe('category_in_cart');
        });
    });

    describe('evaluate', function (): void {
        it('returns true when empty target categories', function (): void {
            $cart = createCartWithCategories(['electronics']);
            $context = new TargetingContext($cart);

            expect($this->evaluator->evaluate(['values' => []], $context))->toBeTrue();
        });

        it('evaluates in operator correctly', function (): void {
            $cart = createCartWithCategories(['electronics', 'clothing']);
            $context = new TargetingContext($cart);

            expect($this->evaluator->evaluate([
                'operator' => 'in',
                'values' => ['electronics', 'furniture'],
            ], $context))->toBeTrue();

            expect($this->evaluator->evaluate([
                'operator' => 'in',
                'values' => ['furniture', 'books'],
            ], $context))->toBeFalse();
        });

        it('evaluates contains_any operator correctly', function (): void {
            $cart = createCartWithCategories(['electronics', 'clothing']);
            $context = new TargetingContext($cart);

            expect($this->evaluator->evaluate([
                'operator' => 'contains_any',
                'values' => ['electronics', 'furniture'],
            ], $context))->toBeTrue();

            expect($this->evaluator->evaluate([
                'operator' => 'contains_any',
                'values' => ['furniture', 'books'],
            ], $context))->toBeFalse();
        });

        it('evaluates not_in operator correctly', function (): void {
            $cart = createCartWithCategories(['electronics']);
            $context = new TargetingContext($cart);

            expect($this->evaluator->evaluate([
                'operator' => 'not_in',
                'values' => ['clothing', 'furniture'],
            ], $context))->toBeTrue();

            expect($this->evaluator->evaluate([
                'operator' => 'not_in',
                'values' => ['electronics', 'furniture'],
            ], $context))->toBeFalse();
        });

        it('evaluates contains_all operator correctly', function (): void {
            $cart = createCartWithCategories(['electronics', 'clothing', 'books']);
            $context = new TargetingContext($cart);

            expect($this->evaluator->evaluate([
                'operator' => 'contains_all',
                'values' => ['electronics', 'clothing'],
            ], $context))->toBeTrue();

            expect($this->evaluator->evaluate([
                'operator' => 'contains_all',
                'values' => ['electronics', 'furniture'],
            ], $context))->toBeFalse();
        });

        it('returns false for unknown operator', function (): void {
            $cart = createCartWithCategories(['electronics']);
            $context = new TargetingContext($cart);

            expect($this->evaluator->evaluate([
                'operator' => 'unknown',
                'values' => ['electronics'],
            ], $context))->toBeFalse();
        });

        it('uses in operator as default', function (): void {
            $cart = createCartWithCategories(['electronics']);
            $context = new TargetingContext($cart);

            expect($this->evaluator->evaluate([
                'values' => ['electronics'],
            ], $context))->toBeTrue();
        });
    });

    describe('validate', function (): void {
        it('returns error when values not set', function (): void {
            $errors = $this->evaluator->validate([]);

            expect($errors)->toContain('Values must be an array of category slugs/IDs');
        });

        it('returns error when values not array', function (): void {
            $errors = $this->evaluator->validate(['values' => 'not-array']);

            expect($errors)->toContain('Values must be an array of category slugs/IDs');
        });

        it('returns no errors with valid values', function (): void {
            $errors = $this->evaluator->validate(['values' => ['electronics', 'clothing']]);

            expect($errors)->toBe([]);
        });
    });
});

describe('CustomerLifetimeValueEvaluator', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new CustomerLifetimeValueEvaluator;
    });

    describe('supports', function (): void {
        it('returns true for clv type', function (): void {
            expect($this->evaluator->supports('clv'))->toBeTrue();
        });

        it('returns false for other types', function (): void {
            expect($this->evaluator->supports('cart_value'))->toBeFalse();
        });
    });

    describe('getType', function (): void {
        it('returns clv', function (): void {
            expect($this->evaluator->getType())->toBe('clv');
        });
    });

    describe('evaluate', function (): void {
        it('evaluates equals operator correctly', function (): void {
            $context = createCLVContext(1000);

            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 1000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 999], $context))->toBeFalse();
        });

        it('evaluates not equals operator correctly', function (): void {
            $context = createCLVContext(1000);

            expect($this->evaluator->evaluate(['operator' => '!=', 'value' => 999], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '!=', 'value' => 1000], $context))->toBeFalse();
        });

        it('evaluates greater than operator correctly', function (): void {
            $context = createCLVContext(1000);

            expect($this->evaluator->evaluate(['operator' => '>', 'value' => 999], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '>', 'value' => 1000], $context))->toBeFalse();
        });

        it('evaluates greater than or equals operator correctly', function (): void {
            $context = createCLVContext(1000);

            expect($this->evaluator->evaluate(['operator' => '>=', 'value' => 1000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '>=', 'value' => 1001], $context))->toBeFalse();
        });

        it('evaluates less than operator correctly', function (): void {
            $context = createCLVContext(1000);

            expect($this->evaluator->evaluate(['operator' => '<', 'value' => 1001], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '<', 'value' => 1000], $context))->toBeFalse();
        });

        it('evaluates less than or equals operator correctly', function (): void {
            $context = createCLVContext(1000);

            expect($this->evaluator->evaluate(['operator' => '<=', 'value' => 1000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '<=', 'value' => 999], $context))->toBeFalse();
        });

        it('evaluates between operator correctly', function (): void {
            $context = createCLVContext(500);

            expect($this->evaluator->evaluate(['operator' => 'between', 'min' => 100, 'max' => 1000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => 'between', 'min' => 600, 'max' => 1000], $context))->toBeFalse();
        });

        it('returns false for unknown operator', function (): void {
            $context = createCLVContext(1000);

            expect($this->evaluator->evaluate(['operator' => 'unknown', 'value' => 1000], $context))->toBeFalse();
        });

        it('uses >= as default operator', function (): void {
            $context = createCLVContext(1000);

            expect($this->evaluator->evaluate(['value' => 1000], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['value' => 1001], $context))->toBeFalse();
        });

        it('handles null user as zero CLV', function (): void {
            $context = createCLVContext(null);

            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 0], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '>', 'value' => 0], $context))->toBeFalse();
        });
    });

    describe('validate', function (): void {
        it('validates between operator requires min', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'between', 'max' => 1000]);

            expect($errors)->toContain('Min value is required for between operator');
        });

        it('validates between operator requires max', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'between', 'min' => 100]);

            expect($errors)->toContain('Max value is required for between operator');
        });

        it('validates between operator with valid values', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'between', 'min' => 100, 'max' => 1000]);

            expect($errors)->toBe([]);
        });

        it('validates other operators require value', function (): void {
            $errors = $this->evaluator->validate(['operator' => '>=']);

            expect($errors)->toContain('Value must be a number');
        });

        it('validates value must be numeric', function (): void {
            $errors = $this->evaluator->validate(['operator' => '>=', 'value' => 'not-number']);

            expect($errors)->toContain('Value must be a number');
        });

        it('validates with valid value', function (): void {
            $errors = $this->evaluator->validate(['operator' => '>=', 'value' => 1000]);

            expect($errors)->toBe([]);
        });
    });
});

describe('DateRangeEvaluator', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new DateRangeEvaluator;
    });

    describe('supports', function (): void {
        it('returns true for date_range type', function (): void {
            expect($this->evaluator->supports('date_range'))->toBeTrue();
        });

        it('returns false for other types', function (): void {
            expect($this->evaluator->supports('device'))->toBeFalse();
        });
    });

    describe('getType', function (): void {
        it('returns date_range', function (): void {
            expect($this->evaluator->getType())->toBe('date_range');
        });
    });

    describe('evaluate', function (): void {
        it('evaluates between operator with valid range', function (): void {
            Carbon::setTestNow('2024-06-15 12:00:00');
            $context = createDateRangeContext();

            expect($this->evaluator->evaluate([
                'operator' => 'between',
                'start' => '2024-06-01',
                'end' => '2024-06-30',
            ], $context))->toBeTrue();
        });

        it('evaluates between operator outside range', function (): void {
            Carbon::setTestNow('2024-07-15 12:00:00');
            $context = createDateRangeContext();

            expect($this->evaluator->evaluate([
                'operator' => 'between',
                'start' => '2024-06-01',
                'end' => '2024-06-30',
            ], $context))->toBeFalse();
        });

        it('evaluates between operator includes full end day', function (): void {
            Carbon::setTestNow('2024-06-30 23:59:59');
            $context = createDateRangeContext();

            expect($this->evaluator->evaluate([
                'operator' => 'between',
                'start' => '2024-06-01',
                'end' => '2024-06-30',
            ], $context))->toBeTrue();
        });

        it('evaluates before operator correctly', function (): void {
            Carbon::setTestNow('2024-06-01 12:00:00');
            $context = createDateRangeContext();

            expect($this->evaluator->evaluate([
                'operator' => 'before',
                'value' => '2024-06-15',
            ], $context))->toBeTrue();

            expect($this->evaluator->evaluate([
                'operator' => 'before',
                'value' => '2024-05-01',
            ], $context))->toBeFalse();
        });

        it('evaluates after operator correctly', function (): void {
            Carbon::setTestNow('2024-06-30 12:00:00');
            $context = createDateRangeContext();

            expect($this->evaluator->evaluate([
                'operator' => 'after',
                'value' => '2024-06-15',
            ], $context))->toBeTrue();

            expect($this->evaluator->evaluate([
                'operator' => 'after',
                'value' => '2024-06-30',
            ], $context))->toBeFalse();
        });

        it('returns false for unknown operator', function (): void {
            Carbon::setTestNow('2024-06-15 12:00:00');
            $context = createDateRangeContext();

            expect($this->evaluator->evaluate([
                'operator' => 'unknown',
                'value' => '2024-06-01',
            ], $context))->toBeFalse();
        });

        it('uses between as default operator', function (): void {
            Carbon::setTestNow('2024-06-15 12:00:00');
            $context = createDateRangeContext();

            expect($this->evaluator->evaluate([
                'start' => '2024-06-01',
                'end' => '2024-06-30',
            ], $context))->toBeTrue();
        });

        it('returns false for invalid start date', function (): void {
            Carbon::setTestNow('2024-06-15 12:00:00');
            $context = createDateRangeContext();

            expect($this->evaluator->evaluate([
                'operator' => 'between',
                'start' => 'invalid-date',
                'end' => '2024-06-30',
            ], $context))->toBeFalse();
        });

        it('returns false for invalid end date', function (): void {
            Carbon::setTestNow('2024-06-15 12:00:00');
            $context = createDateRangeContext();

            expect($this->evaluator->evaluate([
                'operator' => 'between',
                'start' => '2024-06-01',
                'end' => 'invalid-date',
            ], $context))->toBeFalse();
        });

        it('returns false for invalid value date in before', function (): void {
            Carbon::setTestNow('2024-06-15 12:00:00');
            $context = createDateRangeContext();

            expect($this->evaluator->evaluate([
                'operator' => 'before',
                'value' => 'invalid',
            ], $context))->toBeFalse();
        });

        it('returns false for invalid value date in after', function (): void {
            Carbon::setTestNow('2024-06-15 12:00:00');
            $context = createDateRangeContext();

            expect($this->evaluator->evaluate([
                'operator' => 'after',
                'value' => 'invalid',
            ], $context))->toBeFalse();
        });

        it('respects rule timezone override', function (): void {
            Carbon::setTestNow('2024-06-15 08:00:00', 'UTC');
            $context = createDateRangeContext('UTC');

            // Rule specifies Asia/Tokyo timezone
            expect($this->evaluator->evaluate([
                'operator' => 'between',
                'start' => '2024-06-15',
                'end' => '2024-06-15',
                'timezone' => 'Asia/Tokyo',
            ], $context))->toBeTrue();
        });
    });

    describe('validate', function (): void {
        it('validates between operator requires start', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'between', 'end' => '2024-06-30']);

            expect($errors)->toContain('Start date is required for between operator');
        });

        it('validates between operator requires end', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'between', 'start' => '2024-06-01']);

            expect($errors)->toContain('End date is required for between operator');
        });

        it('validates between operator with valid dates', function (): void {
            $errors = $this->evaluator->validate([
                'operator' => 'between',
                'start' => '2024-06-01',
                'end' => '2024-06-30',
            ]);

            expect($errors)->toBe([]);
        });

        it('validates before operator requires value', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'before']);

            expect($errors)->toContain('Date value is required');
        });

        it('validates after operator requires value', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'after']);

            expect($errors)->toContain('Date value is required');
        });

        it('validates with valid value for non-between operators', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'before', 'value' => '2024-06-15']);

            expect($errors)->toBe([]);
        });
    });
});

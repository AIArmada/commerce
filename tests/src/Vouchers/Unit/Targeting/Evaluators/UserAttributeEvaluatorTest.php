<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Targeting\Evaluators;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\Evaluators\UserAttributeEvaluator;
use AIArmada\Vouchers\Targeting\TargetingContext;
use Illuminate\Database\Eloquent\Model;
use Mockery;

beforeEach(function (): void {
    $this->evaluator = new UserAttributeEvaluator;
});

/**
 * Creates a cart for testing.
 */
function createCartForUserAttrTest(): Cart
{
    return new Cart(new InMemoryStorage, 'user-attr-test');
}

/**
 * Creates a mock user model with specified attributes.
 *
 * @param  array<string, mixed>  $attributes
 * @return Mockery\MockInterface&Model
 */
function createMockUserWithAttributes(array $attributes): Mockery\MockInterface
{
    $mock = Mockery::mock(Model::class);
    $mock->shouldReceive('getKey')->andReturn(1);

    // Make attributes accessible via getAttribute
    foreach ($attributes as $key => $value) {
        $mock->shouldReceive('getAttribute')
            ->with($key)
            ->andReturn($value);
        $mock->shouldReceive('__isset')
            ->with($key)
            ->andReturn($value !== null);  // isset returns false for null
        $mock->shouldReceive('offsetExists')
            ->with($key)
            ->andReturn($value !== null);  // isset returns false for null
    }

    // Default null for unknown attributes
    $mock->shouldReceive('getAttribute')
        ->andReturn(null)
        ->byDefault();
    $mock->shouldReceive('__isset')
        ->andReturn(false)
        ->byDefault();
    $mock->shouldReceive('offsetExists')
        ->andReturn(false)
        ->byDefault();

    return $mock;
}

/**
 * Creates a mock user model with getter methods.
 *
 * @param  array<string, mixed>  $attributes
 * @return Mockery\MockInterface&Model
 */
function createMockUserWithGetters(array $attributes): Mockery\MockInterface
{
    $mock = Mockery::mock(Model::class);
    $mock->shouldReceive('getKey')->andReturn(1);

    foreach ($attributes as $key => $value) {
        // Create getter method name (e.g., 'member_level' -> 'getMemberLevel')
        $getterName = 'get' . str_replace('_', '', ucwords($key, '_'));
        $mock->shouldReceive($getterName)->andReturn($value);

        // Also make it accessible via getAttribute
        $mock->shouldReceive('getAttribute')
            ->with($key)
            ->andReturn($value);
        $mock->shouldReceive('__isset')
            ->with($key)
            ->andReturn($value !== null);
        $mock->shouldReceive('offsetExists')
            ->with($key)
            ->andReturn($value !== null);
    }

    // Default null for unknown attributes
    $mock->shouldReceive('getAttribute')
        ->andReturn(null)
        ->byDefault();
    $mock->shouldReceive('__isset')
        ->andReturn(false)
        ->byDefault();
    $mock->shouldReceive('offsetExists')
        ->andReturn(false)
        ->byDefault();

    return $mock;
}

describe('UserAttributeEvaluator supports', function (): void {
    it('supports user_attribute rule type', function (): void {
        expect($this->evaluator->supports(TargetingRuleType::UserAttribute->value))->toBeTrue();
    });

    it('does not support other rule types', function (): void {
        expect($this->evaluator->supports('cart_value'))->toBeFalse();
        expect($this->evaluator->supports('referrer'))->toBeFalse();
        expect($this->evaluator->supports('user_segment'))->toBeFalse();
    });
});

describe('UserAttributeEvaluator getType', function (): void {
    it('returns user_attribute type', function (): void {
        expect($this->evaluator->getType())->toBe('user_attribute');
    });
});

describe('UserAttributeEvaluator validate', function (): void {
    it('requires attribute name', function (): void {
        $errors = $this->evaluator->validate([]);
        expect($errors)->toContain('Attribute name must be a string');
    });

    it('requires attribute to be string', function (): void {
        $errors = $this->evaluator->validate(['attribute' => 123]);
        expect($errors)->toContain('Attribute name must be a string');
    });

    it('accepts valid rule with attribute', function (): void {
        $errors = $this->evaluator->validate([
            'attribute' => 'subscription_tier',
            'operator' => 'equals',
            'value' => 'premium',
        ]);
        expect($errors)->toBeEmpty();
    });

    it('validates operator', function (): void {
        $errors = $this->evaluator->validate([
            'attribute' => 'tier',
            'operator' => 'invalid_operator',
        ]);
        expect($errors)->toHaveCount(1);
        expect($errors[0])->toContain('Invalid operator');
    });

    it('accepts all valid operators', function (): void {
        $validOperators = [
            'equals', 'eq', '=',
            'not_equals', 'neq', '!=',
            'contains', 'starts_with', 'ends_with',
            'in', 'not_in',
            'gt', '>', 'gte', '>=',
            'lt', '<', 'lte', '<=',
            'exists', 'not_exists',
        ];

        foreach ($validOperators as $operator) {
            $errors = $this->evaluator->validate([
                'attribute' => 'test',
                'operator' => $operator,
            ]);
            expect($errors)->toBeEmpty();
        }
    });
});

describe('UserAttributeEvaluator evaluate without attribute', function (): void {
    it('returns false when no attribute specified', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['tier' => 'premium']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([], $context);
        expect($result)->toBeFalse();
    });
});

describe('UserAttributeEvaluator evaluate without user', function (): void {
    it('returns false when user is null', function (): void {
        $cart = createCartForUserAttrTest();
        $context = new TargetingContext($cart, null);

        $result = $this->evaluator->evaluate([
            'attribute' => 'subscription_tier',
            'operator' => 'equals',
            'value' => 'premium',
        ], $context);

        expect($result)->toBeFalse();
    });
});

describe('UserAttributeEvaluator evaluate equality operators', function (): void {
    it('matches with equals operator', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['subscription_tier' => 'premium']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'subscription_tier',
            'operator' => 'equals',
            'value' => 'premium',
        ], $context);

        expect($result)->toBeTrue();
    });

    it('matches with eq operator', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['level' => 5]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'level',
            'operator' => 'eq',
            'value' => 5,
        ], $context);

        expect($result)->toBeTrue();
    });

    it('matches with = operator', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['status' => 'active']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'status',
            'operator' => '=',
            'value' => 'active',
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match when values differ', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['subscription_tier' => 'basic']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'subscription_tier',
            'operator' => 'equals',
            'value' => 'premium',
        ], $context);

        expect($result)->toBeFalse();
    });
});

describe('UserAttributeEvaluator evaluate not_equals operators', function (): void {
    it('matches with not_equals operator when values differ', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['tier' => 'basic']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'tier',
            'operator' => 'not_equals',
            'value' => 'premium',
        ], $context);

        expect($result)->toBeTrue();
    });

    it('matches with neq operator', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['status' => 'pending']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'status',
            'operator' => 'neq',
            'value' => 'active',
        ], $context);

        expect($result)->toBeTrue();
    });

    it('matches with != operator', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['type' => 'individual']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'type',
            'operator' => '!=',
            'value' => 'business',
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match with not_equals when values are same', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['tier' => 'premium']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'tier',
            'operator' => 'not_equals',
            'value' => 'premium',
        ], $context);

        expect($result)->toBeFalse();
    });
});

describe('UserAttributeEvaluator evaluate string operators', function (): void {
    it('matches with contains operator', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['email' => 'john@example.com']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'email',
            'operator' => 'contains',
            'value' => 'example.com',
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match contains when substring not found', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['email' => 'john@gmail.com']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'email',
            'operator' => 'contains',
            'value' => 'example.com',
        ], $context);

        expect($result)->toBeFalse();
    });

    it('matches with starts_with operator', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['company' => 'Acme Corporation']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'company',
            'operator' => 'starts_with',
            'value' => 'Acme',
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match starts_with when prefix not found', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['company' => 'Globex Corporation']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'company',
            'operator' => 'starts_with',
            'value' => 'Acme',
        ], $context);

        expect($result)->toBeFalse();
    });

    it('matches with ends_with operator', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['email' => 'admin@company.com']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'email',
            'operator' => 'ends_with',
            'value' => '@company.com',
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match ends_with when suffix not found', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['email' => 'admin@other.com']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'email',
            'operator' => 'ends_with',
            'value' => '@company.com',
        ], $context);

        expect($result)->toBeFalse();
    });

    it('returns false for string operators with non-string values', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['level' => 5]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'level',
            'operator' => 'contains',
            'value' => '5',
        ], $context);

        expect($result)->toBeFalse();
    });
});

describe('UserAttributeEvaluator evaluate in/not_in operators', function (): void {
    it('matches with in operator when value in array', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['country' => 'US']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'country',
            'operator' => 'in',
            'value' => ['US', 'CA', 'UK'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match with in operator when value not in array', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['country' => 'AU']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'country',
            'operator' => 'in',
            'value' => ['US', 'CA', 'UK'],
        ], $context);

        expect($result)->toBeFalse();
    });

    it('matches with not_in operator when value not in array', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['country' => 'AU']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'country',
            'operator' => 'not_in',
            'value' => ['US', 'CA', 'UK'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match with not_in operator when value in array', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['country' => 'US']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'country',
            'operator' => 'not_in',
            'value' => ['US', 'CA', 'UK'],
        ], $context);

        expect($result)->toBeFalse();
    });
});

describe('UserAttributeEvaluator evaluate numeric comparison operators', function (): void {
    it('matches with gt operator for greater than', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['age' => 25]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'age',
            'operator' => 'gt',
            'value' => 21,
        ], $context);

        expect($result)->toBeTrue();
    });

    it('matches with symbol greater than operator', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['points' => 1000]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'points',
            'operator' => '>',
            'value' => 500,
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match gt when equal', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['age' => 21]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'age',
            'operator' => 'gt',
            'value' => 21,
        ], $context);

        expect($result)->toBeFalse();
    });

    it('matches with gte operator for greater than or equal', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['age' => 21]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'age',
            'operator' => 'gte',
            'value' => 21,
        ], $context);

        expect($result)->toBeTrue();
    });

    it('matches with symbol greater than or equal operator', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['balance' => 100.50]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'balance',
            'operator' => '>=',
            'value' => 100.00,
        ], $context);

        expect($result)->toBeTrue();
    });

    it('matches with lt operator for less than', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['age' => 18]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'age',
            'operator' => 'lt',
            'value' => 21,
        ], $context);

        expect($result)->toBeTrue();
    });

    it('matches with symbol less than operator', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['debt' => 50]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'debt',
            'operator' => '<',
            'value' => 100,
        ], $context);

        expect($result)->toBeTrue();
    });

    it('matches with lte operator for less than or equal', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['age' => 21]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'age',
            'operator' => 'lte',
            'value' => 21,
        ], $context);

        expect($result)->toBeTrue();
    });

    it('matches with symbol less than or equal operator', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['items' => 5]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'items',
            'operator' => '<=',
            'value' => 10,
        ], $context);

        expect($result)->toBeTrue();
    });

    it('returns false for numeric operators with non-numeric values', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['name' => 'John']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'name',
            'operator' => 'gt',
            'value' => 10,
        ], $context);

        expect($result)->toBeFalse();
    });
});

describe('UserAttributeEvaluator evaluate exists operators', function (): void {
    it('matches with exists operator when attribute has value', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['phone' => '555-1234']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'phone',
            'operator' => 'exists',
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match exists operator when attribute is null', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['phone' => null]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'phone',
            'operator' => 'exists',
        ], $context);

        expect($result)->toBeFalse();
    });

    it('matches with not_exists operator when attribute is null', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['phone' => null]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'phone',
            'operator' => 'not_exists',
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match not_exists operator when attribute has value', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['phone' => '555-1234']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'phone',
            'operator' => 'not_exists',
        ], $context);

        expect($result)->toBeFalse();
    });

    it('matches not_exists when attribute does not exist', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['name' => 'John']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'nonexistent_attribute',
            'operator' => 'not_exists',
        ], $context);

        expect($result)->toBeTrue();
    });
});

describe('UserAttributeEvaluator evaluate attribute retrieval via getter', function (): void {
    it('retrieves attribute via getter method', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithGetters(['subscription_tier' => 'gold']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'subscription_tier',
            'operator' => 'equals',
            'value' => 'gold',
        ], $context);

        expect($result)->toBeTrue();
    });
});

describe('UserAttributeEvaluator evaluate attribute from metadata', function (): void {
    it('retrieves attribute from context metadata', function (): void {
        $cart = createCartForUserAttrTest();
        // User without the attribute as property
        $user = createMockUserWithAttributes([]);
        $context = new TargetingContext($cart, $user, null, [
            'user_attributes' => [
                'loyalty_tier' => 'platinum',
            ],
        ]);

        $result = $this->evaluator->evaluate([
            'attribute' => 'loyalty_tier',
            'operator' => 'equals',
            'value' => 'platinum',
        ], $context);

        expect($result)->toBeTrue();
    });

    it('prefers user property over metadata', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['tier' => 'gold']);
        $context = new TargetingContext($cart, $user, null, [
            'user_attributes' => [
                'tier' => 'silver', // Different value in metadata
            ],
        ]);

        $result = $this->evaluator->evaluate([
            'attribute' => 'tier',
            'operator' => 'equals',
            'value' => 'gold',
        ], $context);

        expect($result)->toBeTrue();
    });
});

describe('UserAttributeEvaluator evaluate with invalid operator', function (): void {
    it('returns false for unknown operator', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['status' => 'active']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'status',
            'operator' => 'unknown_operator',
            'value' => 'active',
        ], $context);

        expect($result)->toBeFalse();
    });
});

describe('UserAttributeEvaluator evaluate with default equals operator', function (): void {
    it('uses equals operator by default', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['status' => 'active']);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'status',
            'value' => 'active',
        ], $context);

        expect($result)->toBeTrue();
    });
});

describe('UserAttributeEvaluator evaluate with boolean values', function (): void {
    it('matches boolean true with equals', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['is_verified' => true]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'is_verified',
            'operator' => 'equals',
            'value' => true,
        ], $context);

        expect($result)->toBeTrue();
    });

    it('matches boolean false with equals', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['is_verified' => false]);
        $context = new TargetingContext($cart, $user);

        $result = $this->evaluator->evaluate([
            'attribute' => 'is_verified',
            'operator' => 'equals',
            'value' => false,
        ], $context);

        expect($result)->toBeTrue();
    });

    it('distinguishes false from null with exists', function (): void {
        $cart = createCartForUserAttrTest();
        $user = createMockUserWithAttributes(['is_verified' => false]);
        $context = new TargetingContext($cart, $user);

        // false is not null, so exists should match
        $result = $this->evaluator->evaluate([
            'attribute' => 'is_verified',
            'operator' => 'exists',
        ], $context);

        expect($result)->toBeTrue();
    });
});

<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Targeting;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\TargetingContext;
use AIArmada\Vouchers\Targeting\TargetingEngine;
use Mockery;

afterEach(function (): void {
    Mockery::close();
});

/**
 * Create a simple targeting context.
 */
function createTargetingEngineContext(array $metadata = []): TargetingContext
{
    $cart = new Cart(new InMemoryStorage, 'engine-test-' . uniqid());
    $cart->add([
        'id' => 'product-1',
        'name' => 'Test Product',
        'price' => 5000,
        'quantity' => 2,
    ]);

    return new TargetingContext($cart, null, null, $metadata);
}

describe('TargetingEngine', function (): void {
    describe('constructor', function (): void {
        it('registers default evaluators', function (): void {
            $engine = new TargetingEngine;
            $evaluators = $engine->getEvaluators();

            expect($evaluators)->toHaveKey('user_segment');
            expect($evaluators)->toHaveKey('cart_value');
            expect($evaluators)->toHaveKey('cart_quantity');
            expect($evaluators)->toHaveKey('product_in_cart');
            expect($evaluators)->toHaveKey('category_in_cart');
            expect($evaluators)->toHaveKey('time_window');
            expect($evaluators)->toHaveKey('day_of_week');
            expect($evaluators)->toHaveKey('date_range');
            expect($evaluators)->toHaveKey('channel');
            expect($evaluators)->toHaveKey('device');
            expect($evaluators)->toHaveKey('geographic');
            expect($evaluators)->toHaveKey('first_purchase');
            expect($evaluators)->toHaveKey('clv');
        });
    });

    describe('registerEvaluator', function (): void {
        it('registers custom evaluator', function (): void {
            $engine = new TargetingEngine;

            $customEvaluator = Mockery::mock(TargetingRuleEvaluator::class);
            $customEvaluator->shouldReceive('getType')->andReturn('custom_type');

            $result = $engine->registerEvaluator($customEvaluator);

            expect($result)->toBe($engine);
            expect($engine->getEvaluator('custom_type'))->toBe($customEvaluator);
        });
    });

    describe('getEvaluator', function (): void {
        it('returns evaluator by type', function (): void {
            $engine = new TargetingEngine;

            $evaluator = $engine->getEvaluator('cart_value');

            expect($evaluator)->not->toBeNull();
            expect($evaluator->getType())->toBe('cart_value');
        });

        it('returns null for unknown type', function (): void {
            $engine = new TargetingEngine;

            expect($engine->getEvaluator('unknown_type'))->toBeNull();
        });
    });

    describe('evaluate', function (): void {
        it('returns true for empty targeting', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            expect($engine->evaluate([], $context))->toBeTrue();
        });

        it('uses all mode by default', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            // Cart has total 10000 (5000 * 2 items), so >= 5000 should pass
            $result = $engine->evaluate([
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                ],
            ], $context);

            expect($result)->toBeTrue();
        });

        it('evaluates with all mode - all rules must pass', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluate([
                'mode' => 'all',
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                    ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 2],
                ],
            ], $context);

            expect($result)->toBeTrue();
        });

        it('evaluates with all mode - fails if any rule fails', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluate([
                'mode' => 'all',
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 999999], // fails
                ],
            ], $context);

            expect($result)->toBeFalse();
        });

        it('evaluates with any mode - one rule must pass', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluate([
                'mode' => 'any',
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 999999], // fails
                    ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 2], // passes
                ],
            ], $context);

            expect($result)->toBeTrue();
        });

        it('evaluates with any mode - fails if all rules fail', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluate([
                'mode' => 'any',
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 999999],
                    ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 999],
                ],
            ], $context);

            expect($result)->toBeFalse();
        });

        it('evaluates with custom mode using expression', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluate([
                'mode' => 'custom',
                'expression' => [
                    'and' => [
                        ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                        ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 1],
                    ],
                ],
            ], $context);

            expect($result)->toBeTrue();
        });
    });

    describe('evaluateAll', function (): void {
        it('returns true for empty rules', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            expect($engine->evaluateAll([], $context))->toBeTrue();
        });

        it('returns true when all rules pass', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateAll([
                ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 2],
            ], $context);

            expect($result)->toBeTrue();
        });

        it('returns false when any rule fails', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateAll([
                ['type' => 'cart_value', 'operator' => '>=', 'value' => 999999],
            ], $context);

            expect($result)->toBeFalse();
        });
    });

    describe('evaluateAny', function (): void {
        it('returns true for empty rules', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            expect($engine->evaluateAny([], $context))->toBeTrue();
        });

        it('returns true when any rule passes', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateAny([
                ['type' => 'cart_value', 'operator' => '>=', 'value' => 999999],
                ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 2],
            ], $context);

            expect($result)->toBeTrue();
        });

        it('returns false when no rules pass', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateAny([
                ['type' => 'cart_value', 'operator' => '>=', 'value' => 999999],
                ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 999],
            ], $context);

            expect($result)->toBeFalse();
        });
    });

    describe('evaluateExpression', function (): void {
        it('returns true for empty expression', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            expect($engine->evaluateExpression([], $context))->toBeTrue();
        });

        it('evaluates AND expression', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateExpression([
                'and' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                    ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 2],
                ],
            ], $context);

            expect($result)->toBeTrue();
        });

        it('evaluates AND expression - fails if any fails', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateExpression([
                'and' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 999999],
                ],
            ], $context);

            expect($result)->toBeFalse();
        });

        it('returns false for invalid AND expression', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateExpression([
                'and' => 'not-an-array',
            ], $context);

            expect($result)->toBeFalse();
        });

        it('evaluates OR expression', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateExpression([
                'or' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 999999],
                    ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 2],
                ],
            ], $context);

            expect($result)->toBeTrue();
        });

        it('evaluates OR expression - fails if all fail', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateExpression([
                'or' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 999999],
                    ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 999],
                ],
            ], $context);

            expect($result)->toBeFalse();
        });

        it('returns false for invalid OR expression', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateExpression([
                'or' => 'not-an-array',
            ], $context);

            expect($result)->toBeFalse();
        });

        it('evaluates NOT expression', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateExpression([
                'not' => ['type' => 'cart_value', 'operator' => '>=', 'value' => 999999],
            ], $context);

            expect($result)->toBeTrue();
        });

        it('evaluates NOT expression - inverts true to false', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateExpression([
                'not' => ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
            ], $context);

            expect($result)->toBeFalse();
        });

        it('returns true for invalid NOT expression', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateExpression([
                'not' => 'not-an-array',
            ], $context);

            expect($result)->toBeTrue();
        });

        it('evaluates nested expressions', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            // (cart_value >= 5000) AND (cart_quantity >= 999 OR cart_quantity >= 2)
            $result = $engine->evaluateExpression([
                'and' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                    [
                        'or' => [
                            ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 999],
                            ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 2],
                        ],
                    ],
                ],
            ], $context);

            expect($result)->toBeTrue();
        });

        it('evaluates single rule expression', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateExpression([
                'type' => 'cart_value',
                'operator' => '>=',
                'value' => 5000,
            ], $context);

            expect($result)->toBeTrue();
        });
    });

    describe('evaluateRule', function (): void {
        it('returns true for empty rule type', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            expect($engine->evaluateRule([], $context))->toBeTrue();
        });

        it('returns true for unknown rule type', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            expect($engine->evaluateRule(['type' => 'unknown'], $context))->toBeTrue();
        });

        it('evaluates known rule type', function (): void {
            $engine = new TargetingEngine;
            $context = createTargetingEngineContext();

            $result = $engine->evaluateRule([
                'type' => 'cart_value',
                'operator' => '>=',
                'value' => 5000,
            ], $context);

            expect($result)->toBeTrue();
        });
    });

    describe('validate', function (): void {
        it('returns error for invalid mode', function (): void {
            $engine = new TargetingEngine;

            $errors = $engine->validate(['mode' => 'invalid_mode']);

            expect($errors[0])->toContain('Invalid targeting mode');
        });

        it('validates rules for non-custom modes', function (): void {
            $engine = new TargetingEngine;

            $errors = $engine->validate([
                'mode' => 'all',
                'rules' => 'not-an-array',
            ]);

            expect($errors)->toContain('Rules must be an array');
        });

        it('validates custom mode requires expression', function (): void {
            $engine = new TargetingEngine;

            $errors = $engine->validate([
                'mode' => 'custom',
            ]);

            expect($errors)->toContain('Custom mode requires an expression');
        });

        it('validates custom mode expression must be array', function (): void {
            $engine = new TargetingEngine;

            $errors = $engine->validate([
                'mode' => 'custom',
                'expression' => 'not-an-array',
            ]);

            expect($errors)->toContain('Custom mode requires an expression');
        });

        it('validates rule types', function (): void {
            $engine = new TargetingEngine;

            $errors = $engine->validate([
                'mode' => 'all',
                'rules' => [
                    ['type' => 'unknown_rule_type'],
                ],
            ]);

            expect($errors[0])->toContain('Unknown rule type');
        });

        it('validates rule type is required', function (): void {
            $engine = new TargetingEngine;

            $errors = $engine->validate([
                'mode' => 'all',
                'rules' => [
                    [],
                ],
            ]);

            expect($errors[0])->toContain('Rule type is required');
        });

        it('validates expression AND must be array', function (): void {
            $engine = new TargetingEngine;

            $errors = $engine->validate([
                'mode' => 'custom',
                'expression' => ['and' => 'not-array'],
            ]);

            expect($errors)->toContain('AND expression must be an array');
        });

        it('validates expression OR must be array', function (): void {
            $engine = new TargetingEngine;

            $errors = $engine->validate([
                'mode' => 'custom',
                'expression' => ['or' => 'not-array'],
            ]);

            expect($errors)->toContain('OR expression must be an array');
        });

        it('validates expression NOT must be object', function (): void {
            $engine = new TargetingEngine;

            $errors = $engine->validate([
                'mode' => 'custom',
                'expression' => ['not' => 'not-array'],
            ]);

            expect($errors)->toContain('NOT expression must be an object');
        });

        it('validates nested expression', function (): void {
            $engine = new TargetingEngine;

            $errors = $engine->validate([
                'mode' => 'custom',
                'expression' => [
                    'and' => [
                        ['type' => ''],
                    ],
                ],
            ]);

            expect($errors[0])->toContain('Rule type is required');
        });

        it('returns no errors for valid configuration', function (): void {
            $engine = new TargetingEngine;

            $errors = $engine->validate([
                'mode' => 'all',
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                ],
            ]);

            expect($errors)->toBe([]);
        });

        it('validates operator for rule type', function (): void {
            $engine = new TargetingEngine;

            $errors = $engine->validate([
                'mode' => 'all',
                'rules' => [
                    ['type' => 'cart_value', 'operator' => 'invalid_op', 'value' => 5000],
                ],
            ]);

            expect($errors[0])->toContain("Invalid operator 'invalid_op'");
        });
    });
});

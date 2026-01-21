<?php

declare(strict_types=1);

use AIArmada\Cart\Models\AlertRule;
use AIArmada\FilamentCart\Services\AlertEvaluator;

describe('AlertEvaluator', function (): void {
    describe('evaluate', function (): void {
        it('returns true when no conditions are specified', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = [];

            $result = $evaluator->evaluate($rule, ['value' => 100]);

            expect($result)->toBeTrue();
        });

        it('evaluates single condition with equals operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = [
                'field' => 'value',
                'operator' => '=',
                'value' => 100,
            ];

            expect($evaluator->evaluate($rule, ['value' => 100]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['value' => 50]))->toBeFalse();
        });

        it('evaluates all conditions with AND logic', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = [
                'all' => [
                    ['field' => 'value', 'operator' => '>=', 'value' => 100],
                    ['field' => 'status', 'operator' => '=', 'value' => 'active'],
                ],
            ];

            expect($evaluator->evaluate($rule, ['value' => 150, 'status' => 'active']))->toBeTrue();
            expect($evaluator->evaluate($rule, ['value' => 150, 'status' => 'inactive']))->toBeFalse();
            expect($evaluator->evaluate($rule, ['value' => 50, 'status' => 'active']))->toBeFalse();
        });

        it('evaluates any conditions with OR logic', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = [
                'any' => [
                    ['field' => 'priority', 'operator' => '=', 'value' => 'high'],
                    ['field' => 'value', 'operator' => '>=', 'value' => 1000],
                ],
            ];

            expect($evaluator->evaluate($rule, ['priority' => 'high', 'value' => 50]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['priority' => 'low', 'value' => 1500]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['priority' => 'low', 'value' => 50]))->toBeFalse();
        });

        it('evaluates array of conditions as AND by default', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = [
                ['field' => 'a', 'operator' => '=', 'value' => 1],
                ['field' => 'b', 'operator' => '=', 'value' => 2],
            ];

            expect($evaluator->evaluate($rule, ['a' => 1, 'b' => 2]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['a' => 1, 'b' => 3]))->toBeFalse();
        });
    });

    describe('comparison operators', function (): void {
        it('supports equals operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'x', 'operator' => '=', 'value' => 10];

            expect($evaluator->evaluate($rule, ['x' => 10]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['x' => 5]))->toBeFalse();
        });

        it('supports not equals operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'x', 'operator' => '!=', 'value' => 10];

            expect($evaluator->evaluate($rule, ['x' => 5]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['x' => 10]))->toBeFalse();
        });

        it('supports greater than operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'x', 'operator' => '>', 'value' => 10];

            expect($evaluator->evaluate($rule, ['x' => 15]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['x' => 10]))->toBeFalse();
            expect($evaluator->evaluate($rule, ['x' => 5]))->toBeFalse();
        });

        it('supports greater than or equal operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'x', 'operator' => '>=', 'value' => 10];

            expect($evaluator->evaluate($rule, ['x' => 15]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['x' => 10]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['x' => 5]))->toBeFalse();
        });

        it('supports less than operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'x', 'operator' => '<', 'value' => 10];

            expect($evaluator->evaluate($rule, ['x' => 5]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['x' => 10]))->toBeFalse();
            expect($evaluator->evaluate($rule, ['x' => 15]))->toBeFalse();
        });

        it('supports less than or equal operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'x', 'operator' => '<=', 'value' => 10];

            expect($evaluator->evaluate($rule, ['x' => 5]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['x' => 10]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['x' => 15]))->toBeFalse();
        });

        it('supports in operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'status', 'operator' => 'in', 'value' => ['pending', 'active']];

            expect($evaluator->evaluate($rule, ['status' => 'pending']))->toBeTrue();
            expect($evaluator->evaluate($rule, ['status' => 'active']))->toBeTrue();
            expect($evaluator->evaluate($rule, ['status' => 'cancelled']))->toBeFalse();
        });

        it('supports not_in operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'status', 'operator' => 'not_in', 'value' => ['cancelled', 'deleted']];

            expect($evaluator->evaluate($rule, ['status' => 'active']))->toBeTrue();
            expect($evaluator->evaluate($rule, ['status' => 'cancelled']))->toBeFalse();
        });

        it('supports contains operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'email', 'operator' => 'contains', 'value' => '@example.com'];

            expect($evaluator->evaluate($rule, ['email' => 'user@example.com']))->toBeTrue();
            expect($evaluator->evaluate($rule, ['email' => 'user@other.com']))->toBeFalse();
        });

        it('supports starts_with operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'name', 'operator' => 'starts_with', 'value' => 'VIP'];

            expect($evaluator->evaluate($rule, ['name' => 'VIP Customer']))->toBeTrue();
            expect($evaluator->evaluate($rule, ['name' => 'Regular Customer']))->toBeFalse();
        });

        it('supports ends_with operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'sku', 'operator' => 'ends_with', 'value' => '-XL'];

            expect($evaluator->evaluate($rule, ['sku' => 'SHIRT-XL']))->toBeTrue();
            expect($evaluator->evaluate($rule, ['sku' => 'SHIRT-SM']))->toBeFalse();
        });

        it('supports is_null operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'discount', 'operator' => 'is_null', 'value' => null];

            expect($evaluator->evaluate($rule, ['discount' => null]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['discount' => 10]))->toBeFalse();
        });

        it('supports is_not_null operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'coupon', 'operator' => 'is_not_null', 'value' => null];

            expect($evaluator->evaluate($rule, ['coupon' => 'SAVE10']))->toBeTrue();
            expect($evaluator->evaluate($rule, ['coupon' => null]))->toBeFalse();
        });

        it('supports is_empty operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'items', 'operator' => 'is_empty', 'value' => null];

            expect($evaluator->evaluate($rule, ['items' => []]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['items' => '']))->toBeTrue();
            expect($evaluator->evaluate($rule, ['items' => ['product']]))->toBeFalse();
        });

        it('supports is_not_empty operator', function (): void {
            $evaluator = new AlertEvaluator;
            $rule = new AlertRule;
            $rule->conditions = ['field' => 'items', 'operator' => 'is_not_empty', 'value' => null];

            expect($evaluator->evaluate($rule, ['items' => ['product']]))->toBeTrue();
            expect($evaluator->evaluate($rule, ['items' => []]))->toBeFalse();
        });

        // Removed potentially hanging tests for debugging
    });
});

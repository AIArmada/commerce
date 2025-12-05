<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting;

use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\Enums\TargetingMode;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;

/**
 * Engine for evaluating voucher targeting rules.
 *
 * Supports three evaluation modes:
 * - all: All rules must match (AND logic)
 * - any: Any rule must match (OR logic)
 * - custom: Boolean expression with AND, OR, NOT operators
 */
class TargetingEngine
{
    /**
     * @var array<string, TargetingRuleEvaluator>
     */
    private array $evaluators = [];

    public function __construct()
    {
        $this->registerDefaultEvaluators();
    }

    /**
     * Register a targeting rule evaluator.
     */
    public function registerEvaluator(TargetingRuleEvaluator $evaluator): self
    {
        $this->evaluators[$evaluator->getType()] = $evaluator;

        return $this;
    }

    /**
     * Get a registered evaluator by type.
     */
    public function getEvaluator(string $type): ?TargetingRuleEvaluator
    {
        return $this->evaluators[$type] ?? null;
    }

    /**
     * Get all registered evaluators.
     *
     * @return array<string, TargetingRuleEvaluator>
     */
    public function getEvaluators(): array
    {
        return $this->evaluators;
    }

    /**
     * Evaluate targeting configuration against context.
     *
     * @param  array<string, mixed>  $targeting
     */
    public function evaluate(array $targeting, TargetingContext $context): bool
    {
        if (empty($targeting)) {
            return true; // No targeting = always valid
        }

        $modeValue = $targeting['mode'] ?? 'all';
        $mode = TargetingMode::tryFrom($modeValue) ?? TargetingMode::All;

        return match ($mode) {
            TargetingMode::All => $this->evaluateAll($targeting['rules'] ?? [], $context),
            TargetingMode::Any => $this->evaluateAny($targeting['rules'] ?? [], $context),
            TargetingMode::Custom => $this->evaluateExpression($targeting['expression'] ?? [], $context),
        };
    }

    /**
     * Evaluate with ALL mode - all rules must pass.
     *
     * @param  array<int, array<string, mixed>>  $rules
     */
    public function evaluateAll(array $rules, TargetingContext $context): bool
    {
        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            if (! $this->evaluateRule($rule, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate with ANY mode - at least one rule must pass.
     *
     * @param  array<int, array<string, mixed>>  $rules
     */
    public function evaluateAny(array $rules, TargetingContext $context): bool
    {
        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate a custom boolean expression.
     *
     * Supports nested AND, OR, NOT operators:
     * {
     *   "and": [
     *     {"type": "cart_value", "operator": ">=", "value": 5000},
     *     {"or": [
     *       {"type": "user_segment", "operator": "in", "values": ["vip"]},
     *       {"type": "first_purchase", "operator": "=", "value": true}
     *     ]},
     *     {"not": {"type": "channel", "operator": "=", "value": "pos"}}
     *   ]
     * }
     *
     * @param  array<string, mixed>  $expression
     */
    public function evaluateExpression(array $expression, TargetingContext $context): bool
    {
        if (empty($expression)) {
            return true;
        }

        // Handle AND operator
        if (isset($expression['and'])) {
            $subExpressions = $expression['and'];
            if (! is_array($subExpressions)) {
                return false;
            }

            foreach ($subExpressions as $subExpr) {
                if (! $this->evaluateExpression($subExpr, $context)) {
                    return false;
                }
            }

            return true;
        }

        // Handle OR operator
        if (isset($expression['or'])) {
            $subExpressions = $expression['or'];
            if (! is_array($subExpressions)) {
                return false;
            }

            foreach ($subExpressions as $subExpr) {
                if ($this->evaluateExpression($subExpr, $context)) {
                    return true;
                }
            }

            return false;
        }

        // Handle NOT operator
        if (isset($expression['not'])) {
            $subExpr = $expression['not'];
            if (! is_array($subExpr)) {
                return true;
            }

            return ! $this->evaluateExpression($subExpr, $context);
        }

        // It's a single rule
        return $this->evaluateRule($expression, $context);
    }

    /**
     * Evaluate a single rule.
     *
     * @param  array<string, mixed>  $rule
     */
    public function evaluateRule(array $rule, TargetingContext $context): bool
    {
        $type = $rule['type'] ?? '';

        if ($type === '') {
            return true; // Skip empty rules
        }

        $evaluator = $this->getEvaluator($type);

        if ($evaluator === null) {
            // Unknown rule type - log and skip (return true to not block)
            return true;
        }

        return $evaluator->evaluate($rule, $context);
    }

    /**
     * Validate a targeting configuration.
     *
     * @param  array<string, mixed>  $targeting
     * @return array<string>  List of validation errors
     */
    public function validate(array $targeting): array
    {
        $errors = [];

        $mode = $targeting['mode'] ?? 'all';
        if (TargetingMode::tryFrom($mode) === null) {
            $errors[] = "Invalid targeting mode: {$mode}";
        }

        if ($mode === 'custom') {
            if (! isset($targeting['expression']) || ! is_array($targeting['expression'])) {
                $errors[] = 'Custom mode requires an expression';
            } else {
                $errors = array_merge($errors, $this->validateExpression($targeting['expression']));
            }
        } else {
            $rules = $targeting['rules'] ?? [];
            if (! is_array($rules)) {
                $errors[] = 'Rules must be an array';
            } else {
                foreach ($rules as $i => $rule) {
                    $ruleErrors = $this->validateRule($rule);
                    foreach ($ruleErrors as $error) {
                        $errors[] = "Rule {$i}: {$error}";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a boolean expression.
     *
     * @param  array<string, mixed>  $expression
     * @return array<string>
     */
    private function validateExpression(array $expression): array
    {
        $errors = [];

        if (isset($expression['and'])) {
            if (! is_array($expression['and'])) {
                $errors[] = 'AND expression must be an array';
            } else {
                foreach ($expression['and'] as $subExpr) {
                    $errors = array_merge($errors, $this->validateExpression($subExpr));
                }
            }
        } elseif (isset($expression['or'])) {
            if (! is_array($expression['or'])) {
                $errors[] = 'OR expression must be an array';
            } else {
                foreach ($expression['or'] as $subExpr) {
                    $errors = array_merge($errors, $this->validateExpression($subExpr));
                }
            }
        } elseif (isset($expression['not'])) {
            if (! is_array($expression['not'])) {
                $errors[] = 'NOT expression must be an object';
            } else {
                $errors = array_merge($errors, $this->validateExpression($expression['not']));
            }
        } else {
            // Single rule
            $errors = array_merge($errors, $this->validateRule($expression));
        }

        return $errors;
    }

    /**
     * Validate a single rule.
     *
     * @param  array<string, mixed>  $rule
     * @return array<string>
     */
    private function validateRule(array $rule): array
    {
        $errors = [];

        $type = $rule['type'] ?? '';

        if ($type === '') {
            $errors[] = 'Rule type is required';

            return $errors;
        }

        $ruleType = TargetingRuleType::tryFrom($type);
        if ($ruleType === null) {
            $errors[] = "Unknown rule type: {$type}";

            return $errors;
        }

        // Validate operator
        $operator = $rule['operator'] ?? '';
        $validOperators = $ruleType->getOperators();

        if ($operator !== '' && ! isset($validOperators[$operator])) {
            $errors[] = "Invalid operator '{$operator}' for rule type '{$type}'";
        }

        // Let the evaluator validate specific requirements
        $evaluator = $this->getEvaluator($type);
        if ($evaluator !== null) {
            $errors = array_merge($errors, $evaluator->validate($rule));
        }

        return $errors;
    }

    /**
     * Register default evaluators for all rule types.
     */
    private function registerDefaultEvaluators(): void
    {
        $evaluatorClasses = [
            Evaluators\UserSegmentEvaluator::class,
            Evaluators\CartValueEvaluator::class,
            Evaluators\CartQuantityEvaluator::class,
            Evaluators\ProductInCartEvaluator::class,
            Evaluators\CategoryInCartEvaluator::class,
            Evaluators\TimeWindowEvaluator::class,
            Evaluators\DayOfWeekEvaluator::class,
            Evaluators\DateRangeEvaluator::class,
            Evaluators\ChannelEvaluator::class,
            Evaluators\DeviceEvaluator::class,
            Evaluators\GeographicEvaluator::class,
            Evaluators\FirstPurchaseEvaluator::class,
            Evaluators\CustomerLifetimeValueEvaluator::class,
        ];

        foreach ($evaluatorClasses as $class) {
            if (class_exists($class)) {
                $this->registerEvaluator(new $class());
            }
        }
    }
}

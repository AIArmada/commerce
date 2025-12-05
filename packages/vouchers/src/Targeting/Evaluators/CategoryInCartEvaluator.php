<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Evaluators;

use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\TargetingContext;

/**
 * Evaluates category-in-cart targeting rules.
 */
class CategoryInCartEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::CategoryInCart->value;
    }

    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $cartCategories = $context->getProductCategories();
        $targetCategories = $rule['values'] ?? [];
        $operator = $rule['operator'] ?? 'in';

        if (empty($targetCategories)) {
            return true;
        }

        return match ($operator) {
            'in', 'contains_any' => ! empty(array_intersect($cartCategories, $targetCategories)),
            'not_in' => empty(array_intersect($cartCategories, $targetCategories)),
            'contains_all' => empty(array_diff($targetCategories, $cartCategories)),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::CategoryInCart->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        if (! isset($rule['values']) || ! is_array($rule['values'])) {
            $errors[] = 'Values must be an array of category slugs/IDs';
        }

        return $errors;
    }
}

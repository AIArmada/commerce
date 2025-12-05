<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Evaluators;

use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\TargetingContext;

/**
 * Evaluates product-in-cart targeting rules.
 */
class ProductInCartEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::ProductInCart->value;
    }

    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $cartProducts = $context->getProductIdentifiers();
        $targetProducts = $rule['values'] ?? [];
        $operator = $rule['operator'] ?? 'in';

        if (empty($targetProducts)) {
            return true;
        }

        return match ($operator) {
            'in', 'contains_any' => ! empty(array_intersect($cartProducts, $targetProducts)),
            'not_in' => empty(array_intersect($cartProducts, $targetProducts)),
            'contains_all' => empty(array_diff($targetProducts, $cartProducts)),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::ProductInCart->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        if (! isset($rule['values']) || ! is_array($rule['values'])) {
            $errors[] = 'Values must be an array of product SKUs/IDs';
        }

        return $errors;
    }
}

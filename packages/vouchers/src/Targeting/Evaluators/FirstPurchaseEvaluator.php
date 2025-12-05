<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Evaluators;

use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\TargetingContext;

/**
 * Evaluates first purchase targeting rules.
 */
class FirstPurchaseEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::FirstPurchase->value;
    }

    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $isFirstPurchase = $context->isFirstPurchase();
        $targetValue = (bool) ($rule['value'] ?? true);

        return $isFirstPurchase === $targetValue;
    }

    public function getType(): string
    {
        return TargetingRuleType::FirstPurchase->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        if (isset($rule['value']) && ! is_bool($rule['value']) && ! in_array($rule['value'], [0, 1, '0', '1', 'true', 'false'], true)) {
            $errors[] = 'Value must be a boolean';
        }

        return $errors;
    }
}

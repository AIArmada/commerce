<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Evaluators;

use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\TargetingContext;

/**
 * Evaluates customer lifetime value (CLV) targeting rules.
 */
class CustomerLifetimeValueEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::CustomerLifetimeValue->value;
    }

    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $clv = $context->getCustomerLifetimeValue();
        $operator = $rule['operator'] ?? '>=';

        return match ($operator) {
            '=' => $clv === (int) ($rule['value'] ?? 0),
            '!=' => $clv !== (int) ($rule['value'] ?? 0),
            '>' => $clv > (int) ($rule['value'] ?? 0),
            '>=' => $clv >= (int) ($rule['value'] ?? 0),
            '<' => $clv < (int) ($rule['value'] ?? 0),
            '<=' => $clv <= (int) ($rule['value'] ?? 0),
            'between' => $clv >= (int) ($rule['min'] ?? 0) && $clv <= (int) ($rule['max'] ?? PHP_INT_MAX),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::CustomerLifetimeValue->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];
        $operator = $rule['operator'] ?? '>=';

        if ($operator === 'between') {
            if (! isset($rule['min']) || ! is_numeric($rule['min'])) {
                $errors[] = 'Min value is required for between operator';
            }
            if (! isset($rule['max']) || ! is_numeric($rule['max'])) {
                $errors[] = 'Max value is required for between operator';
            }
        } else {
            if (! isset($rule['value']) || ! is_numeric($rule['value'])) {
                $errors[] = 'Value must be a number';
            }
        }

        return $errors;
    }
}

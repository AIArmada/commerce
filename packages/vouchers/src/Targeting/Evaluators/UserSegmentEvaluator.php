<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Evaluators;

use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\TargetingContext;

/**
 * Evaluates user segment targeting rules.
 */
class UserSegmentEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::UserSegment->value;
    }

    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $userSegments = $context->getUserSegments();
        $targetSegments = $rule['values'] ?? [];
        $operator = $rule['operator'] ?? 'in';

        if (empty($targetSegments)) {
            return true;
        }

        return match ($operator) {
            'in' => ! empty(array_intersect($userSegments, $targetSegments)),
            'not_in' => empty(array_intersect($userSegments, $targetSegments)),
            'contains_any' => ! empty(array_intersect($userSegments, $targetSegments)),
            'contains_all' => empty(array_diff($targetSegments, $userSegments)),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::UserSegment->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        if (! isset($rule['values']) || ! is_array($rule['values'])) {
            $errors[] = 'Values must be an array of segments';
        }

        return $errors;
    }
}

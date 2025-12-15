<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting\Evaluators;

use AIArmada\Vouchers\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\TargetingContext;

/**
 * Evaluates user attribute targeting rules.
 *
 * Supports targeting based on custom user attributes like:
 * - subscription_tier
 * - account_type
 * - custom fields
 */
class UserAttributeEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::UserAttribute->value;
    }

    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $attribute = $rule['attribute'] ?? null;
        $operator = $rule['operator'] ?? 'equals';
        $value = $rule['value'] ?? null;

        if ($attribute === null) {
            return false;
        }

        $user = $context->user;
        if ($user === null) {
            return false;
        }

        // Get the attribute value from user metadata or model
        $userValue = $this->getUserAttributeValue($user, $attribute, $context);

        return $this->compareValues($userValue, $value, $operator);
    }

    public function getType(): string
    {
        return TargetingRuleType::UserAttribute->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        if (! isset($rule['attribute']) || ! is_string($rule['attribute'])) {
            $errors[] = 'Attribute name must be a string';
        }

        $validOperators = ['equals', 'eq', '=', 'not_equals', 'neq', '!=', 'contains', 'starts_with', 'ends_with', 'in', 'not_in', 'gt', '>', 'gte', '>=', 'lt', '<', 'lte', '<=', 'exists', 'not_exists'];
        if (isset($rule['operator']) && ! in_array($rule['operator'], $validOperators, true)) {
            $errors[] = 'Invalid operator. Valid operators: ' . implode(', ', $validOperators);
        }

        return $errors;
    }

    /**
     * Get attribute value from user model or metadata.
     */
    private function getUserAttributeValue(object $user, string $attribute, TargetingContext $context): mixed
    {
        // For Eloquent models, use getAttribute if available
        if (method_exists($user, 'getAttribute')) {
            $value = $user->getAttribute($attribute);
            if ($value !== null) {
                return $value;
            }
        }

        // Check for getter method
        $getter = 'get' . str_replace('_', '', ucwords($attribute, '_'));
        if (method_exists($user, $getter)) {
            return $user->{$getter}();
        }

        // Try accessing via magic __get (for objects with __get)
        if (isset($user->{$attribute})) {
            return $user->{$attribute};
        }

        // Check metadata from context
        if (isset($context->metadata['user_attributes'][$attribute])) {
            return $context->metadata['user_attributes'][$attribute];
        }

        return null;
    }

    /**
     * Compare values based on operator.
     */
    private function compareValues(mixed $userValue, mixed $targetValue, string $operator): bool
    {
        return match ($operator) {
            'equals', 'eq', '=' => $userValue === $targetValue,
            'not_equals', 'neq', '!=' => $userValue !== $targetValue,
            'contains' => is_string($userValue) && is_string($targetValue) && str_contains($userValue, $targetValue),
            'starts_with' => is_string($userValue) && is_string($targetValue) && str_starts_with($userValue, $targetValue),
            'ends_with' => is_string($userValue) && is_string($targetValue) && str_ends_with($userValue, $targetValue),
            'in' => is_array($targetValue) && in_array($userValue, $targetValue, true),
            'not_in' => is_array($targetValue) && ! in_array($userValue, $targetValue, true),
            'gt', '>' => is_numeric($userValue) && is_numeric($targetValue) && $userValue > $targetValue,
            'gte', '>=' => is_numeric($userValue) && is_numeric($targetValue) && $userValue >= $targetValue,
            'lt', '<' => is_numeric($userValue) && is_numeric($targetValue) && $userValue < $targetValue,
            'lte', '<=' => is_numeric($userValue) && is_numeric($targetValue) && $userValue <= $targetValue,
            'exists' => $userValue !== null,
            'not_exists' => $userValue === null,
            default => false,
        };
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Approvals\Support;

use BackedEnum;

final readonly class WorkflowConditions
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $conditions
     */
    public static function matches(array $attributes, array $conditions): bool
    {
        foreach ($conditions as $field => $condition) {
            if (! is_string($field) || ! array_key_exists($field, $attributes)) {
                return false;
            }

            $value = $attributes[$field];

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            if (! self::matchesCondition($value, $condition)) {
                return false;
            }
        }

        return true;
    }

    public static function matchesOwner(?string $ruleType, int|string|null $ruleId, ?string $docType, int|string|null $docId, bool $includeGlobal): bool
    {
        if (($ruleType === null) !== ($ruleId === null) || ($docType === null) !== ($docId === null)) {
            return false;
        }

        if ($ruleType === $docType && (string) $ruleId === (string) $docId) {
            return true;
        }

        return $includeGlobal && $ruleType === null && $ruleId === null;
    }

    private static function matchesCondition(mixed $value, mixed $condition): bool
    {
        if (! is_array($condition)) {
            return $value === $condition;
        }

        $operator = array_key_exists('operator', $condition) ? $condition['operator'] : '=';
        if (! array_key_exists('value', $condition)) {
            return false;
        }

        $expected = $condition['value'];

        return match ($operator) {
            '=' => $value === $expected,
            '!=' => $value !== $expected,
            '>' => is_int($value) && is_int($expected) && $value > $expected,
            '>=' => is_int($value) && is_int($expected) && $value >= $expected,
            '<' => is_int($value) && is_int($expected) && $value < $expected,
            '<=' => is_int($value) && is_int($expected) && $value <= $expected,
            'in' => is_array($expected) && array_is_list($expected) && in_array($value, $expected, true),
            'not_in' => is_array($expected) && array_is_list($expected) && ! in_array($value, $expected, true),
            default => false,
        };
    }
}

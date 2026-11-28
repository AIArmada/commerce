<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Queries;

use AIArmada\Customers\Enums\CustomerStatus;
use BackedEnum;

final class SegmentConditionValue
{
    /**
     * Resolve the field-specific typed value for a segment condition.
     *
     * @param  array<string, mixed>  $condition
     */
    public static function resolve(string $field, array $condition): bool | int | string | null
    {
        return match ($field) {
            'accepts_marketing' => self::boolean($condition['value_boolean'] ?? $condition['value'] ?? null),
            'status' => self::status($condition['value_status'] ?? $condition['value'] ?? null),
            'created_days_ago' => self::integer($condition['value_numeric'] ?? $condition['value'] ?? null),
            default => null,
        };
    }

    private static function boolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            return match (mb_strtolower(mb_trim($value))) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off', '' => false,
                default => null,
            };
        }

        return null;
    }

    private static function status(mixed $value): ?string
    {
        if ($value instanceof CustomerStatus) {
            return $value->value;
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }

    private static function integer(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && (float) (int) $value === $value) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}

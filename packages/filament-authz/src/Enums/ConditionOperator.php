<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Enums;

enum ConditionOperator: string
{
    // Equality
    case Equals = 'eq';
    case NotEquals = 'neq';

    // Comparison
    case GreaterThan = 'gt';
    case GreaterThanOrEquals = 'gte';
    case LessThan = 'lt';
    case LessThanOrEquals = 'lte';

    // String
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case Matches = 'matches';

    // Collection
    case In = 'in';
    case NotIn = 'not_in';
    case ContainsAny = 'contains_any';
    case ContainsAll = 'contains_all';

    // Null checks
    case IsNull = 'is_null';
    case IsNotNull = 'is_not_null';

    // Boolean
    case IsTrue = 'is_true';
    case IsFalse = 'is_false';

    // Date/Time
    case Before = 'before';
    case After = 'after';
    case Between = 'between';

    public function label(): string
    {
        return match ($this) {
            self::Equals => 'Equals',
            self::NotEquals => 'Not Equals',
            self::GreaterThan => 'Greater Than',
            self::GreaterThanOrEquals => 'Greater Than or Equals',
            self::LessThan => 'Less Than',
            self::LessThanOrEquals => 'Less Than or Equals',
            self::Contains => 'Contains',
            self::NotContains => 'Does Not Contain',
            self::StartsWith => 'Starts With',
            self::EndsWith => 'Ends With',
            self::Matches => 'Matches Pattern',
            self::In => 'In List',
            self::NotIn => 'Not In List',
            self::ContainsAny => 'Contains Any Of',
            self::ContainsAll => 'Contains All Of',
            self::IsNull => 'Is Null',
            self::IsNotNull => 'Is Not Null',
            self::IsTrue => 'Is True',
            self::IsFalse => 'Is False',
            self::Before => 'Before',
            self::After => 'After',
            self::Between => 'Between',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Equals => '=',
            self::NotEquals => '≠',
            self::GreaterThan => '>',
            self::GreaterThanOrEquals => '≥',
            self::LessThan => '<',
            self::LessThanOrEquals => '≤',
            self::Contains => '∋',
            self::NotContains => '∌',
            self::StartsWith => '^=',
            self::EndsWith => '$=',
            self::Matches => '~=',
            self::In => '∈',
            self::NotIn => '∉',
            self::ContainsAny => '∩≠∅',
            self::ContainsAll => '⊇',
            self::IsNull => '∅',
            self::IsNotNull => '≠∅',
            self::IsTrue => '✓',
            self::IsFalse => '✗',
            self::Before => '<',
            self::After => '>',
            self::Between => '⊂',
        };
    }

    public function requiresValue(): bool
    {
        return match ($this) {
            self::IsNull,
            self::IsNotNull,
            self::IsTrue,
            self::IsFalse => false,
            default => true,
        };
    }

    public function isArrayOperator(): bool
    {
        return match ($this) {
            self::In,
            self::NotIn,
            self::ContainsAny,
            self::ContainsAll,
            self::Between => true,
            default => false,
        };
    }

    public function isStringOperator(): bool
    {
        return match ($this) {
            self::Contains,
            self::NotContains,
            self::StartsWith,
            self::EndsWith,
            self::Matches => true,
            default => false,
        };
    }

    public function isDateOperator(): bool
    {
        return match ($this) {
            self::Before,
            self::After,
            self::Between => true,
            default => false,
        };
    }

    public function isComparisonOperator(): bool
    {
        return match ($this) {
            self::Equals,
            self::NotEquals,
            self::GreaterThan,
            self::GreaterThanOrEquals,
            self::LessThan,
            self::LessThanOrEquals => true,
            default => false,
        };
    }

    /**
     * Evaluate the condition against given values.
     */
    public function evaluate(mixed $attributeValue, mixed $conditionValue = null): bool
    {
        return match ($this) {
            self::Equals => $attributeValue === $conditionValue,
            self::NotEquals => $attributeValue !== $conditionValue,
            self::GreaterThan => $attributeValue > $conditionValue,
            self::GreaterThanOrEquals => $attributeValue >= $conditionValue,
            self::LessThan => $attributeValue < $conditionValue,
            self::LessThanOrEquals => $attributeValue <= $conditionValue,
            self::Contains => is_string($attributeValue) && str_contains($attributeValue, (string) $conditionValue),
            self::NotContains => is_string($attributeValue) && ! str_contains($attributeValue, (string) $conditionValue),
            self::StartsWith => is_string($attributeValue) && str_starts_with($attributeValue, (string) $conditionValue),
            self::EndsWith => is_string($attributeValue) && str_ends_with($attributeValue, (string) $conditionValue),
            self::Matches => is_string($attributeValue) && preg_match((string) $conditionValue, $attributeValue) === 1,
            self::In => is_array($conditionValue) && in_array($attributeValue, $conditionValue, true),
            self::NotIn => is_array($conditionValue) && ! in_array($attributeValue, $conditionValue, true),
            self::ContainsAny => is_array($attributeValue) && is_array($conditionValue) && ! empty(array_intersect($attributeValue, $conditionValue)),
            self::ContainsAll => is_array($attributeValue) && is_array($conditionValue) && empty(array_diff($conditionValue, $attributeValue)),
            self::IsNull => $attributeValue === null,
            self::IsNotNull => $attributeValue !== null,
            self::IsTrue => $attributeValue === true,
            self::IsFalse => $attributeValue === false,
            self::Before => $attributeValue < $conditionValue,
            self::After => $attributeValue > $conditionValue,
            self::Between => is_array($conditionValue) && count($conditionValue) === 2 && $attributeValue >= $conditionValue[0] && $attributeValue <= $conditionValue[1],
        };
    }
}

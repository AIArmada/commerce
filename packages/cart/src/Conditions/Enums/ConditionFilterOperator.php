<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Enums;

use InvalidArgumentException;

enum ConditionFilterOperator: string
{
    case EQ = '=';
    case NEQ = '!=';
    case GT = '>';
    case GTE = '>=';
    case LT = '<';
    case LTE = '<=';
    case IN = 'in';
    case NOT_IN = 'not-in';
    case CONTAINS = '~';
    case NOT_CONTAINS = '!~';
    case STARTS_WITH = 'starts_with';
    case ENDS_WITH = 'ends_with';

    public static function fromString(string $operator): self
    {
        $normalized = mb_strtolower(mb_trim($operator));
        $normalized = str_replace([' ', '__'], '-', $normalized);
        $normalized = str_replace('_', '-', $normalized);

        return match ($normalized) {
            '=', 'eq' => self::EQ,
            '!=' , '<>' , 'neq' => self::NEQ,
            '>' => self::GT,
            '>=' => self::GTE,
            '<' => self::LT,
            '<=' => self::LTE,
            'in' => self::IN,
            'not-in' => self::NOT_IN,
            '~', 'contains' => self::CONTAINS,
            '!~', 'not-contains' => self::NOT_CONTAINS,
            'starts-with', 'starts_with' => self::STARTS_WITH,
            'ends-with', 'ends_with' => self::ENDS_WITH,
            default => throw new InvalidArgumentException("Unknown operator [{$operator}]"),
        };
    }

    public function toDslToken(): string
    {
        return match ($this) {
            self::EQ => '=',
            self::NEQ => '!=',
            self::GT => '>',
            self::GTE => '>=',
            self::LT => '<',
            self::LTE => '<=',
            self::IN => 'in',
            self::NOT_IN => 'not-in',
            self::CONTAINS => '~',
            self::NOT_CONTAINS => '!~',
            self::STARTS_WITH => 'starts_with',
            self::ENDS_WITH => 'ends_with',
        };
    }

    public function requiresArrayValue(): bool
    {
        return in_array($this, [self::IN, self::NOT_IN], true);
    }
}

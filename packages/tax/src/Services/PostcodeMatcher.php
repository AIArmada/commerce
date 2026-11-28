<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services;

final class PostcodeMatcher
{
    public static function matches(string $postcode, string $pattern): bool
    {
        if ($postcode === $pattern) {
            return true;
        }

        if (str_contains($pattern, '*')) {
            $quoted = preg_quote($pattern, '/');

            return preg_match('/^' . str_replace('\\*', '.*', $quoted) . '$/D', $postcode) === 1;
        }

        if (! str_contains($pattern, '-')) {
            return false;
        }

        [$start, $end] = array_map(mb_trim(...), explode('-', $pattern, 2));
        $format = '/^([A-Za-z0-9]*[A-Za-z])?([0-9]+)$/D';

        if (preg_match($format, $start, $lower) !== 1
            || preg_match($format, $end, $upper) !== 1
            || preg_match($format, $postcode, $value) !== 1
            || $lower[1] !== $upper[1]
            || $value[1] !== $lower[1]) {
            return false;
        }

        return self::compareDigits($value[2], $lower[2]) >= 0
            && self::compareDigits($value[2], $upper[2]) <= 0;
    }

    private static function compareDigits(string $left, string $right): int
    {
        $left = ltrim($left, '0');
        $right = ltrim($right, '0');

        return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
    }
}

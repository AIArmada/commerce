<?php

declare(strict_types=1);

namespace AIArmada\Chip\Support;

use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use InvalidArgumentException;

/**
 * Shared product tax-percent rule (spec: string, 0–100).
 *
 * Accepts ints, floats, and numeric strings; returns the trimmed
 * as-given form. Payload builders should emit the return value;
 * in-place validators may discard it.
 *
 * Precision mirrors the server (sandbox-proven P26): at most 5
 * digits in total with no more than 2 decimal places
 * (`max_digits` / `max_decimal_places`).
 */
final class TaxPercent
{
    public static function normalize(mixed $value): float | int | string
    {
        if (is_string($value)) {
            $value = mb_trim($value);
        }

        if ((is_float($value) && ! is_finite($value))
            || ! is_numeric($value)
            || $value < 0
            || $value > 100) {
            throw new ChipValidationException('Product tax percent must be a number between 0 and 100.');
        }

        try {
            [$digits, $denominator] = ProductData::decimalParts($value, '');
        } catch (InvalidArgumentException) {
            throw new ChipValidationException('Product tax percent must have at most 5 digits with no more than 2 decimal places.');
        }

        // Denominator is 10^decimals; the numerator keeps as-given
        // digits, matching how max_digits counts them.
        if (mb_strlen(mb_ltrim($digits, '-')) > 5 || mb_strlen($denominator) - 1 > 2) {
            throw new ChipValidationException('Product tax percent must have at most 5 digits with no more than 2 decimal places.');
        }

        return $value;
    }
}

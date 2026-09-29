<?php

declare(strict_types=1);

namespace AIArmada\Chip\Support;

use AIArmada\Chip\Exceptions\ChipValidationException;

/**
 * Shared product tax-percent rule (spec: string, 0–100).
 *
 * Accepts ints, floats, and numeric strings; returns the trimmed
 * as-given form. Payload builders should emit the return value;
 * in-place validators may discard it.
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

        return $value;
    }
}

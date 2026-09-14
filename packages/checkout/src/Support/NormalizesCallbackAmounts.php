<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

/**
 * Normalize untrusted gateway callback values into the strict types the
 * payment pipeline requires: integer minor-unit amounts, ISO currency
 * codes, and non-empty string identifiers.
 */
trait NormalizesCallbackAmounts
{
    private function minorAmount(mixed $amount): ?int
    {
        if (is_int($amount)) {
            return $amount >= 0 ? $amount : null;
        }

        if (! is_string($amount) || ! preg_match('/^\d+$/', mb_trim($amount))) {
            return null;
        }

        $validated = filter_var($amount, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);

        return is_int($validated) ? $validated : null;
    }

    private function currency(mixed $currency): ?string
    {
        if (! is_string($currency)) {
            return null;
        }

        $currency = mb_strtoupper(mb_trim($currency));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null;
    }

    private function callbackString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = mb_trim((string) $value);

        return $value === '' ? null : $value;
    }
}

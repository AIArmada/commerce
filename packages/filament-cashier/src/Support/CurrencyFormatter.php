<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Support;

use AIArmada\CommerceSupport\Support\MoneyFormatter;

/**
 * Shared currency formatting utilities.
 *
 * Centralizes currency symbol mapping and amount formatting to eliminate
 * duplication across DTOs, widgets, and resources.
 */
final class CurrencyFormatter
{
    /**
     * Format amount in cents to a human-readable currency string.
     */
    public static function format(int $amountInCents, string $currency, int $precision = 2): string
    {
        return MoneyFormatter::formatMinor($amountInCents, $currency, $precision);
    }

    /**
     * Format amount in cents with currency code suffix (e.g., "100.00 MYR").
     */
    public static function formatWithCode(int $amountInCents, string $currency, int $precision = 2): string
    {
        return MoneyFormatter::formatMinorWithCode($amountInCents, $currency, $precision);
    }

    /**
     * Get the symbol for a currency code.
     */
    public static function getSymbol(string $currency): string
    {
        return MoneyFormatter::symbol($currency);
    }

    /**
     * Check if a currency uses zero decimal places (e.g., JPY, KRW).
     */
    public static function isZeroDecimal(string $currency): bool
    {
        return self::getPrecision($currency) === 0;
    }

    /**
     * Get precision for a currency.
     */
    public static function getPrecision(string $currency): int
    {
        return MoneyFormatter::precisionFor($currency);
    }

    /**
     * Format with auto-detected precision based on currency.
     */
    public static function formatAuto(int $amountInCents, string $currency): string
    {
        return MoneyFormatter::formatMinor($amountInCents, $currency, self::getPrecision($currency));
    }
}

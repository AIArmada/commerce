<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Support;

/**
 * Shared currency formatting utilities.
 *
 * Centralizes currency symbol mapping and amount formatting to eliminate
 * duplication across DTOs, widgets, and resources.
 */
final class CurrencyFormatter
{
    /**
     * @var array<string, string>
     */
    private const CURRENCY_SYMBOLS = [
        'MYR' => 'RM',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'SGD' => 'S$',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'JPY' => '¥',
        'CNY' => '¥',
        'KRW' => '₩',
        'INR' => '₹',
        'THB' => '฿',
        'IDR' => 'Rp',
        'PHP' => '₱',
        'VND' => '₫',
    ];

    /**
     * Format amount in cents to a human-readable currency string.
     */
    public static function format(int $amountInCents, string $currency, int $precision = 2): string
    {
        $symbol = self::getSymbol($currency);
        $value = $amountInCents / 100;

        return $symbol . number_format($value, $precision, '.', ',');
    }

    /**
     * Format amount in cents with currency code suffix (e.g., "100.00 MYR").
     */
    public static function formatWithCode(int $amountInCents, string $currency, int $precision = 2): string
    {
        $value = $amountInCents / 100;

        return number_format($value, $precision, '.', ',') . ' ' . mb_strtoupper($currency);
    }

    /**
     * Get the symbol for a currency code.
     */
    public static function getSymbol(string $currency): string
    {
        $upper = mb_strtoupper($currency);

        return self::CURRENCY_SYMBOLS[$upper] ?? $upper . ' ';
    }

    /**
     * Check if a currency uses zero decimal places (e.g., JPY, KRW).
     */
    public static function isZeroDecimal(string $currency): bool
    {
        return in_array(mb_strtoupper($currency), [
            'JPY',
            'KRW',
            'VND',
            'CLP',
            'ISK',
            'UGX',
            'RWF',
        ], true);
    }

    /**
     * Get precision for a currency.
     */
    public static function getPrecision(string $currency): int
    {
        return self::isZeroDecimal($currency) ? 0 : 2;
    }

    /**
     * Format with auto-detected precision based on currency.
     */
    public static function formatAuto(int $amountInCents, string $currency): string
    {
        $precision = self::getPrecision($currency);

        // For zero decimal currencies, amount is already in main units
        $divisor = $precision === 0 ? 1 : 100;
        $symbol = self::getSymbol($currency);
        $value = $amountInCents / $divisor;

        return $symbol . number_format($value, $precision, '.', ',');
    }
}

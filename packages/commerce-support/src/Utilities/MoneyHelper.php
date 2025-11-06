<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Utilities;

use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use InvalidArgumentException;

/**
 * Money utility helpers for commerce packages.
 *
 * Provides convenient methods for creating, manipulating, and formatting
 * Money objects with consistent currency handling across all packages.
 */
class MoneyHelper
{
    /**
     * Create a Money instance from various input types.
     *
     * @param  float|string|int  $amount  The amount (can be "100.50", 100.50, or 10050 cents)
     * @param  string|null  $currency  Currency code (defaults to config)
     */
    public static function make(float|string|int $amount, ?string $currency = null): Money
    {
        $currency = $currency ?? self::getDefaultCurrency();

        // Sanitize the amount if it's a string
        if (is_string($amount)) {
            $amount = self::sanitizePrice($amount);
        }

        return Money::{$currency}($amount);
    }

    /**
     * Sanitize a price value from various formats.
     *
     * Handles:
     * - Strings with currency symbols: "RM 100.50", "$100.50"
     * - Strings with spaces/commas: "1,000.50", "1 000.50"
     * - Leading plus signs: "+100.50"
     * - Negative values: "-100.50"
     *
     * @param  mixed  $value  The value to sanitize
     * @return float The sanitized numeric value
     */
    public static function sanitizePrice(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return 0.0;
        }

        // Remove currency symbols, spaces, and commas
        $cleaned = preg_replace('/[^\d.+-]/', '', $value);

        // Remove leading plus sign (optional positive indicator)
        $cleaned = mb_ltrim($cleaned ?? '', '+');

        return (float) ($cleaned ?: 0.0);
    }

    /**
     * Format Money object for display.
     *
     * @param  Money  $money  The Money instance
     * @param  bool  $includeSymbol  Whether to include currency symbol
     * @param  bool  $includeCode  Whether to include currency code
     */
    public static function formatForDisplay(
        Money $money,
        bool $includeSymbol = true,
        bool $includeCode = false
    ): string {
        $formatted = $money->format();

        if (! $includeSymbol) {
            // Remove currency symbol but keep the amount
            $formatted = preg_replace('/[^\d.,\s-]/', '', $formatted);
            $formatted = mb_trim($formatted ?? '');
        }

        if ($includeCode && ! str_contains($formatted, $money->getCurrency()->getCurrency())) {
            $formatted .= ' '.$money->getCurrency()->getCurrency();
        }

        return $formatted;
    }

    /**
     * Create Money from cents/smallest currency unit.
     *
     * @param  int  $cents  Amount in cents (e.g., 10050 for $100.50)
     * @param  string|null  $currency  Currency code
     */
    public static function fromCents(int $cents, ?string $currency = null): Money
    {
        $currency = $currency ?? self::getDefaultCurrency();
        $amount = $cents / 100; // Convert cents to currency units

        return Money::{$currency}($amount);
    }

    /**
     * Convert Money to cents/smallest currency unit.
     *
     * @param  Money  $money  The Money instance
     * @return int Amount in cents
     */
    public static function toCents(Money $money): int
    {
        return (int) ($money->getAmount() * 100);
    }

    /**
     * Parse amount from user input string.
     *
     * Handles formats like:
     * - "RM 100.50"
     * - "$100.50"
     * - "100.50"
     * - "100"
     *
     * @param  string  $input  User input string
     * @return float Parsed amount
     */
    public static function parseAmount(string $input): float
    {
        return self::sanitizePrice($input);
    }

    /**
     * Get the default currency from configuration.
     *
     * @return string Currency code (e.g., 'MYR', 'USD')
     */
    public static function getDefaultCurrency(): string
    {
        // Try cart config first
        if (config()->has('cart.money.default_currency')) {
            return config('cart.money.default_currency');
        }

        // Fallback to app config
        if (config()->has('app.currency')) {
            return config('app.currency');
        }

        // Ultimate fallback
        return 'MYR';
    }

    /**
     * Validate if a currency code is supported.
     *
     * @param  string  $currency  Currency code to validate
     * @return bool Whether the currency is valid
     */
    public static function validateCurrency(string $currency): bool
    {
        try {
            new Currency($currency);

            return true;
        } catch (InvalidArgumentException) { // @phpstan-ignore catch.neverThrown
            return false;
        }
    }

    /**
     * Get currency symbol for a given currency code.
     *
     * @param  string|null  $currency  Currency code (defaults to config)
     */
    public static function getCurrencySymbol(?string $currency = null): string
    {
        $currency = $currency ?? self::getDefaultCurrency();

        try {
            $currencyObj = new Currency($currency);

            return $currencyObj->getSymbol();
        } catch (InvalidArgumentException) {
            return $currency; // Fallback to code if symbol not found
        }
    }

    /**
     * Create a zero Money instance.
     *
     * @param  string|null  $currency  Currency code
     */
    public static function zero(?string $currency = null): Money
    {
        return self::make(0, $currency);
    }

    /**
     * Check if two Money instances are equal.
     */
    public static function equals(Money $money1, Money $money2): bool
    {
        return $money1->equals($money2);
    }

    /**
     * Add multiple Money instances.
     *
     * @param  Money  ...$amounts  Variable number of Money instances
     *
     * @throws InvalidArgumentException If no amounts provided
     */
    public static function sum(Money ...$amounts): Money
    {
        if (empty($amounts)) {
            throw new InvalidArgumentException('At least one Money instance is required');
        }

        $sum = array_shift($amounts);

        foreach ($amounts as $amount) {
            $sum = $sum->add($amount);
        }

        return $sum;
    }

    /**
     * Calculate percentage of a Money amount.
     *
     * @param  Money  $money  The base amount
     * @param  float  $percentage  Percentage (e.g., 10 for 10%)
     * @param  bool  $add  Whether to add to original amount (true) or return percentage only (false)
     */
    public static function percentage(Money $money, float $percentage, bool $add = false): Money
    {
        $percentageAmount = $money->multiply($percentage / 100);

        return $add ? $money->add($percentageAmount) : $percentageAmount;
    }

    /**
     * Convert Money to different currency.
     *
     * Note: This does NOT perform exchange rate conversion.
     * It only changes the currency code while keeping the same numeric value.
     * For actual currency conversion, use a dedicated exchange rate service.
     *
     * @param  Money  $money  The Money instance
     * @param  string  $toCurrency  Target currency code
     */
    public static function convertCurrency(Money $money, string $toCurrency): Money
    {
        return self::make($money->getAmount(), $toCurrency);
    }
}

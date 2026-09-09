<?php

declare(strict_types=1);

namespace AIArmada\Cart\Support;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Cart-specific money presentation and decimal-to-minor-unit conversion.
 *
 * Cart values are always stored and calculated as integer minor units. This
 * class is the single boundary for cart currency defaults, formatting, and
 * conversion of human-entered decimal values.
 */
final class CartMoney
{
    public static function currency(?string $currency = null): string
    {
        $resolved = mb_strtoupper(mb_trim($currency ?? ''));

        if ($resolved === '') {
            $resolved = mb_strtoupper(mb_trim((string) config('cart.money.default_currency', 'MYR')));
        }

        if ($resolved === '') {
            throw new InvalidArgumentException('A cart currency must be configured.');
        }

        return $resolved;
    }

    public static function formatMinor(int $amountInMinorUnits, ?string $currency = null): string
    {
        return MoneyFormatter::formatMinor($amountInMinorUnits, self::currency($currency));
    }

    public static function decimalFromMinor(int $amountInMinorUnits, ?string $currency = null): string
    {
        return MoneyFormatter::decimalFromMinor($amountInMinorUnits, self::currency($currency));
    }

    public static function majorFromMinor(int $amountInMinorUnits, ?string $currency = null): float
    {
        return (float) self::decimalFromMinor($amountInMinorUnits, $currency);
    }

    public static function minorFromDecimal(string $value, ?string $currency = null): int
    {
        $currency = self::currency($currency);
        $scale = self::minorScale($currency);
        $roundingMode = self::roundingMode();

        return BigDecimal::of($value)
            ->multipliedBy($scale)
            ->toScale(0, $roundingMode)
            ->toInt();
    }

    public static function minorScale(?string $currency = null): int
    {
        return 10 ** MoneyFormatter::precisionFor(self::currency($currency));
    }

    private static function roundingMode(): RoundingMode
    {
        return match (config('cart.money.rounding_mode', 'half_up')) {
            'half_even' => RoundingMode::HalfEven,
            'floor' => RoundingMode::Floor,
            'ceil' => RoundingMode::Ceiling,
            'half_up' => RoundingMode::HalfUp,
            default => throw new InvalidArgumentException('Unsupported cart money rounding mode.'),
        };
    }
}

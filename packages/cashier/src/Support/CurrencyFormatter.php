<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

use AIArmada\CommerceSupport\Support\MoneyFormatter;

final class CurrencyFormatter
{
    public static function format(int $amountInCents, string $currency, int $precision = 2): string
    {
        return MoneyFormatter::formatMinor($amountInCents, $currency, $precision);
    }

    public static function formatWithCode(int $amountInCents, string $currency, int $precision = 2): string
    {
        return MoneyFormatter::formatMinorWithCode($amountInCents, $currency, $precision);
    }

    public static function getSymbol(string $currency): string
    {
        return MoneyFormatter::symbol($currency);
    }

    public static function isZeroDecimal(string $currency): bool
    {
        return self::getPrecision($currency) === 0;
    }

    public static function getPrecision(string $currency): int
    {
        return MoneyFormatter::precisionFor($currency);
    }

    public static function formatAuto(int $amountInCents, string $currency): string
    {
        return MoneyFormatter::formatMinor($amountInCents, $currency, self::getPrecision($currency));
    }
}

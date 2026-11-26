<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Resources\AffiliateResource\Schemas;

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Illuminate\Validation\ValidationException;

final class CommissionRateInput
{
    public static function format(?int $rate, string $type, string $currency): ?string
    {
        if ($rate === null) {
            return null;
        }

        return $type === CommissionType::Percentage->value
            ? number_format($rate / 100, 2, '.', '')
            : str_replace(',', '', MoneyFormatter::decimalFromMinor($rate, $currency));
    }

    public static function parse(?string $rate, string $type, string $currency): ?int
    {
        if ($rate === null || $rate === '') {
            return null;
        }

        $precision = $type === CommissionType::Percentage->value ? 2 : MoneyFormatter::precisionFor($currency);

        if (preg_match('/^([0-9]*)(?:\.([0-9]+))?$/D', $rate, $matches) !== 1) {
            throw self::invalidRate();
        }

        $fraction = $matches[2] ?? '';

        if (mb_strlen($fraction) > $precision) {
            throw self::invalidRate();
        }

        $digits = mb_ltrim($matches[1] . mb_str_pad($fraction, $precision, '0'), '0');
        $minor = filter_var($digits === '' ? '0' : $digits, FILTER_VALIDATE_INT);

        if ($minor === false) {
            throw self::invalidRate();
        }

        return $minor;
    }

    private static function invalidRate(): ValidationException
    {
        return ValidationException::withMessages(['data.commission_rate' => 'Enter a non-negative commission rate with the supported decimal precision.']);
    }
}

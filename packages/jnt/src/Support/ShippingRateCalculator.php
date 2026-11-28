<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Support;

use InvalidArgumentException;

final class ShippingRateCalculator
{
    /**
     * @return array{amount: int, estimated_days: int, weight_kg: int}
     */
    public static function quote(int $weightGrams, string $postcode, ?string $state = null): array
    {
        return [
            'amount' => self::calculateRate($weightGrams, $postcode, $state),
            'estimated_days' => self::estimatedDays($postcode, $state),
            'weight_kg' => self::billableWeightKg($weightGrams),
        ];
    }

    public static function calculateRate(int $weightGrams, string $postcode, ?string $state = null): int
    {
        $weightKg = self::billableWeightKg($weightGrams);
        $baseRate = self::configInt('jnt.shipping.base_rate', 800);
        $perKgRate = self::configInt('jnt.shipping.per_kg_rate', 200);
        $minCharge = self::configInt('jnt.shipping.min_charge', 800);
        $basisPoints = self::regionMultiplierBasisPoints($postcode, $state);

        if (min($baseRate, $perKgRate, $minCharge, $basisPoints) < 0
            || ($perKgRate > 0 && $weightKg - 1 > intdiv(PHP_INT_MAX - $baseRate, $perKgRate))) {
            throw new InvalidArgumentException('Invalid or overflowing shipping rate configuration.');
        }

        $rate = $baseRate + ($perKgRate * ($weightKg - 1));

        if ($basisPoints > 0 && $rate > intdiv(PHP_INT_MAX - 5000, $basisPoints)) {
            throw new InvalidArgumentException('Regional shipping rate exceeds the supported range.');
        }

        $rate = intdiv(($rate * $basisPoints) + 5000, 10000);

        return max($rate, $minCharge);
    }

    public static function billableWeightKg(int $weightGrams): int
    {
        if ($weightGrams < 1) {
            throw new InvalidArgumentException('Shipping weight must be positive.');
        }

        return intdiv($weightGrams - 1, 1000) + 1;
    }

    public static function estimatedDays(string $postcode, ?string $state = null): int
    {
        $defaultDays = self::configInt('jnt.shipping.default_estimated_days', 3);
        $extraDays = self::configInt('jnt.shipping.east_malaysia_extra_days', 2);

        if ($defaultDays < 0 || $extraDays < 0) {
            throw new InvalidArgumentException('Invalid shipping delivery configuration.');
        }

        return self::region($postcode, $state) === null ? $defaultDays : $defaultDays + $extraDays;
    }

    public static function regionMultiplierBasisPoints(string $postcode, ?string $state = null): int
    {
        $region = self::region($postcode, $state);

        if ($region === null) {
            return 10000;
        }

        $multipliers = config('jnt.shipping.region_multipliers_bp', []);

        if (! is_array($multipliers)) {
            return 10000;
        }

        $basisPoints = (int) ($multipliers[$region] ?? 10000);

        if ($basisPoints < 0) {
            throw new InvalidArgumentException('Invalid regional shipping multiplier configuration.');
        }

        return $basisPoints;
    }

    public static function region(string $postcode, ?string $state = null): ?string
    {
        $postcode = self::postcode($postcode);

        if ($postcode !== null) {
            $numeric = (int) $postcode;

            return match (true) {
                $numeric >= 87000 && $numeric <= 87999 => 'labuan',
                $numeric >= 88000 && $numeric <= 91999 => 'sabah',
                $numeric >= 93000 && $numeric <= 98999 => 'sarawak',
                default => null,
            };
        }

        $state = mb_strtolower(mb_trim((string) ($state ?? '')));

        return match (true) {
            $state === '' => null,
            str_contains($state, 'labuan') => 'labuan',
            str_contains($state, 'sabah') => 'sabah',
            str_contains($state, 'sarawak') => 'sarawak',
            default => null,
        };
    }

    private static function postcode(string $postcode): ?string
    {
        $postcode = mb_trim($postcode);

        return preg_match('/^\d{5}$/', $postcode) === 1 ? $postcode : null;
    }

    private static function configInt(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}

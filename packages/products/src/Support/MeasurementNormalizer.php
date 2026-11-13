<?php

declare(strict_types=1);

namespace AIArmada\Products\Support;

use InvalidArgumentException;

final class MeasurementNormalizer
{
    private const array WEIGHT_TO_GRAMS = [
        'g' => 1.0,
        'gram' => 1.0,
        'grams' => 1.0,
        'kg' => 1000.0,
        'kilogram' => 1000.0,
        'kilograms' => 1000.0,
        'lb' => 453.59237,
        'lbs' => 453.59237,
        'pound' => 453.59237,
        'pounds' => 453.59237,
        'oz' => 28.349523125,
        'ounce' => 28.349523125,
        'ounces' => 28.349523125,
    ];

    private const array DIMENSION_TO_CENTIMETRES = [
        'mm' => 0.1,
        'millimetre' => 0.1,
        'millimetres' => 0.1,
        'millimeter' => 0.1,
        'millimeters' => 0.1,
        'cm' => 1.0,
        'centimetre' => 1.0,
        'centimetres' => 1.0,
        'centimeter' => 1.0,
        'centimeters' => 1.0,
        'm' => 100.0,
        'metre' => 100.0,
        'metres' => 100.0,
        'meter' => 100.0,
        'meters' => 100.0,
        'in' => 2.54,
        'inch' => 2.54,
        'inches' => 2.54,
        'ft' => 30.48,
        'foot' => 30.48,
        'feet' => 30.48,
    ];

    public static function weightToGrams(float | int | string | null $weight, ?string $unit): ?int
    {
        if ($weight === null || $weight === '') {
            return null;
        }

        $normalizedUnit = self::normalizeUnit($unit, 'kg');
        $factor = self::WEIGHT_TO_GRAMS[$normalizedUnit] ?? null;

        if ($factor === null) {
            throw new InvalidArgumentException(sprintf('Unsupported weight unit [%s].', $unit));
        }

        return (int) round((float) $weight * $factor);
    }

    public static function dimensionToCentimetres(float | int | string | null $dimension, ?string $unit): ?float
    {
        if ($dimension === null || $dimension === '') {
            return null;
        }

        $normalizedUnit = self::normalizeUnit($unit, 'cm');
        $factor = self::DIMENSION_TO_CENTIMETRES[$normalizedUnit] ?? null;

        if ($factor === null) {
            throw new InvalidArgumentException(sprintf('Unsupported dimension unit [%s].', $unit));
        }

        return round((float) $dimension * $factor, 2);
    }

    private static function normalizeUnit(?string $unit, string $default): string
    {
        $normalized = mb_strtolower(mb_trim((string) $unit));

        return $normalized === '' ? $default : $normalized;
    }
}

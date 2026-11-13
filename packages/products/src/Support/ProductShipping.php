<?php

declare(strict_types=1);

namespace AIArmada\Products\Support;

/**
 * Normalizes catalog measurements to the shipping boundary units.
 */
final class ProductShipping
{
    public static function weightGrams(float | int | string | null $weight, ?string $unit): ?int
    {
        $grams = MeasurementNormalizer::weightToGrams($weight, $unit);

        return $grams !== null && $grams > 0 ? $grams : null;
    }

    /**
     * @return array{length: int, width: int, height: int}|null
     */
    public static function dimensionsCm(
        float | int | string | null $length,
        float | int | string | null $width,
        float | int | string | null $height,
        ?string $unit,
    ): ?array {
        $lengthCm = MeasurementNormalizer::dimensionToCentimetres($length, $unit);
        $widthCm = MeasurementNormalizer::dimensionToCentimetres($width, $unit);
        $heightCm = MeasurementNormalizer::dimensionToCentimetres($height, $unit);

        if ($lengthCm === null || $widthCm === null || $heightCm === null) {
            return null;
        }

        $dimensions = [
            'length' => (int) round($lengthCm),
            'width' => (int) round($widthCm),
            'height' => (int) round($heightCm),
        ];

        if ($dimensions['length'] <= 0 || $dimensions['width'] <= 0 || $dimensions['height'] <= 0) {
            return null;
        }

        return $dimensions;
    }
}

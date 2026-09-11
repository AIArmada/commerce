<?php

declare(strict_types=1);

namespace AIArmada\Products\Support;

use Akaunting\Money\Money;

final class ProductPricing
{
    public static function formatMinorAmount(int $amount, string $currency): string
    {
        return Money::$currency($amount, false)->format();
    }

    public static function discountPercentage(int $price, ?int $comparePrice): ?float
    {
        if ($comparePrice === null || $comparePrice <= $price || $comparePrice === 0) {
            return null;
        }

        return round((($comparePrice - $price) / $comparePrice) * 100, 1);
    }
}

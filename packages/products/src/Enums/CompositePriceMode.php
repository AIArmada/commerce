<?php

declare(strict_types=1);

namespace AIArmada\Products\Enums;

enum CompositePriceMode: string
{
    case Fixed = 'fixed';
    case Sum = 'sum';
    case PercentageDiscount = 'percentage_discount';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('products::enums.composite_price_mode.fixed'),
            self::Sum => __('products::enums.composite_price_mode.sum'),
            self::PercentageDiscount => __('products::enums.composite_price_mode.percentage_discount'),
        };
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Products\Enums;

enum CompositePriceMode: string
{
    case Fixed = 'fixed';
    case Sum = 'sum';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('products::enums.composite_price_mode.fixed'),
            self::Sum => __('products::enums.composite_price_mode.sum'),
        };
    }
}

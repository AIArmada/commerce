<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Enums;

enum PromotionType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case BuyXGetY = 'buy_x_get_y';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage Discount',
            self::Fixed => 'Fixed Amount Discount',
            self::BuyXGetY => 'Buy X Get Y Free',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Percentage => 'heroicon-o-receipt-percent',
            self::Fixed => 'heroicon-o-currency-dollar',
            self::BuyXGetY => 'heroicon-o-gift',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Percentage => 'success',
            self::Fixed => 'warning',
            self::BuyXGetY => 'info',
        };
    }

    /**
     * Format the discount value for display.
     */
    public function formatValue(int $value): string
    {
        return match ($this) {
            self::Percentage => "{$value}%",
            self::Fixed => 'RM ' . number_format($value / 100, 2),
            self::BuyXGetY => 'Buy & Get',
        };
    }
}

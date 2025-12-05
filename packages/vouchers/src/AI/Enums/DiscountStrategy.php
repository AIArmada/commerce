<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI\Enums;

/**
 * Represents discount optimization strategies.
 */
enum DiscountStrategy: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case FreeShipping = 'free_shipping';
    case BuyXGetY = 'buy_x_get_y';
    case Tiered = 'tiered';

    /**
     * Get a human-readable label.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage Discount',
            self::FixedAmount => 'Fixed Amount Off',
            self::FreeShipping => 'Free Shipping',
            self::BuyXGetY => 'Buy X Get Y',
            self::Tiered => 'Tiered Discount',
        };
    }

    /**
     * Get the description of this strategy.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::Percentage => 'A percentage off the cart total',
            self::FixedAmount => 'A fixed amount off the cart total',
            self::FreeShipping => 'Free shipping on the order',
            self::BuyXGetY => 'Buy certain products, get others free or discounted',
            self::Tiered => 'Discount increases with cart value',
        };
    }

    /**
     * Check if this strategy is suitable for high-value carts.
     */
    public function isSuitableForHighValueCart(): bool
    {
        return match ($this) {
            self::FixedAmount, self::FreeShipping => true,
            self::Percentage, self::BuyXGetY, self::Tiered => false,
        };
    }

    /**
     * Check if this strategy is suitable for low-value carts.
     */
    public function isSuitableForLowValueCart(): bool
    {
        return match ($this) {
            self::Percentage, self::FreeShipping => true,
            self::FixedAmount, self::BuyXGetY, self::Tiered => false,
        };
    }

    /**
     * Get the psychological appeal score (1-5).
     */
    public function getPsychologicalAppeal(): int
    {
        return match ($this) {
            self::Percentage => 4,
            self::FixedAmount => 3,
            self::FreeShipping => 5,
            self::BuyXGetY => 4,
            self::Tiered => 3,
        };
    }

    /**
     * Get margin protection score (1-5, higher = better for margins).
     */
    public function getMarginProtection(): int
    {
        return match ($this) {
            self::Percentage => 2,
            self::FixedAmount => 4,
            self::FreeShipping => 3,
            self::BuyXGetY => 5,
            self::Tiered => 3,
        };
    }
}

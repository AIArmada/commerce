<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Enums;

use InvalidArgumentException;

enum ConditionPhase: string
{
    case PRE_ITEM = 'pre_item';
    case ITEM_DISCOUNT = 'item_discount';
    case ITEM_POST = 'item_post';
    case CART_SUBTOTAL = 'cart_subtotal';
    case SHIPPING = 'shipping';
    case TAXABLE = 'taxable';
    case TAX = 'tax';
    case PAYMENT = 'payment';
    case GRAND_TOTAL = 'grand_total';
    case CUSTOM = 'custom';

    public static function fromString(string $phase): self
    {
        $normalized = mb_strtolower(mb_trim($phase));

        foreach (self::cases() as $case) {
            if ($case->value === $normalized) {
                return $case;
            }
        }

        throw new InvalidArgumentException("Unknown condition phase [{$phase}].");
    }

    public function order(): int
    {
        return match ($this) {
            self::PRE_ITEM => 10,
            self::ITEM_DISCOUNT => 20,
            self::ITEM_POST => 30,
            self::CART_SUBTOTAL => 40,
            self::SHIPPING => 50,
            self::TAXABLE => 60,
            self::TAX => 70,
            self::PAYMENT => 80,
            self::GRAND_TOTAL => 90,
            self::CUSTOM => 100,
        };
    }
}

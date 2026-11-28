<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation\Support;

use InvalidArgumentException;
use OverflowException;

final class CostArithmetic
{
    public static function multiply(int $quantity, int $unitCost): int
    {
        if ($quantity < 0 || $unitCost < 0) {
            throw new InvalidArgumentException('Valuation quantities and costs must be non-negative.');
        }

        if ($unitCost !== 0 && $quantity > intdiv(PHP_INT_MAX, $unitCost)) {
            throw new OverflowException('Inventory value exceeds the supported integer range.');
        }

        return $quantity * $unitCost;
    }

    public static function add(int $left, int $right): int
    {
        if ($left < 0 || $right < 0) {
            throw new InvalidArgumentException('Valuation totals must be non-negative.');
        }

        if ($left > PHP_INT_MAX - $right) {
            throw new OverflowException('Inventory total exceeds the supported integer range.');
        }

        return $left + $right;
    }

    public static function average(int $value, int $quantity): int
    {
        if ($value < 0 || $quantity < 0) {
            throw new InvalidArgumentException('Valuation quantities and costs must be non-negative.');
        }

        if ($quantity === 0) {
            return 0;
        }

        $quotient = intdiv($value, $quantity);
        $remainder = $value % $quantity;

        return $quotient + (int) ($remainder >= $quantity - $remainder);
    }
}

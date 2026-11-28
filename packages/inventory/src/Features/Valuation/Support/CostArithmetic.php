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

    public static function fromDatabase(mixed $value): int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }

        if (! is_string($value) || preg_match('/^[0-9]+$/D', $value) !== 1) {
            throw new OverflowException('Inventory aggregate is not a supported non-negative integer.');
        }

        $digits = mb_ltrim($value, '0');
        $maximum = (string) PHP_INT_MAX;

        if (mb_strlen($digits) > mb_strlen($maximum)
            || (mb_strlen($digits) === mb_strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            throw new OverflowException('Inventory aggregate exceeds the supported integer range.');
        }

        return (int) $value;
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

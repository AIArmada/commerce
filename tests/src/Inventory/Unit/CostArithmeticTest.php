<?php

declare(strict_types=1);

use AIArmada\Inventory\Features\Valuation\Support\CostArithmetic;

it('rounds non-negative minor-unit averages without floating point conversion', function (): void {
    expect(CostArithmetic::average(PHP_INT_MAX, 1))->toBe(PHP_INT_MAX)
        ->and(CostArithmetic::average(PHP_INT_MAX, 2))->toBe(intdiv(PHP_INT_MAX, 2) + 1)
        ->and(CostArithmetic::average(5, 2))->toBe(3)
        ->and(CostArithmetic::average(4, 3))->toBe(1)
        ->and(CostArithmetic::average(0, 0))->toBe(0);
});

it('rejects overflowing inventory arithmetic before conversion to float', function (): void {
    expect(fn () => CostArithmetic::multiply(PHP_INT_MAX, 2))->toThrow(OverflowException::class)
        ->and(fn () => CostArithmetic::add(PHP_INT_MAX, 1))->toThrow(OverflowException::class)
        ->and(fn () => CostArithmetic::multiply(-1, 1))->toThrow(InvalidArgumentException::class)
        ->and(CostArithmetic::multiply(PHP_INT_MAX, 1))->toBe(PHP_INT_MAX);
});

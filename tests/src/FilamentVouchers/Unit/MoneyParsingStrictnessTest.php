<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Support\MoneyHelper;

uses(TestCase::class);

it('rejects unparseable money input instead of coercing it to zero', function (): void {
    expect(MoneyHelper::displayToCents('not-money'))->toBeNull()
        ->and(MoneyHelper::displayToCents('12.34.56'))->toBeNull()
        ->and(MoneyHelper::displayToCents('$10'))->toBeNull()
        ->and(MoneyHelper::displayToBasisPoints('bogus'))->toBeNull();
});

it('keeps parsing valid money input', function (): void {
    expect(MoneyHelper::displayToCents('10.00'))->toBe(1000)
        ->and(MoneyHelper::displayToCents('0.01'))->toBe(1)
        ->and(MoneyHelper::displayToCents('-5.25'))->toBe(-525)
        ->and(MoneyHelper::displayToBasisPoints('10.50'))->toBe(1050)
        ->and(MoneyHelper::displayToCents(null))->toBeNull()
        ->and(MoneyHelper::displayToCents(''))->toBeNull();
});

<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\MoneyFormatter;

describe('MoneyFormatter::majorToMinor', function (): void {
    it('converts decimal strings without floating point drift', function (): void {
        expect(MoneyFormatter::majorToMinor('100.01', 'MYR'))->toBe(10001)
            ->and(MoneyFormatter::majorToMinor('0.29', 'MYR'))->toBe(29)
            ->and(MoneyFormatter::majorToMinor('1,234.50', 'MYR'))->toBe(123450);
    });

    it('uses the currency precision', function (): void {
        expect(MoneyFormatter::majorToMinor('1234', 'JPY'))->toBe(1234)
            ->and(MoneyFormatter::majorToMinor('1.234', 'KWD'))->toBe(1234);
    });

    it('rounds excess decimal places deterministically', function (): void {
        expect(MoneyFormatter::majorToMinor('19.995', 'MYR'))->toBe(2000)
            ->and(MoneyFormatter::majorToMinor('19.994', 'MYR'))->toBe(1999);
    });
});

it('formats money with a deterministic decimal separator and currency precision', function (): void {
    expect(MoneyFormatter::formatMinor(1234, 'EUR'))->toBe('€12.34')
        ->and(MoneyFormatter::formatMinor(1234, 'JPY'))->toBe('¥1,234')
        ->and(MoneyFormatter::formatMinor(-1234, 'USD'))->toBe('-$12.34');
});

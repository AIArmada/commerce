<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\MoneyFormatter;

describe('MoneyFormatter minor-unit APIs', function (): void {
    it('formats golden minor-unit values using currency precision', function (): void {
        expect(MoneyFormatter::formatMinor(1234, 'EUR'))->toBe('€12.34')
            ->and(MoneyFormatter::formatMinorWithCode(1234, 'USD'))->toBe('12.34 USD')
            ->and(MoneyFormatter::decimalFromMinor(1234, 'KWD'))->toBe('1.234')
            ->and(MoneyFormatter::formatMinor(1234, 'JPY'))->toBe('¥1,234')
            ->and(MoneyFormatter::formatMinor(-1234, 'USD'))->toBe('-$12.34');
    });

    it('accepts only integer minor units', function (): void {
        expect(fn (): string => MoneyFormatter::formatMinor(12.5, 'USD'))
            ->toThrow(TypeError::class);
        expect(fn (): string => MoneyFormatter::formatMinorWithCode('12', 'USD'))
            ->toThrow(TypeError::class);
        expect(fn (): string => MoneyFormatter::decimalFromMinor('12', 'USD'))
            ->toThrow(TypeError::class);
    });
});

describe('MoneyFormatter major-unit APIs', function (): void {
    it('formats integer major-unit values without decimal coercion', function (): void {
        expect(MoneyFormatter::formatMajor(19, 'MYR'))->toBe('RM19.00')
            ->and(MoneyFormatter::formatMajorWithCode(19, 'MYR'))->toBe('19.00 MYR')
            ->and(MoneyFormatter::decimalFromMajor(19, 'USD'))->toBe('19.00')
            ->and(MoneyFormatter::majorToMinor(19, 'MYR'))->toBe(1900)
            ->and(MoneyFormatter::majorToMinor(19, 'JPY'))->toBe(19)
            ->and(MoneyFormatter::formatMajor(19, 'KWD'))->toBe('د.ك19.000');
    });

    it('rejects decimal major-unit input instead of rounding implicitly', function (): void {
        expect(fn (): string => MoneyFormatter::formatMajor(19.99, 'USD'))
            ->toThrow(TypeError::class);
        expect(fn (): int => MoneyFormatter::majorToMinor('19', 'USD'))
            ->toThrow(TypeError::class);
        expect(fn (): string => MoneyFormatter::decimalFromMajor(19.99, 'USD'))
            ->toThrow(TypeError::class);
    });
});

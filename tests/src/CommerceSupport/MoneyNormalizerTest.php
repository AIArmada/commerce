<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\MoneyNormalizer;

describe('MoneyNormalizer::toCents', function (): void {
    it('returns integer minor units unchanged', function (): void {
        expect(MoneyNormalizer::toCents(1999))->toBe(1999)
            ->and(MoneyNormalizer::toCents(0))->toBe(0)
            ->and(MoneyNormalizer::toCents(-100))->toBe(-100);
    });

    it('rejects values that require a major-to-minor conversion', function (): void {
        expect(fn (): int => MoneyNormalizer::toCents(19.99))
            ->toThrow(TypeError::class);
        expect(fn (): int => MoneyNormalizer::toCents('1999'))
            ->toThrow(TypeError::class);
        expect(fn (): int => MoneyNormalizer::toCents(null))
            ->toThrow(TypeError::class);
    });
});

describe('MoneyNormalizer::toDollars', function (): void {
    it('converts cents to dollars', function (): void {
        expect(MoneyNormalizer::toDollars(1999))->toBe(19.99)
            ->and(MoneyNormalizer::toDollars(100))->toBe(1.0)
            ->and(MoneyNormalizer::toDollars(0))->toBe(0.0);
    });
});

describe('MoneyNormalizer::format', function (): void {
    it('formats cents as currency string', function (): void {
        $formatted = MoneyNormalizer::format(1999, 'USD', 'en_US');

        expect($formatted)->toContain('19.99');
    });
});

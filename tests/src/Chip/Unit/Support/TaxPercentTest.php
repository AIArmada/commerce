<?php

declare(strict_types=1);

use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Support\TaxPercent;

describe('TaxPercent', function (): void {
    it('accepts values within the server rule', function (): void {
        expect(TaxPercent::normalize(6))->toBe(6)
            ->and(TaxPercent::normalize(6.0))->toBe(6.0)
            ->and(TaxPercent::normalize('6'))->toBe('6')
            ->and(TaxPercent::normalize('6.00'))->toBe('6.00')
            ->and(TaxPercent::normalize(' 6 '))->toBe('6')
            ->and(TaxPercent::normalize('100.00'))->toBe('100.00')
            ->and(TaxPercent::normalize('1e2'))->toBe('1e2');
    });

    it('rejects out-of-range and non-numeric tax', function (): void {
        expect(fn () => TaxPercent::normalize(-1))->toThrow(ChipValidationException::class, 'between 0 and 100')
            ->and(fn () => TaxPercent::normalize(100.01))->toThrow(ChipValidationException::class, 'between 0 and 100')
            ->and(fn () => TaxPercent::normalize('lots'))->toThrow(ChipValidationException::class, 'between 0 and 100');
    });

    it('mirrors the server precision rule: 5 digits, 2 decimal places', function (): void {
        // The server 400s max_decimal_places / max_digits here.
        expect(fn () => TaxPercent::normalize('6.555'))->toThrow(ChipValidationException::class, 'at most 5 digits')
            ->and(fn () => TaxPercent::normalize('99.9999'))->toThrow(ChipValidationException::class, 'at most 5 digits')
            ->and(fn () => TaxPercent::normalize('1e-2000'))->toThrow(ChipValidationException::class, 'at most 5 digits');
    });

    it('counts decimal places on zero values like the server', function (): void {
        // The server 400s '0.000' with max_decimal_places.
        expect(TaxPercent::normalize('0.00'))->toBe('0.00')
            ->and(fn () => TaxPercent::normalize('0.000'))->toThrow(ChipValidationException::class, 'at most 5 digits')
            ->and(fn () => TaxPercent::normalize('0.' . str_repeat('0', 1025)))->toThrow(ChipValidationException::class, 'at most 5 digits');
    });
});

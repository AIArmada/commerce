<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\CurrencyConversion;

describe('CurrencyConversion data object', function (): void {
    it('converts original amount to major currency units', function (): void {
        $conversion = CurrencyConversion::fromArray([
            'original_currency' => 'USD',
            'original_amount' => 12345,
            'exchange_rate' => 4.56,
        ]);

        expect($conversion->original_currency)->toBe('USD');
        expect($conversion->getOriginalAmountInCurrency())->toBe(123.45);
    });

    it('exports array representation', function (): void {
        $conversion = new CurrencyConversion('EUR', 5000, 4.95);

        expect($conversion->toArray())->toBe([
            'original_currency' => 'EUR',
            'original_amount' => 5000,
            'exchange_rate' => 4.95,
        ]);
    });
});

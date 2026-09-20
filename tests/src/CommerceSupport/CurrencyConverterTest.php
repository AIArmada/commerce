<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\CurrencyConverter;

function currencyConverter(array $rates = [], string $base = 'USD'): CurrencyConverter
{
    config(['commerce-support.currency.exchange_rates' => [
        'base' => $base,
        'rates' => $rates,
    ]]);

    return app(CurrencyConverter::class);
}

describe('CurrencyConverter', function (): void {
    it('converts minor units between currencies', function (): void {
        $converter = currencyConverter(['MYR' => 4.7]);

        expect($converter->convertMinor(10000, 'USD', 'MYR'))->toBe(47000)
            ->and($converter->convertMinor(47000, 'MYR', 'USD'))->toBe(10000);
    });

    it('returns the amount unchanged for the same currency', function (): void {
        expect(currencyConverter()->convertMinor(12345, 'MYR', 'myr'))->toBe(12345);
    });

    it('returns null when no rate is known', function (): void {
        $converter = currencyConverter(['MYR' => 4.7]);

        expect($converter->convertMinor(10000, 'USD', 'JPY'))->toBeNull()
            ->and($converter->convertMinor(10000, 'JPY', 'USD'))->toBeNull();
    });

    it('totals mixed currencies into the base currency', function (): void {
        $converter = currencyConverter(['MYR' => 4.0]);

        expect($converter->totalMinor(['USD' => 100, 'MYR' => 400]))->toBe(200);
    });

    it('totals into an explicit target currency', function (): void {
        $converter = currencyConverter(['MYR' => 4.0, 'EUR' => 2.0]);

        // 100 USD = 200 EUR, plus 400 MYR = 200 EUR.
        expect($converter->totalMinor(['USD' => 100, 'MYR' => 400], 'EUR'))->toBe(400);
    });

    it('refuses partial totals when any leg is unconvertible', function (): void {
        $converter = currencyConverter(['MYR' => 4.0]);

        expect($converter->totalMinor(['USD' => 100, 'JPY' => 5000]))->toBeNull();
    });
});

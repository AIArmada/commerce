<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use Illuminate\Support\Facades\Artisan;

function currencyConverter(array $rates = [], string $base = 'USD', array $history = []): CurrencyConverter
{
    $settings = new ExchangeRateSettings([
        'base' => $base,
        'rates' => $rates,
        'history' => $history,
    ]);
    $settings->save();

    return app(CurrencyConverter::class);
}

describe('CurrencyConverter', function (): void {
    beforeEach(function (): void {
        // Settings cache outlives RefreshDatabase rollbacks within a process.
        Artisan::call('settings:clear-cache');
    });

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

    it('converts with the rate effective at asOf', function (): void {
        $converter = currencyConverter(['MYR' => 4.7], 'USD', ['2026-01-01' => ['MYR' => 4.0]]);
        $asOf = new DateTimeImmutable('2026-03-01');

        expect($converter->convertMinor(400, 'MYR', 'USD', $asOf))->toBe(100)
            ->and($converter->totalMinor(['MYR' => 400], 'USD', $asOf))->toBe(100);
    });
});

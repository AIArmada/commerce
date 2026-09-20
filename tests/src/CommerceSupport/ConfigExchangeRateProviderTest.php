<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ConfigExchangeRateProvider;

function exchangeRateProvider(array $rates = [], string $base = 'USD'): ConfigExchangeRateProvider
{
    config(['commerce-support.currency.exchange_rates' => [
        'base' => $base,
        'rates' => $rates,
    ]]);

    return app(ConfigExchangeRateProvider::class);
}

describe('ConfigExchangeRateProvider', function (): void {
    it('returns identity for the same currency without any config', function (): void {
        expect(exchangeRateProvider()->rate('USD', 'USD'))->toBe(1.0)
            ->and(exchangeRateProvider()->rate('myr', 'MYR'))->toBe(1.0);
    });

    it('derives cross rates through the base', function (): void {
        $provider = exchangeRateProvider(['MYR' => 4.7, 'EUR' => 0.92]);

        expect($provider->rate('USD', 'MYR'))->toBe(4.7)
            ->and($provider->rate('MYR', 'USD'))->toBe(1 / 4.7)
            ->and($provider->rate('MYR', 'EUR'))->toBe(0.92 / 4.7);
    });

    it('implies 1.0 for an unlisted base currency', function (): void {
        $provider = exchangeRateProvider(['MYR' => 4.7]);

        expect($provider->rate('MYR', 'USD'))->toBe(1 / 4.7);
    });

    it('returns null when either side is unknown', function (): void {
        $provider = exchangeRateProvider(['MYR' => 4.7]);

        expect($provider->rate('MYR', 'JPY'))->toBeNull()
            ->and($provider->rate('JPY', 'USD'))->toBeNull()
            ->and(exchangeRateProvider()->rate('MYR', 'USD'))->toBeNull();
    });

    it('ignores zero and negative rates', function (): void {
        $provider = exchangeRateProvider(['MYR' => 0, 'EUR' => -1.5]);

        expect($provider->rate('USD', 'MYR'))->toBeNull()
            ->and($provider->rate('EUR', 'USD'))->toBeNull();
    });

    it('matches currency codes case-insensitively', function (): void {
        $provider = exchangeRateProvider(['myr' => 4.7], 'usd');

        expect($provider->rate('usd', 'myr'))->toBe(4.7)
            ->and($provider->baseCurrency())->toBe('USD');
    });

    it('reads the base currency from config', function (): void {
        expect(exchangeRateProvider(base: 'MYR')->baseCurrency())->toBe('MYR')
            ->and(exchangeRateProvider()->baseCurrency())->toBe('USD');
    });
});

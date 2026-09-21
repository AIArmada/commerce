<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;
use AIArmada\CommerceSupport\Support\SettingsExchangeRateProvider;
use Illuminate\Support\Facades\Artisan;

function seedExchangeRateSettings(string $base, array $rates, array $history = []): void
{
    $settings = new ExchangeRateSettings([
        'base' => $base,
        'rates' => $rates,
        'history' => $history,
    ]);
    $settings->save();
}

describe('SettingsExchangeRateProvider', function (): void {
    beforeEach(function (): void {
        // Settings cache outlives RefreshDatabase rollbacks within a process.
        Artisan::call('settings:clear-cache');
    });

    it('ships migration defaults through the normal migrate flow', function (): void {
        $settings = app(ExchangeRateSettings::class);

        expect($settings->base)->toBe('USD')
            ->and($settings->rates)->toBe([])
            ->and($settings->history)->toBe([]);
    });

    it('falls back to config when settings cannot load', function (): void {
        config(['commerce-support.currency.exchange_rates' => [
            'base' => 'USD',
            'rates' => ['MYR' => 4.7],
            'history' => [],
        ]]);

        // An unloadable settings object: property access throws before any
        // value is read, exercising the config fallback branch.
        $unloadable = (new ReflectionClass(ExchangeRateSettings::class))->newInstanceWithoutConstructor();
        app()->instance(ExchangeRateSettings::class, $unloadable);

        $provider = app(SettingsExchangeRateProvider::class);

        expect($provider->baseCurrency())->toBe('USD')
            ->and($provider->rate('USD', 'MYR'))->toBe(4.7);
    });

    it('prefers settings values once saved', function (): void {
        config(['commerce-support.currency.exchange_rates' => [
            'base' => 'USD',
            'rates' => ['MYR' => 4.7],
            'history' => [],
        ]]);
        seedExchangeRateSettings('USD', ['MYR' => 5.0]);

        $provider = app(SettingsExchangeRateProvider::class);

        expect($provider->rate('USD', 'MYR'))->toBe(5.0);
    });

    it('applies settings history for as-of conversion', function (): void {
        seedExchangeRateSettings('USD', ['MYR' => 6.0], ['2026-01-01' => ['MYR' => 4.0]]);

        $provider = app(SettingsExchangeRateProvider::class);

        expect($provider->rate('USD', 'MYR', new DateTimeImmutable('2026-03-01')))->toBe(4.0)
            ->and($provider->rate('USD', 'MYR'))->toBe(6.0);
    });
});

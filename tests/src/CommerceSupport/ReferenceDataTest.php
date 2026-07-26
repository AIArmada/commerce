<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Actions\SeedCurrenciesAction;
use AIArmada\CommerceSupport\Actions\SeedLanguagesAction;
use AIArmada\CommerceSupport\Actions\SeedTimezonesAction;
use AIArmada\CommerceSupport\Models\Currency;
use AIArmada\CommerceSupport\Models\Language;
use AIArmada\CommerceSupport\Models\Timezone;

it('seeds the shared reference catalogues', function (): void {
    app(SeedCurrenciesAction::class)->execute();
    app(SeedLanguagesAction::class)->execute();
    app(SeedTimezonesAction::class)->execute();

    expect(Currency::count())->toBeGreaterThan(150)
        ->and(Language::count())->toBeGreaterThan(100)
        ->and(Timezone::count())->toBeGreaterThan(300)
        ->and(Currency::where('code', 'MYR')->exists())->toBeTrue()
        ->and(Currency::where('code', 'AAD')->exists())->toBeFalse()
        ->and(Language::where('code', 'ms')->exists())->toBeTrue()
        ->and(Timezone::where('name', 'Asia/Kuala_Lumpur')->exists())->toBeTrue();
});

it('seeds the shared reference catalogues idempotently', function (): void {
    $currencies = app(SeedCurrenciesAction::class);
    $languages = app(SeedLanguagesAction::class);
    $timezones = app(SeedTimezonesAction::class);

    $currencies->execute();
    $languages->execute();
    $timezones->execute();

    expect($currencies->execute()['created'])->toBe(0)
        ->and($languages->execute()['created'])->toBe(0)
        ->and($timezones->execute()['created'])->toBe(0);
});

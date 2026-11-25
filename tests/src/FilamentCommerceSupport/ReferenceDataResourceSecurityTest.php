<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Features\ReferenceData\Models\Currency;
use AIArmada\CommerceSupport\Features\ReferenceData\Models\Language;
use AIArmada\CommerceSupport\Features\ReferenceData\Models\Timezone;
use AIArmada\FilamentCommerceSupport\Resources\CurrencyResource;
use AIArmada\FilamentCommerceSupport\Resources\LanguageResource;
use AIArmada\FilamentCommerceSupport\Resources\TimezoneResource;
use Filament\Facades\Filament;

beforeEach(function (): void {
    $user = User::query()->create([
        'name' => 'Reference Data Viewer',
        'email' => 'reference-data-viewer@example.com',
        'password' => 'secret',
    ]);

    Filament::auth()->login($user);
});

it('enforces the read-only gate on every reference-data resource', function (): void {
    config()->set('filament-commerce-support.resources.currencies.read_only', true);
    config()->set('filament-commerce-support.resources.languages.read_only', true);
    config()->set('filament-commerce-support.resources.timezones.read_only', true);

    expect(CurrencyResource::canCreate())->toBeFalse()
        ->and(CurrencyResource::canEdit(new Currency))->toBeFalse()
        ->and(CurrencyResource::canDelete(new Currency))->toBeFalse()
        ->and(CurrencyResource::canDeleteAny())->toBeFalse()
        ->and(LanguageResource::canCreate())->toBeFalse()
        ->and(LanguageResource::canEdit(new Language))->toBeFalse()
        ->and(LanguageResource::canDelete(new Language))->toBeFalse()
        ->and(LanguageResource::canDeleteAny())->toBeFalse()
        ->and(TimezoneResource::canCreate())->toBeFalse()
        ->and(TimezoneResource::canEdit(new Timezone))->toBeFalse()
        ->and(TimezoneResource::canDelete(new Timezone))->toBeFalse()
        ->and(TimezoneResource::canDeleteAny())->toBeFalse();
});

it('delegates to default authorization when a reference-data resource is writable', function (): void {
    config()->set('filament-commerce-support.resources.currencies.read_only', false);
    config()->set('filament-commerce-support.resources.languages.read_only', false);
    config()->set('filament-commerce-support.resources.timezones.read_only', false);

    expect(CurrencyResource::canCreate())->toBeTrue()
        ->and(CurrencyResource::canEdit(new Currency))->toBeTrue()
        ->and(CurrencyResource::canDelete(new Currency))->toBeTrue()
        ->and(CurrencyResource::canDeleteAny())->toBeTrue()
        ->and(LanguageResource::canCreate())->toBeTrue()
        ->and(TimezoneResource::canCreate())->toBeTrue();
});

it('registers each reference-data resource from its own enabled flag even when navigation is disabled', function (): void {
    config()->set('filament-commerce-support.navigation.enabled', false);
    config()->set('filament-commerce-support.resources.currencies.enabled', true);
    config()->set('filament-commerce-support.resources.languages.enabled', true);
    config()->set('filament-commerce-support.resources.timezones.enabled', true);

    expect(CurrencyResource::shouldRegisterNavigation())->toBeTrue()
        ->and(LanguageResource::shouldRegisterNavigation())->toBeTrue()
        ->and(TimezoneResource::shouldRegisterNavigation())->toBeTrue();

    config()->set('filament-commerce-support.resources.currencies.enabled', false);
    config()->set('filament-commerce-support.resources.languages.enabled', false);
    config()->set('filament-commerce-support.resources.timezones.enabled', false);

    expect(CurrencyResource::shouldRegisterNavigation())->toBeFalse()
        ->and(LanguageResource::shouldRegisterNavigation())->toBeFalse()
        ->and(TimezoneResource::shouldRegisterNavigation())->toBeFalse();
});

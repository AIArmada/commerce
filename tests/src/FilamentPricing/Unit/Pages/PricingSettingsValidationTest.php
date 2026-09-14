<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentPricing\Pages\ManagePricingSettings;
use AIArmada\FilamentPricing\Support\PricingSettingsAccess;
use AIArmada\Pricing\Settings\PricingSettings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

uses(TestCase::class);

function validPricingSettingsData(): array
{
    return [
        'defaultCurrency' => 'USD',
        'decimalPlaces' => 2,
        'roundingMode' => 'half_up',
        'pricesIncludeTax' => false,
        'minimumOrderValue' => 0,
        'maximumOrderValue' => 1000,
        'promotionalPricingEnabled' => true,
        'tieredPricingEnabled' => true,
        'customerGroupPricingEnabled' => false,
    ];
}

it('rejects out-of-range settings instead of persisting them', function (array $override): void {
    $settings = Mockery::mock(PricingSettings::class)->makePartial();
    $settings->shouldNotReceive('save');

    app()->instance(PricingSettings::class, $settings);

    $page = new ManagePricingSettings;
    $page->data = array_merge(validPricingSettingsData(), $override);

    expect(fn () => $page->save())->toThrow(ValidationException::class);
})->with([
    'decimal places above max' => [['decimalPlaces' => 9]],
    'decimal places below min' => [['decimalPlaces' => -1]],
    'unknown currency' => [['defaultCurrency' => 'XX']],
    'unknown rounding' => [['roundingMode' => 'sideways']],
    'negative minimum' => [['minimumOrderValue' => -5]],
]);

it('keeps the settings page closed without the configured ability', function (): void {
    config()->set('filament-pricing.authorization.settings_ability', 'pricing.manage-settings');

    $user = User::query()->create(['name' => 'Shopper', 'email' => 'pricing-shopper@example.com', 'password' => 'secret']);

    expect(PricingSettingsAccess::allows($user))->toBeFalse()
        ->and(ManagePricingSettings::canAccess())->toBeFalse()
        ->and(ManagePricingSettings::shouldRegisterNavigation())->toBeFalse();
});

it('opens the settings page for ability holders', function (): void {
    config()->set('filament-pricing.authorization.settings_ability', 'pricing.manage-settings');

    Gate::define('pricing.manage-settings', fn (): bool => true);

    $user = User::query()->create(['name' => 'Admin', 'email' => 'pricing-admin@example.com', 'password' => 'secret']);

    expect(PricingSettingsAccess::allows($user))->toBeTrue();

    $this->actingAs($user);

    expect(ManagePricingSettings::canAccess())->toBeTrue();
});

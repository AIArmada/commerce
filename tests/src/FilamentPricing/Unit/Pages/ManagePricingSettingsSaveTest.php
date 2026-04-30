<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentPricing\Pages\ManagePricingSettings;
use AIArmada\Pricing\Settings\PricingSettings;

uses(TestCase::class);

it('hydrates data from PricingSettings on mount and fills the form', function (): void {
    $settings = Mockery::mock(PricingSettings::class);
    $settings->defaultCurrency = 'USD';
    $settings->decimalPlaces = 3;
    $settings->roundingMode = 'down';
    $settings->pricesIncludeTax = true;
    $settings->minimumOrderValue = 100;
    $settings->maximumOrderValue = 500;
    $settings->promotionalPricingEnabled = false;
    $settings->tieredPricingEnabled = true;
    $settings->customerGroupPricingEnabled = true;

    // Mount should not persist.
    $settings->shouldNotReceive('save');

    app()->instance(PricingSettings::class, $settings);

    $page = app(ManagePricingSettings::class);

    $page->mount();

    expect($page->data['defaultCurrency'])->toBe('USD');
    expect($page->data['decimalPlaces'])->toEqual(3);
    expect($page->data['pricesIncludeTax'])->toBeTrue();
});

it('persists settings from page data when saving', function (): void {
    $settings = Mockery::mock(PricingSettings::class);
    $settings->shouldReceive('save')->once();
    app()->instance(PricingSettings::class, $settings);

    $page = app(ManagePricingSettings::class);

    $page->data = [
        'defaultCurrency' => 'SGD',
        'decimalPlaces' => 2,
        'roundingMode' => 'half_up',
        'pricesIncludeTax' => false,
        'minimumOrderValue' => 123,
        'maximumOrderValue' => 456,
        'promotionalPricingEnabled' => true,
        'tieredPricingEnabled' => false,
        'customerGroupPricingEnabled' => true,
    ];

    $page->save();

    expect($settings->defaultCurrency)->toBe('SGD');
    expect($settings->decimalPlaces)->toBe(2);
    expect($settings->roundingMode)->toBe('half_up');
    expect($settings->pricesIncludeTax)->toBeFalse();
    expect($settings->minimumOrderValue)->toBe(123);
    expect($settings->maximumOrderValue)->toBe(456);
    expect($settings->promotionalPricingEnabled)->toBeTrue();
    expect($settings->tieredPricingEnabled)->toBeFalse();
    expect($settings->customerGroupPricingEnabled)->toBeTrue();
});

it('exposes a save header action', function (): void {
    $page = app(ManagePricingSettings::class);

    $method = new ReflectionMethod(ManagePricingSettings::class, 'getHeaderActions');

    /** @var array<int, object> $actions */
    $actions = $method->invoke($page);

    expect($actions)->toHaveCount(1);
});

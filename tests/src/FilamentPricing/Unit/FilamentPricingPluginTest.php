<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentPricing\FilamentPricingPlugin;
use AIArmada\FilamentPricing\Pages\ManagePricingSettings;
use AIArmada\FilamentPricing\Pages\PriceSimulator;
use AIArmada\FilamentPricing\Resources\PriceListResource;
use AIArmada\FilamentPricing\Resources\PromotionResource;
use AIArmada\FilamentPricing\Widgets\PricingStatsWidget;
use Filament\FilamentManager;
use Filament\Panel;

it('exposes a stable plugin id', function (): void {
    $plugin = app(FilamentPricingPlugin::class);

    expect($plugin->getId())->toBe('filament-pricing');
});

it('can be resolved via make()', function (): void {
    $plugin = FilamentPricingPlugin::make();

    expect($plugin)->toBeInstanceOf(FilamentPricingPlugin::class);
    expect($plugin->getId())->toBe('filament-pricing');
});

it('resolves via filament() helper in get()', function (): void {
    $expectedPlugin = app(FilamentPricingPlugin::class);

    $originalFilament = app()->bound('filament') ? app('filament') : null;

    $filament = Mockery::mock(FilamentManager::class);
    $filament->shouldReceive('getPlugin')
        ->once()
        ->with('filament-pricing')
        ->andReturn($expectedPlugin);

    app()->instance('filament', $filament);

    expect(FilamentPricingPlugin::get())->toBe($expectedPlugin);

    if ($originalFilament) {
        app()->instance('filament', $originalFilament);
    } else {
        app()->forgetInstance('filament');
    }
});

it('registers resources and widgets on the panel', function (): void {
    $panel = Mockery::mock(Panel::class);

    $expectedResources = [
        PriceListResource::class,
    ];

    if (class_exists('\\AIArmada\\Promotions\\Models\\Promotion')) {
        $expectedResources[] = PromotionResource::class;
    }

    $panel->shouldReceive('resources')->once()->with($expectedResources)->andReturnSelf();

    $expectedPages = [
        ManagePricingSettings::class,
    ];

    if (class_exists('\\AIArmada\\Products\\Models\\Product') && class_exists('\\AIArmada\\Products\\Models\\Variant')) {
        $expectedPages[] = PriceSimulator::class;
    }

    $panel->shouldReceive('pages')->once()->with($expectedPages)->andReturnSelf();

    $panel->shouldReceive('widgets')->once()->with([
        PricingStatsWidget::class,
    ])->andReturnSelf();

    $plugin = app(FilamentPricingPlugin::class);
    $plugin->register($panel);
});

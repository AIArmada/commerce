<?php

declare(strict_types=1);

use AIArmada\FilamentShipping\FilamentShippingPlugin;
use AIArmada\FilamentShipping\Pages\ManifestPage;
use AIArmada\FilamentShipping\Pages\ShippingDashboard;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource;
use AIArmada\FilamentShipping\Resources\ShipmentResource;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource;
use AIArmada\FilamentShipping\Widgets\CarrierPerformanceWidget;
use AIArmada\FilamentShipping\Widgets\PendingActionsWidget;
use AIArmada\FilamentShipping\Widgets\PendingShipmentsWidget;
use AIArmada\FilamentShipping\Widgets\ShippingDashboardWidget;
use Filament\Panel;

// ============================================
// FilamentShippingPlugin Tests
// ============================================

it('creates plugin instance', function (): void {
    $plugin = FilamentShippingPlugin::make();

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('returns correct plugin id', function (): void {
    $plugin = FilamentShippingPlugin::make();

    expect($plugin->getId())->toBe('filament-shipping');
});

it('can disable shipment resource', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->shipmentResource(false);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('can disable shipping zone resource', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->shippingZoneResource(false);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('can disable return authorization resource', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->returnAuthorizationResource(false);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('can disable dashboard widgets', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->dashboardWidgets(false);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('can disable shipping dashboard page', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->shippingDashboard(false);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('can disable manifest page', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->manifestPage(false);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('supports method chaining for configuration', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->shipmentResource(true)
        ->shippingZoneResource(true)
        ->returnAuthorizationResource(true)
        ->dashboardWidgets(true)
        ->shippingDashboard(true)
        ->manifestPage(true);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('can disable all features', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->shipmentResource(false)
        ->shippingZoneResource(false)
        ->returnAuthorizationResource(false)
        ->dashboardWidgets(false)
        ->shippingDashboard(false)
        ->manifestPage(false);

    expect($plugin)->toBeInstanceOf(FilamentShippingPlugin::class);
});

it('registers resources, pages, and widgets by default', function (): void {
    $plugin = FilamentShippingPlugin::make();

    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            ShipmentResource::class,
            ShippingZoneResource::class,
            ReturnAuthorizationResource::class,
        ])
        ->andReturnSelf();

    $panel->shouldReceive('pages')
        ->once()
        ->with([
            ShippingDashboard::class,
            ManifestPage::class,
        ])
        ->andReturnSelf();

    $panel->shouldReceive('widgets')
        ->once()
        ->with([
            ShippingDashboardWidget::class,
            PendingShipmentsWidget::class,
            CarrierPerformanceWidget::class,
            PendingActionsWidget::class,
        ])
        ->andReturnSelf();

    $plugin->register($panel);
});

it('registers nothing when all features are disabled', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->shipmentResource(false)
        ->shippingZoneResource(false)
        ->returnAuthorizationResource(false)
        ->dashboardWidgets(false)
        ->shippingDashboard(false)
        ->manifestPage(false);

    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('resources')->once()->with([])->andReturnSelf();
    $panel->shouldReceive('pages')->once()->with([])->andReturnSelf();
    $panel->shouldReceive('widgets')->once()->with([])->andReturnSelf();

    $plugin->register($panel);
});

<?php

declare(strict_types=1);

use AIArmada\FilamentShipping\FilamentShippingPlugin;
use AIArmada\FilamentShipping\Pages\FulfillmentQueue;
use AIArmada\FilamentShipping\Pages\ManifestPage;
use AIArmada\FilamentShipping\Pages\ShippingDashboard;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource;
use AIArmada\FilamentShipping\Resources\ShipmentResource;
use AIArmada\FilamentShipping\Resources\ShippingRateResource;
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

it('supports method chaining for all feature toggles', function (): void {
    $plugin = FilamentShippingPlugin::make()
        ->shipmentResource(false)
        ->shippingZoneResource(false)
        ->shippingRateResource(false)
        ->returnAuthorizationResource(false)
        ->dashboardWidgets(false)
        ->shippingDashboard(false)
        ->manifestPage(false)
        ->fulfillmentQueue(false);

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
            ShippingRateResource::class,
            ReturnAuthorizationResource::class,
        ])
        ->andReturnSelf();

    $panel->shouldReceive('pages')
        ->once()
        ->with([
            ShippingDashboard::class,
            ManifestPage::class,
            FulfillmentQueue::class,
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
        ->shippingRateResource(false)
        ->returnAuthorizationResource(false)
        ->dashboardWidgets(false)
        ->shippingDashboard(false)
        ->manifestPage(false)
        ->fulfillmentQueue(false);

    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('resources')->once()->with([])->andReturnSelf();
    $panel->shouldReceive('pages')->once()->with([])->andReturnSelf();
    $panel->shouldReceive('widgets')->once()->with([])->andReturnSelf();

    $plugin->register($panel);
});

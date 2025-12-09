<?php

declare(strict_types=1);

use AIArmada\FilamentCashier\FilamentCashierPlugin;
use AIArmada\FilamentCashier\Support\GatewayDetector;

beforeEach(function (): void {
    // Reset the app binding before each test
    app()->forgetInstance(GatewayDetector::class);
});

it('exposes a stable plugin id', function (): void {
    expect((new FilamentCashierPlugin())->getId())->toBe('filament-cashier');
});

it('can be created using make method', function (): void {
    $plugin = FilamentCashierPlugin::make();

    expect($plugin)->toBeInstanceOf(FilamentCashierPlugin::class);
});

it('has default navigation group', function (): void {
    $plugin = new FilamentCashierPlugin();

    expect($plugin->getNavigationGroup())->toBe('Billing');
});

it('can set custom navigation group', function (): void {
    $plugin = (new FilamentCashierPlugin())->navigationGroup('Payments');

    expect($plugin->getNavigationGroup())->toBe('Payments');
});

it('can set navigation sort', function (): void {
    $plugin = (new FilamentCashierPlugin())->navigationSort(10);

    expect($plugin->getNavigationSort())->toBe(10);
});

it('can enable and disable dashboard', function (): void {
    $plugin = new FilamentCashierPlugin();

    // Default enabled
    expect($plugin)->enableDashboard()->toBeInstanceOf(FilamentCashierPlugin::class);

    // Can disable
    expect($plugin->enableDashboard(false))->toBeInstanceOf(FilamentCashierPlugin::class);
});

it('can enable and disable subscriptions', function (): void {
    $plugin = new FilamentCashierPlugin();

    expect($plugin->enableSubscriptions())->toBeInstanceOf(FilamentCashierPlugin::class);
    expect($plugin->enableSubscriptions(false))->toBeInstanceOf(FilamentCashierPlugin::class);
});

it('can enable and disable invoices', function (): void {
    $plugin = new FilamentCashierPlugin();

    expect($plugin->enableInvoices())->toBeInstanceOf(FilamentCashierPlugin::class);
    expect($plugin->enableInvoices(false))->toBeInstanceOf(FilamentCashierPlugin::class);
});

it('can enable gateway management', function (): void {
    $plugin = new FilamentCashierPlugin();

    expect($plugin->enableGatewayManagement())->toBeInstanceOf(FilamentCashierPlugin::class);
    expect($plugin->enableGatewayManagement(true))->toBeInstanceOf(FilamentCashierPlugin::class);
});

it('can enable customer portal mode', function (): void {
    $plugin = new FilamentCashierPlugin();

    expect($plugin->customerPortalMode())->toBeInstanceOf(FilamentCashierPlugin::class);
    expect($plugin->customerPortalMode(true))->toBeInstanceOf(FilamentCashierPlugin::class);
});

it('gateway detector can be instantiated', function (): void {
    $detector = new GatewayDetector();

    expect($detector)->toBeInstanceOf(GatewayDetector::class);
});

it('gateway detector returns available gateways as collection', function (): void {
    $detector = new GatewayDetector();
    $gateways = $detector->availableGateways();

    expect($gateways)->toBeInstanceOf(Illuminate\Support\Collection::class);
});

it('gateway detector provides gateway options', function (): void {
    $detector = new GatewayDetector();
    $options = $detector->getGatewayOptions();

    expect($options)->toBeArray();
});

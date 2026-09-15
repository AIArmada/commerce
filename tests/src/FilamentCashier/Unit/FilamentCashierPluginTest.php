<?php

declare(strict_types=1);

use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\FilamentCashierPlugin;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    // Reset the app binding before each test
    app()->forgetInstance(GatewayDetector::class);
});

it('exposes a stable plugin id', function (): void {
    expect((new FilamentCashierPlugin)->getId())->toBe('filament-cashier');
});

it('can be created using make method', function (): void {
    $plugin = FilamentCashierPlugin::make();

    expect($plugin)->toBeInstanceOf(FilamentCashierPlugin::class);
});

it('has default navigation group', function (): void {
    $plugin = new FilamentCashierPlugin;

    expect($plugin->getNavigationGroup())->toBe('Billing');
});

it('can set custom navigation group', function (): void {
    $plugin = (new FilamentCashierPlugin)->navigationGroup('Payments');

    expect($plugin->getNavigationGroup())->toBe('Payments');
});

it('can set navigation sort', function (): void {
    $plugin = (new FilamentCashierPlugin)->navigationSort(10);

    expect($plugin->getNavigationSort())->toBe(10);
});

it('can enable and disable dashboard', function (): void {
    $plugin = new FilamentCashierPlugin;

    expect($plugin)->dashboard()->toBeInstanceOf(FilamentCashierPlugin::class);
    expect($plugin->dashboard(false))->toBeInstanceOf(FilamentCashierPlugin::class);
});

it('can enable and disable subscriptions', function (): void {
    $plugin = new FilamentCashierPlugin;

    expect($plugin->subscriptions())->toBeInstanceOf(FilamentCashierPlugin::class);
    expect($plugin->subscriptions(false))->toBeInstanceOf(FilamentCashierPlugin::class);
});

it('can enable and disable invoices', function (): void {
    $plugin = new FilamentCashierPlugin;

    expect($plugin->invoices())->toBeInstanceOf(FilamentCashierPlugin::class);
    expect($plugin->invoices(false))->toBeInstanceOf(FilamentCashierPlugin::class);
});

it('can enable gateway management', function (): void {
    $plugin = new FilamentCashierPlugin;

    expect($plugin->gatewayManagement())->toBeInstanceOf(FilamentCashierPlugin::class);
    expect($plugin->gatewayManagement(true))->toBeInstanceOf(FilamentCashierPlugin::class);
});

it('can enable customer portal mode', function (): void {
    $plugin = new FilamentCashierPlugin;

    expect($plugin->customerPortalMode())->toBeInstanceOf(FilamentCashierPlugin::class);
    expect($plugin->customerPortalMode(true))->toBeInstanceOf(FilamentCashierPlugin::class);
});

it('defines the billing portal login toggle in config', function (): void {
    $config = require __DIR__ . '/../../../../packages/filament-cashier/config/filament-cashier.php';

    expect($config['billing_portal'])->toHaveKey('login_enabled', true);
});

/* GatewayDetector instantiation/collection/options removed; identical in GatewayDetectorTest. */

<?php

declare(strict_types=1);

use AIArmada\FilamentCashierChip\FilamentCashierChipPlugin;
use Filament\Contracts\Plugin;

it('exposes a stable plugin id', function (): void {
    expect((new FilamentCashierChipPlugin)->getId())->toBe('filament-cashier-chip');
});

it('can be created using make method', function (): void {
    $plugin = FilamentCashierChipPlugin::make();

    expect($plugin)->toBeInstanceOf(FilamentCashierChipPlugin::class);
});

it('enables subscriptions by default', function (): void {
    $plugin = new FilamentCashierChipPlugin;
    $reflection = new ReflectionClass($plugin);
    $property = $reflection->getProperty('hasSubscriptions');

    expect($property->getValue($plugin))->toBeTrue();
});

it('can disable subscriptions', function (): void {
    $plugin = (new FilamentCashierChipPlugin)->subscriptions(false);
    $reflection = new ReflectionClass($plugin);
    $property = $reflection->getProperty('hasSubscriptions');

    expect($property->getValue($plugin))->toBeFalse();
});

it('enables customers by default', function (): void {
    $plugin = new FilamentCashierChipPlugin;
    $reflection = new ReflectionClass($plugin);
    $property = $reflection->getProperty('hasCustomers');

    expect($property->getValue($plugin))->toBeTrue();
});

it('can disable customers', function (): void {
    $plugin = (new FilamentCashierChipPlugin)->customers(false);
    $reflection = new ReflectionClass($plugin);
    $property = $reflection->getProperty('hasCustomers');

    expect($property->getValue($plugin))->toBeFalse();
});

it('enables invoices by default', function (): void {
    $plugin = new FilamentCashierChipPlugin;
    $reflection = new ReflectionClass($plugin);
    $property = $reflection->getProperty('hasInvoices');

    expect($property->getValue($plugin))->toBeTrue();
});

it('can disable invoices', function (): void {
    $plugin = (new FilamentCashierChipPlugin)->invoices(false);
    $reflection = new ReflectionClass($plugin);
    $property = $reflection->getProperty('hasInvoices');

    expect($property->getValue($plugin))->toBeFalse();
});

it('enables dashboard widgets by default', function (): void {
    $plugin = new FilamentCashierChipPlugin;
    $reflection = new ReflectionClass($plugin);
    $property = $reflection->getProperty('hasDashboardWidgets');

    expect($property->getValue($plugin))->toBeTrue();
});

it('can disable dashboard widgets', function (): void {
    $plugin = (new FilamentCashierChipPlugin)->dashboardWidgets(false);
    $reflection = new ReflectionClass($plugin);
    $property = $reflection->getProperty('hasDashboardWidgets');

    expect($property->getValue($plugin))->toBeFalse();
});

it('enables billing dashboard by default', function (): void {
    $plugin = new FilamentCashierChipPlugin;
    $reflection = new ReflectionClass($plugin);
    $property = $reflection->getProperty('hasBillingDashboard');

    expect($property->getValue($plugin))->toBeTrue();
});

it('can disable billing dashboard', function (): void {
    $plugin = (new FilamentCashierChipPlugin)->billingDashboard(false);
    $reflection = new ReflectionClass($plugin);
    $property = $reflection->getProperty('hasBillingDashboard');

    expect($property->getValue($plugin))->toBeFalse();
});

it('returns fluent instance from subscriptions method', function (): void {
    $plugin = new FilamentCashierChipPlugin;

    expect($plugin->subscriptions())->toBe($plugin);
});

it('returns fluent instance from customers method', function (): void {
    $plugin = new FilamentCashierChipPlugin;

    expect($plugin->customers())->toBe($plugin);
});

it('returns fluent instance from invoices method', function (): void {
    $plugin = new FilamentCashierChipPlugin;

    expect($plugin->invoices())->toBe($plugin);
});

it('returns fluent instance from dashboardWidgets method', function (): void {
    $plugin = new FilamentCashierChipPlugin;

    expect($plugin->dashboardWidgets())->toBe($plugin);
});

it('returns fluent instance from billingDashboard method', function (): void {
    $plugin = new FilamentCashierChipPlugin;

    expect($plugin->billingDashboard())->toBe($plugin);
});

it('implements filament plugin interface', function (): void {
    $plugin = new FilamentCashierChipPlugin;

    expect($plugin)->toBeInstanceOf(Plugin::class);
});

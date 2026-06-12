<?php

declare(strict_types=1);

use AIArmada\FilamentAddressing\FilamentAddressingPlugin;

it('registers only enabled resources', function (): void {
    config()->set('filament-addressing.resources.countries.enabled', true);
    config()->set('filament-addressing.resources.areas.enabled', true);
    config()->set('filament-addressing.resources.addresses.enabled', false);
    config()->set('filament-addressing.resources.snapshots.enabled', false);

    $plugin = FilamentAddressingPlugin::make();

    expect($plugin)->toBeInstanceOf(FilamentAddressingPlugin::class);
    expect($plugin->getId())->toBe('filament-addressing');
});

it('registers all resources when enabled', function (): void {
    config()->set('filament-addressing.resources.countries.enabled', true);
    config()->set('filament-addressing.resources.areas.enabled', true);
    config()->set('filament-addressing.resources.addresses.enabled', true);
    config()->set('filament-addressing.resources.snapshots.enabled', true);

    $plugin = FilamentAddressingPlugin::make();

    expect($plugin)->toBeInstanceOf(FilamentAddressingPlugin::class);
});

it('has correct plugin id', function (): void {
    $plugin = FilamentAddressingPlugin::make();

    expect($plugin->getId())->toBe('filament-addressing');
});

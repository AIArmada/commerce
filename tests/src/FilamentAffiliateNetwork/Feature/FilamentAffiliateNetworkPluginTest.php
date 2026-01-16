<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliateNetwork\FilamentAffiliateNetworkPlugin;

describe('FilamentAffiliateNetworkPlugin', function (): void {
    test('can be instantiated', function (): void {
        $plugin = FilamentAffiliateNetworkPlugin::make();

        expect($plugin)->toBeInstanceOf(FilamentAffiliateNetworkPlugin::class);
    });

    test('has correct ID', function (): void {
        $plugin = FilamentAffiliateNetworkPlugin::make();

        expect($plugin->getId())->toBe('filament-affiliate-network');
    });

    test('can be retrieved from container', function (): void {
        $plugin = app(FilamentAffiliateNetworkPlugin::class);

        expect($plugin)->toBeInstanceOf(FilamentAffiliateNetworkPlugin::class);
    });

    test('is registered as singleton', function (): void {
        $plugin1 = app(FilamentAffiliateNetworkPlugin::class);
        $plugin2 = app(FilamentAffiliateNetworkPlugin::class);

        expect($plugin1)->toBe($plugin2);
    });
});

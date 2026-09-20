<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliateNetwork\FilamentAffiliateNetworkPlugin;

describe('FilamentAffiliateNetworkPlugin', function (): void {
    test('has correct ID', function (): void {
        $plugin = FilamentAffiliateNetworkPlugin::make();

        expect($plugin->getId())->toBe('filament-affiliate-network');
    });
});

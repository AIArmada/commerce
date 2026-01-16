<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliateNetwork\FilamentAffiliateNetworkServiceProvider;
use Spatie\LaravelPackageTools\PackageServiceProvider;

describe('FilamentAffiliateNetworkServiceProvider', function (): void {
    test('is instance of PackageServiceProvider', function (): void {
        $provider = app()->getProvider(FilamentAffiliateNetworkServiceProvider::class);

        expect($provider)->toBeInstanceOf(PackageServiceProvider::class);
    });

    test('service provider is registered', function (): void {
        expect(app()->providerIsLoaded(FilamentAffiliateNetworkServiceProvider::class))->toBeTrue();
    });

    test('config is published', function (): void {
        expect(config('filament-affiliate-network'))->not->toBeNull();
    });

    test('config has navigation group', function (): void {
        expect(config('filament-affiliate-network.navigation.group'))->not->toBeNull();
    });
});

<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentProducts\FilamentProductsServiceProvider;

uses(TestCase::class);

it('boots the filament products service provider', function (): void {
    $provider = new FilamentProductsServiceProvider(app());

    $provider->register();
    $provider->boot();

    expect(true)->toBeTrue();
});

it('loads the published filament products configuration', function (): void {
    app()->register(FilamentProductsServiceProvider::class);

    expect(config('filament-products.navigation.group'))->toBe('Catalog')
        ->and(config('filament-products.navigation.resources.products'))->toBe(1)
        ->and(config('filament-products.features.collections'))->toBeTrue();
});

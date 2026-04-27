<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentCart\Resources\CartResource\Tables\CartsTable;
use Filament\Support\Icons\Heroicon;

test('cart resource navigation uses configuration', function (): void {
    config([
        'filament-cart.navigation_group' => 'Operations',
        'filament-cart.resources.navigation_sort.carts' => 42,
    ]);

    expect(CartResource::getNavigationGroup())->toBe('Operations');
    expect(CartResource::getNavigationSort())->toBe(42);
    expect(CartResource::getNavigationIcon())->toBe(Heroicon::OutlinedShoppingCart);
    expect(CartResource::canCreate())->toBeFalse();
    expect(array_keys(CartResource::getPages()))->toBe(['index', 'view']);
});

test('cart table polling interval uses the configured suffix correctly', function (): void {
    config(['filament-cart.polling_interval' => '30s']);
    expect(CartsTable::resolvePollingInterval())->toBe('30s');

    config(['filament-cart.polling_interval' => 45]);
    expect(CartsTable::resolvePollingInterval())->toBe('45s');
});

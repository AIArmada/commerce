<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Widgets\AbandonedCartsWidget;
use AIArmada\FilamentCart\Widgets\CartStatsOverviewWidget;
use Akaunting\Money\Money;

it('formats abandoned cart value from the snapshot subtotal column', function (): void {
    config()->set('cart.money.default_currency', 'USD');

    $cart = Cart::create([
        'identifier' => 'widget-cart',
        'instance' => 'default',
        'subtotal' => 4321,
        'metadata' => ['subtotal' => 999999],
    ]);

    $widget = new AbandonedCartsWidget;
    $method = (new ReflectionClass($widget))->getMethod('getCartValue');

    expect($method->invoke($widget, $cart))->toBe((string) Money::USD(4321));
});

it('uses the persisted subtotal column for stats overview aggregation', function (): void {
    $widget = new CartStatsOverviewWidget;
    $method = (new ReflectionClass($widget))->getMethod('getSubtotalExpression');

    expect($method->invoke($widget))->toBe('COALESCE(subtotal, 0)');
});

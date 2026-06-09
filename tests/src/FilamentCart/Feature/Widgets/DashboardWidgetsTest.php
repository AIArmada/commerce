<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Widgets\AbandonedCartsWidget;
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

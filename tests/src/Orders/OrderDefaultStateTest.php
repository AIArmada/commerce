<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\Transitions\PaymentConfirmed;

it('defaults direct creates to the Created state', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-DEF-' . uniqid(),
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    expect($order->status)->toBeInstanceOf(Created::class);
});

it('runs the payment flow from a default-created order', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-DEFPAY-' . uniqid(),
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    $order = (new PaymentConfirmed($order, 'txn_default_' . uniqid(), 'stripe', 10000))->handle();

    expect($order->status)->toBeInstanceOf(Processing::class)
        ->and($order->paid_at)->not->toBeNull();
});

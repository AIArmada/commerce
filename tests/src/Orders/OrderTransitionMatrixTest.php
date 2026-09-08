<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Completed;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\Delivered;
use AIArmada\Orders\States\Fraud;
use AIArmada\Orders\States\OnHold;
use AIArmada\Orders\States\PaymentFailed;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Refunded;
use AIArmada\Orders\States\Returned;
use AIArmada\Orders\States\Shipped;

it('allows every configured order state transition', function (string $from, string $to): void {
    $order = Order::create([
        'order_number' => 'ORD-MATRIX-' . uniqid(),
        'status' => $from,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    $order->status->transitionTo($to);

    expect($order->status)->toBeInstanceOf($to);
})->with([
    'created to pending payment' => [Created::class, PendingPayment::class],
    'created to processing' => [Created::class, Processing::class],
    'pending payment to processing' => [PendingPayment::class, Processing::class],
    'pending payment to canceled' => [PendingPayment::class, Canceled::class],
    'pending payment to payment failed' => [PendingPayment::class, PaymentFailed::class],
    'processing to on hold' => [Processing::class, OnHold::class],
    'processing to fraud' => [Processing::class, Fraud::class],
    'processing to shipped' => [Processing::class, Shipped::class],
    'processing to completed' => [Processing::class, Completed::class],
    'processing to canceled' => [Processing::class, Canceled::class],
    'processing to refunded' => [Processing::class, Refunded::class],
    'on hold to processing' => [OnHold::class, Processing::class],
    'on hold to canceled' => [OnHold::class, Canceled::class],
    'shipped to delivered' => [Shipped::class, Delivered::class],
    'shipped to returned' => [Shipped::class, Returned::class],
    'delivered to completed' => [Delivered::class, Completed::class],
    'delivered to returned' => [Delivered::class, Returned::class],
    'delivered to refunded' => [Delivered::class, Refunded::class],
    'completed to refunded' => [Completed::class, Refunded::class],
    'canceled to refunded' => [Canceled::class, Refunded::class],
    'returned to refunded' => [Returned::class, Refunded::class],
]);

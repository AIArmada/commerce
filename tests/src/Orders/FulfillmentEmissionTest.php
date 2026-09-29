<?php

declare(strict_types=1);

use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Orders\Listeners\AttributeCommissionOnFulfillment;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\OrdersServiceProvider;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\Transitions\PaymentConfirmed;
use Illuminate\Support\Facades\Event;

it('schedules fulfillment dispatch when payment confirms', function (): void {
    $order = new Order;
    $order->forceFill([
        'order_number' => 'ORD-FULFILL-001',
        'status' => PendingPayment::class,
        'currency' => 'USD',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);
    $order->save();

    Event::fake([OrderFulfillmentRequired::class]);

    $invokeCommitHooks = captureAfterCommitHooks();

    $result = (new PaymentConfirmed($order, 'txn-fulfill-1', 'stripe', 10000))->handle();

    expect($result->status)->toBeInstanceOf(Processing::class);

    $invokeCommitHooks();

    Event::assertDispatched(
        OrderFulfillmentRequired::class,
        fn (OrderFulfillmentRequired $event): bool => $event->order->is($order)
            && $event->transactionId === 'txn-fulfill-1'
            && $event->gateway === 'stripe'
    );
});

it('does not schedule fulfillment for duplicate payment delivery', function (): void {
    $order = new Order;
    $order->forceFill([
        'order_number' => 'ORD-FULFILL-002',
        'status' => PendingPayment::class,
        'currency' => 'USD',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);
    $order->save();

    (new PaymentConfirmed($order, 'txn-fulfill-2', 'stripe', 10000))->handle();

    Event::fake([OrderFulfillmentRequired::class]);

    $invokeCommitHooks = captureAfterCommitHooks();

    (new PaymentConfirmed($order->fresh(), 'txn-fulfill-2', 'stripe', 10000))->handle();

    $invokeCommitHooks();

    Event::assertNotDispatched(OrderFulfillmentRequired::class);
});

it('attributes commission on fulfillment when the integration is enabled', function (): void {
    config()->set('orders.integrations.affiliates.enabled', true);

    $order = new Order;
    $order->forceFill([
        'order_number' => 'ORD-FULFILL-003',
        'status' => Processing::class,
        'currency' => 'USD',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);
    $order->save();

    Event::fake([CommissionAttributionRequired::class]);

    app(AttributeCommissionOnFulfillment::class)->handle(
        new OrderFulfillmentRequired($order, 'txn-fulfill-3', 'stripe')
    );

    Event::assertDispatched(
        CommissionAttributionRequired::class,
        fn (CommissionAttributionRequired $event): bool => $event->order->is($order)
    );
});

it('skips attribution on fulfillment when the integration is disabled', function (): void {
    config()->set('orders.integrations.affiliates.enabled', false);

    $order = new Order;
    $order->forceFill([
        'order_number' => 'ORD-FULFILL-004',
        'status' => Processing::class,
        'currency' => 'USD',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);
    $order->save();

    Event::fake([CommissionAttributionRequired::class]);

    app(AttributeCommissionOnFulfillment::class)->handle(
        new OrderFulfillmentRequired($order, 'txn-fulfill-4', 'stripe')
    );

    Event::assertNotDispatched(CommissionAttributionRequired::class);
});

it('registers commission attribution on the fulfillment event', function (): void {
    // OrdersServiceProvider is not auto-loaded by the suite TestCase.
    app()->register(OrdersServiceProvider::class);

    expect(Event::getRawListeners()[OrderFulfillmentRequired::class] ?? [])
        ->toContain(AttributeCommissionOnFulfillment::class);
});

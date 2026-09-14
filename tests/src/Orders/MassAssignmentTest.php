<?php

declare(strict_types=1);

use AIArmada\Orders\Enums\OrderItemStatus;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Models\OrderRefund;
use AIArmada\Orders\States\Created;
use Carbon\CarbonImmutable;

it('ignores caller-supplied owner fields on orders', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-OWN-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'owner_type' => 'forged-type',
        'owner_id' => 'forged-id',
    ]);

    expect($order->owner_type)->not->toBe('forged-type')
        ->and($order->owner_id)->not->toBe('forged-id');
});

it('ignores caller-supplied lifecycle timestamps on payments', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-PAYTS-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
    ]);

    $payment = OrderPayment::create([
        'order_id' => $order->id,
        'gateway' => 'stripe',
        'transaction_id' => 'txn_' . uniqid(),
        'amount' => 1000,
        'currency' => 'MYR',
        'status' => PaymentStatus::Completed,
        'failed_at' => CarbonImmutable::now(),
        'refunded_at' => CarbonImmutable::now(),
    ]);

    expect($payment->failed_at)->toBeNull()
        ->and($payment->refunded_at)->toBeNull();
});

it('ignores caller-supplied lifecycle timestamps on refunds', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-REF-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
    ]);

    $refund = OrderRefund::create([
        'order_id' => $order->id,
        'gateway' => 'stripe',
        'transaction_id' => 'txn_' . uniqid(),
        'amount' => 1000,
        'currency' => 'MYR',
        'status' => RefundStatus::Pending,
        'reason' => 'test',
        'refunded_at' => CarbonImmutable::now(),
        'failed_at' => CarbonImmutable::now(),
        'provider_submission_started_at' => CarbonImmutable::now(),
    ]);

    expect($refund->refunded_at)->toBeNull()
        ->and($refund->failed_at)->toBeNull()
        ->and($refund->provider_submission_started_at)->toBeNull();
});

it('ignores caller-supplied lifecycle fields on items', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-ITEM-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'name' => 'Widget',
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'MYR',
        'status' => 'shipped',
        'shipped_at' => CarbonImmutable::now(),
        'delivered_at' => CarbonImmutable::now(),
        'returned_at' => CarbonImmutable::now(),
        'canceled_at' => CarbonImmutable::now(),
    ]);

    expect($item->status)->toBe(OrderItemStatus::Active)
        ->and($item->shipped_at)->toBeNull()
        ->and($item->delivered_at)->toBeNull()
        ->and($item->returned_at)->toBeNull()
        ->and($item->canceled_at)->toBeNull();
});

it('still transitions payment and refund lifecycles through model methods', function (): void {
    $order = Order::create([
        'order_number' => 'ORD-LIFE-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
    ]);

    $payment = OrderPayment::create([
        'order_id' => $order->id,
        'gateway' => 'stripe',
        'transaction_id' => 'txn_' . uniqid(),
        'amount' => 1000,
        'currency' => 'MYR',
        'status' => PaymentStatus::Pending,
    ]);
    $payment->markAsFailed('declined');

    expect($payment->refresh()->failed_at)->not->toBeNull();

    $refund = OrderRefund::create([
        'order_id' => $order->id,
        'gateway' => 'stripe',
        'transaction_id' => 'txn_' . uniqid(),
        'amount' => 1000,
        'currency' => 'MYR',
        'status' => RefundStatus::Pending,
        'reason' => 'test',
    ]);
    $refund->markAsCompleted();

    expect($refund->refresh()->refunded_at)->not->toBeNull();
});

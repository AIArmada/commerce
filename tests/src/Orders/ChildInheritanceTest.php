<?php

declare(strict_types=1);

use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Models\OrderNote;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Models\OrderRefund;
use AIArmada\Orders\States\Created;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);

    $this->order = Order::create([
        'order_number' => 'ORD-CHILD-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
    ]);
});

it('resolves the parent and inherits its owner when scoping is off', function (): void {
    $item = OrderItem::create([
        'order_id' => $this->order->id,
        'name' => 'Widget',
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'MYR',
    ]);

    expect($item->owner_type)->toBe($this->order->owner_type)
        ->and($item->owner_id)->toBe($this->order->owner_id);

    $payment = OrderPayment::create([
        'order_id' => $this->order->id,
        'gateway' => 'stripe',
        'amount' => 1000,
        'currency' => 'MYR',
        'status' => PaymentStatus::Pending,
    ]);

    expect($payment->owner_type)->toBe($this->order->owner_type)
        ->and($payment->owner_id)->toBe($this->order->owner_id);
});

it('rejects unknown parents when scoping is off', function (): void {
    expect(fn (): OrderItem => OrderItem::create([
        'order_id' => (string) Str::orderedUuid(),
        'name' => 'Widget',
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'MYR',
    ]))->toThrow(ModelNotFoundException::class);

    expect(fn (): OrderRefund => OrderRefund::create([
        'order_id' => (string) Str::orderedUuid(),
        'gateway' => 'stripe',
        'amount' => 1000,
        'currency' => 'MYR',
        'status' => RefundStatus::Pending,
        'reason' => 'test',
    ]))->toThrow(ModelNotFoundException::class);
});

it('still requires an order id when scoping is off', function (): void {
    expect(fn (): OrderNote => OrderNote::create([
        'order_id' => null,
        'content' => 'note without an order',
    ]))->toThrow(InvalidArgumentException::class, 'order_id is required');
});

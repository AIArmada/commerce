<?php

declare(strict_types=1);

use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Models\OrderNote;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Models\OrderRefund;
use AIArmada\Orders\States\Created;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/*
 * ORD-01 regression: parent reference integrity still holds when owner
 * scoping is disabled. Existence validation must fail loud in that mode.
 */

beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
});

function cpiuOrder(): Order
{
    return Order::query()->create([
        'order_number' => 'ORD-CPIU-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);
}

function cpiuItem(Order $order): OrderItem
{
    return $order->items()->create([
        'name' => 'Test item',
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => $order->currency,
    ]);
}

function cpiuNote(Order $order): OrderNote
{
    return $order->orderNotes()->create([
        'content' => 'Test note',
        'visibility' => 'internal',
    ]);
}

function cpiuPayment(Order $order, int $amount = 1000): OrderPayment
{
    return $order->payments()->create([
        'gateway' => 'stripe',
        'amount' => $amount,
        'currency' => $order->currency,
    ]);
}

it('keeps every persisted child parent reference immutable without owner scoping', function (): void {
    $orderA = cpiuOrder();
    $orderB = cpiuOrder();

    $children = [
        cpiuItem($orderA),
        cpiuNote($orderA),
        cpiuPayment($orderA),
        $orderA->refunds()->create([
            'gateway' => 'stripe',
            'amount' => 500,
            'currency' => $orderA->currency,
            'reason' => 'Test refund',
        ]),
    ];

    foreach ($children as $child) {
        $child->order_id = $orderB->id;

        expect(fn () => $child->save())
            ->toThrow(InvalidArgumentException::class, 'immutable after creation');

        expect($child->refresh()->order_id)->toBe($orderA->id);

        expect(fn () => $child->update(['order_id' => $orderB->id]))
            ->toThrow(InvalidArgumentException::class, 'immutable after creation');

        expect($child->refresh()->order_id)->toBe($orderA->id);
    }
});

it('fails child creation when the referenced parent order does not exist', function (): void {
    $missing = (string) Str::orderedUuid();

    expect(fn () => OrderItem::query()->create([
        'order_id' => $missing,
        'name' => 'Orphan item',
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'MYR',
    ]))->toThrow(ModelNotFoundException::class);

    expect(fn () => OrderNote::query()->create([
        'order_id' => $missing,
        'content' => 'Orphan note',
    ]))->toThrow(ModelNotFoundException::class);

    expect(fn () => OrderPayment::query()->create([
        'order_id' => $missing,
        'gateway' => 'stripe',
        'amount' => 1000,
        'currency' => 'MYR',
    ]))->toThrow(ModelNotFoundException::class);

    expect(fn () => OrderRefund::query()->create([
        'order_id' => $missing,
        'gateway' => 'stripe',
        'amount' => 1000,
        'currency' => 'MYR',
        'reason' => 'Orphan refund',
    ]))->toThrow(ModelNotFoundException::class);
});

it('rejects a refund payment belonging to another order without owner scoping', function (): void {
    $orderA = cpiuOrder();
    $orderB = cpiuOrder();
    $paymentB = cpiuPayment($orderB);

    expect(fn () => $orderA->refunds()->create([
        'payment_id' => $paymentB->id,
        'gateway' => 'stripe',
        'amount' => 500,
        'currency' => $orderA->currency,
        'reason' => 'Cross-order refund',
    ]))->toThrow(InvalidArgumentException::class, 'same order');

    expect($orderA->refunds()->count())->toBe(0)
        ->and($orderB->refunds()->count())->toBe(0)
        ->and($orderA->refresh()->pending_refunded_total)->toBe(0)
        ->and($orderB->refresh()->pending_refunded_total)->toBe(0);
});

it('fails refund creation when the referenced payment does not exist', function (): void {
    $order = cpiuOrder();

    expect(fn () => $order->refunds()->create([
        'payment_id' => (string) Str::orderedUuid(),
        'gateway' => 'stripe',
        'amount' => 500,
        'currency' => $order->currency,
        'reason' => 'Missing payment',
    ]))->toThrow(ModelNotFoundException::class);

    expect($order->refunds()->count())->toBe(0);
});

it('still applies supported child updates without owner scoping', function (): void {
    $order = cpiuOrder();
    $payment = cpiuPayment($order, 1000);
    $payment->markAsCompleted();

    expect($order->refresh()->paid_total)->toBe(1000);

    $item = cpiuItem($order);
    $item->quantity = 3;
    $item->save();

    expect($item->refresh()->total)->toBe(3000);
});

it('keeps the refund payment association immutable without owner scoping', function (): void {
    $order = cpiuOrder();
    $firstPayment = cpiuPayment($order);
    $secondPayment = cpiuPayment($order);

    $refund = $order->refunds()->create([
        'payment_id' => $firstPayment->id,
        'gateway' => 'stripe',
        'amount' => 500,
        'currency' => $order->currency,
        'reason' => 'Test refund',
    ]);

    expect(fn () => $refund->update(['payment_id' => $secondPayment->id]))
        ->toThrow(InvalidArgumentException::class, 'immutable after creation');

    expect($refund->refresh()->payment_id)->toBe($firstPayment->id);
});

it('preserves payment status transitions without owner scoping', function (): void {
    $order = cpiuOrder();
    $payment = cpiuPayment($order, 1000);

    $payment->markAsCompleted();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Completed)
        ->and($order->refresh()->paid_total)->toBe(1000);
});

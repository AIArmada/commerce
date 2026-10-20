<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Models\OrderNote;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Models\OrderRefund;
use AIArmada\Orders\States\Created;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

/*
 * ORD-01 regression: persisted order-child parent references are immutable
 * and validated. This file runs under the suite default owner-enabled
 * environment with the ambient "Default Owner" (see tests/src/TestCase.php).
 */

function cpiOrder(): Order
{
    return Order::query()->create([
        'order_number' => 'ORD-CPI-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);
}

function cpiItem(Order $order): OrderItem
{
    return $order->items()->create([
        'name' => 'Test item',
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => $order->currency,
    ]);
}

function cpiNote(Order $order): OrderNote
{
    return $order->orderNotes()->create([
        'content' => 'Test note',
        'visibility' => 'internal',
    ]);
}

function cpiPayment(Order $order, int $amount = 1000, PaymentStatus $status = PaymentStatus::Pending): OrderPayment
{
    return $order->payments()->create([
        'gateway' => 'stripe',
        'amount' => $amount,
        'currency' => $order->currency,
        'status' => $status,
    ]);
}

function cpiRefund(Order $order, ?OrderPayment $payment, int $amount = 500, RefundStatus $status = RefundStatus::Pending): OrderRefund
{
    return $order->refunds()->create([
        'payment_id' => $payment?->getKey(),
        'gateway' => $payment?->gateway ?? 'manual',
        'amount' => $amount,
        'currency' => $order->currency,
        'status' => $status,
        'reason' => 'Test refund',
    ]);
}

function cpiForeignOwner(): User
{
    return User::query()->create([
        'name' => 'Other Owner',
        'email' => 'other-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
}

it('keeps every persisted child parent reference immutable for same-owner moves', function (): void {
    $orderA = cpiOrder();
    $orderB = cpiOrder();

    $children = [
        cpiItem($orderA),
        cpiNote($orderA),
        cpiPayment($orderA),
        cpiRefund($orderA, null),
    ];

    foreach ($children as $child) {
        $child->order_id = $orderB->id;

        expect(fn () => $child->save())
            ->toThrow(InvalidArgumentException::class, 'immutable after creation');

        expect($child->refresh()->order_id)->toBe($orderA->id);
    }

    expect($orderA->items()->count())->toBe(1)
        ->and($orderA->orderNotes()->count())->toBe(1)
        ->and($orderA->payments()->count())->toBe(1)
        ->and($orderA->refunds()->count())->toBe(1)
        ->and($orderB->items()->count())->toBe(0)
        ->and($orderB->orderNotes()->count())->toBe(0)
        ->and($orderB->payments()->count())->toBe(0)
        ->and($orderB->refunds()->count())->toBe(0);
});

it('keeps every persisted child parent reference immutable for cross-owner moves', function (): void {
    $orderA = cpiOrder();
    $otherOwner = cpiForeignOwner();
    $foreignOrder = OwnerContext::withOwner($otherOwner, fn (): Order => cpiOrder());

    $children = [
        cpiItem($orderA),
        cpiNote($orderA),
        cpiPayment($orderA),
        cpiRefund($orderA, null),
    ];

    foreach ($children as $child) {
        $child->order_id = $foreignOrder->id;

        expect(fn () => $child->save())
            ->toThrow(InvalidArgumentException::class, 'immutable after creation');

        expect($child->refresh()->order_id)->toBe($orderA->id);
    }
});

it('keeps the parent reference immutable through model mass assignment', function (): void {
    $orderA = cpiOrder();
    $orderB = cpiOrder();
    $item = cpiItem($orderA);

    expect(fn () => $item->update(['order_id' => $orderB->id]))
        ->toThrow(InvalidArgumentException::class, 'immutable after creation');

    expect($item->refresh()->order_id)->toBe($orderA->id)
        ->and($orderA->items()->count())->toBe(1)
        ->and($orderB->items()->count())->toBe(0);
});

it('keeps the refund payment association immutable once persisted', function (): void {
    $order = cpiOrder();
    $firstPayment = cpiPayment($order, 1000, PaymentStatus::Completed);
    $secondPayment = cpiPayment($order, 1000, PaymentStatus::Completed);
    $refund = cpiRefund($order, $firstPayment, 500);

    expect(fn () => $refund->update(['payment_id' => $secondPayment->id]))
        ->toThrow(InvalidArgumentException::class, 'immutable after creation');

    expect($refund->refresh()->payment_id)->toBe($firstPayment->id);
});

it('rejects a refund payment belonging to another order of the same owner', function (): void {
    $orderA = cpiOrder();
    $orderB = cpiOrder();
    $paymentB = cpiPayment($orderB, 1000);

    expect(fn () => cpiRefund($orderA, $paymentB))
        ->toThrow(InvalidArgumentException::class, 'same order');

    expect($orderA->refunds()->count())->toBe(0)
        ->and($orderB->refunds()->count())->toBe(0)
        ->and($orderA->refresh()->pending_refunded_total)->toBe(0)
        ->and($orderB->refresh()->pending_refunded_total)->toBe(0);
});

it('rejects a refund payment belonging to another owner', function (): void {
    $order = cpiOrder();
    $otherOwner = cpiForeignOwner();

    $foreignPayment = OwnerContext::withOwner($otherOwner, function (): OrderPayment {
        return cpiPayment(cpiOrder(), 1000);
    });

    expect(fn () => cpiRefund($order, $foreignPayment))
        ->toThrow(AuthorizationException::class);

    expect($order->refunds()->count())->toBe(0)
        ->and($order->refresh()->pending_refunded_total)->toBe(0);
});

it('fails child creation when the referenced parent is missing or inaccessible', function (): void {
    $missing = (string) Str::orderedUuid();

    expect(fn () => OrderItem::query()->create([
        'order_id' => $missing,
        'name' => 'Orphan item',
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'MYR',
    ]))->toThrow(AuthorizationException::class);

    expect(fn () => OrderNote::query()->create([
        'order_id' => $missing,
        'content' => 'Orphan note',
    ]))->toThrow(AuthorizationException::class);

    expect(fn () => OrderPayment::query()->create([
        'order_id' => $missing,
        'gateway' => 'stripe',
        'amount' => 1000,
        'currency' => 'MYR',
    ]))->toThrow(AuthorizationException::class);

    expect(fn () => OrderRefund::query()->create([
        'order_id' => $missing,
        'gateway' => 'stripe',
        'amount' => 1000,
        'currency' => 'MYR',
        'reason' => 'Orphan refund',
    ]))->toThrow(AuthorizationException::class);

    $order = cpiOrder();

    expect(fn () => $order->refunds()->create([
        'payment_id' => (string) Str::orderedUuid(),
        'gateway' => 'stripe',
        'amount' => 500,
        'currency' => $order->currency,
        'reason' => 'Missing payment',
    ]))->toThrow(AuthorizationException::class);

    expect($order->refunds()->count())->toBe(0);
});

it('leaves cached totals on both orders untouched when a child move is rejected', function (): void {
    $orderA = cpiOrder();
    $orderB = cpiOrder();

    $payment = cpiPayment($orderA, 1000, PaymentStatus::Completed);
    $refund = cpiRefund($orderA, $payment, 300);

    expect($orderA->refresh()->paid_total)->toBe(1000)
        ->and($orderA->refresh()->pending_refunded_total)->toBe(300);

    foreach ([$payment, $refund] as $child) {
        $child->order_id = $orderB->id;

        expect(fn () => $child->save())
            ->toThrow(InvalidArgumentException::class, 'immutable after creation');
    }

    expect($orderA->refresh()->paid_total)->toBe(1000)
        ->and($orderA->refresh()->pending_refunded_total)->toBe(300)
        ->and($orderA->refresh()->refunded_total)->toBe(0)
        ->and($orderB->refresh()->paid_total)->toBe(0)
        ->and($orderB->refresh()->pending_refunded_total)->toBe(0)
        ->and($orderB->refresh()->refunded_total)->toBe(0);
});

it('still creates child rows and applies supported updates', function (): void {
    $order = cpiOrder();
    $payment = cpiPayment($order, 1000, PaymentStatus::Completed);
    $refund = cpiRefund($order, $payment, 300);

    expect($order->refresh()->paid_total)->toBe(1000)
        ->and($order->refresh()->pending_refunded_total)->toBe(300);

    $item = cpiItem($order);
    $item->quantity = 2;
    $item->save();

    expect($item->refresh()->total)->toBe(2000);

    $note = cpiNote($order);
    $note->content = 'Updated note';
    $note->save();

    expect($note->refresh()->content)->toBe('Updated note');

    $payment->amount = 1200;
    $payment->save();

    expect($order->refresh()->paid_total)->toBe(1200);

    $refund->amount = 400;
    $refund->save();

    expect($order->refresh()->pending_refunded_total)->toBe(400);

    $refund->markAsCompleted();

    expect($order->refresh()->pending_refunded_total)->toBe(0)
        ->and($order->refresh()->refunded_total)->toBe(400)
        ->and($refund->refresh()->payment_id)->toBe($payment->id);
});

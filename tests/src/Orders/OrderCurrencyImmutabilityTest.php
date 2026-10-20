<?php

declare(strict_types=1);

use AIArmada\Orders\Actions\RegisterOrderPayment;
use AIArmada\Orders\Actions\RegisterOrderRefund;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Events\OrderRefunded;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Refunded;
use Illuminate\Support\Facades\Event;

/**
 * ORD-08 regressions: the order accounting currency is fixed when the order
 * is created. A parent edit must never re-denominate money already received,
 * reserved for refund, or still pending with a gateway, so changing it is
 * rejected on every model write path after the initial insert.
 */
beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('orders.owner.auto_assign_on_create', false);
});

function ord08Order(string $currency = 'MYR', int $grandTotal = 10000): Order
{
    return Order::create([
        'order_number' => 'ORD-ORD08-' . mb_strtoupper(uniqid()),
        'status' => Processing::class,
        'currency' => $currency,
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
    ])->refresh();
}

function ord08PayableOrder(int $grandTotal = 10000, string $currency = 'MYR'): Order
{
    $order = Order::create([
        'order_number' => 'ORD-ORD08-' . mb_strtoupper(uniqid()),
        'status' => Created::class,
        'currency' => $currency,
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
    ]);

    $order->status->transitionTo(PendingPayment::class);

    return $order->refresh();
}

function ord08PendingPayment(Order $order, string $transactionId, int $amount): OrderPayment
{
    return OrderPayment::create([
        'order_id' => $order->getKey(),
        'gateway' => 'chip',
        'transaction_id' => $transactionId,
        'amount' => $amount,
        'currency' => 'MYR',
        'status' => PaymentStatus::Pending,
    ])->refresh();
}

it('normalizes the accounting currency when the order is created', function (): void {
    $lowercase = Order::create([
        'order_number' => 'ORD-ORD08-' . mb_strtoupper(uniqid()),
        'status' => Created::class,
        'currency' => 'myr',
        'grand_total' => 10000,
    ]);

    $alias = Order::create([
        'order_number' => 'ORD-ORD08-' . mb_strtoupper(uniqid()),
        'status' => Created::class,
        'currency' => 'RM',
        'grand_total' => 10000,
    ]);

    expect($lowercase->refresh()->currency)->toBe('MYR')
        ->and($alias->refresh()->currency)->toBe('MYR');
});

it('accepts an equivalent normalized currency code as a no-op edit', function (): void {
    $order = ord08Order();

    $order->update(['currency' => 'myr']);

    expect($order->refresh()->currency)->toBe('MYR');

    $order->update(['currency' => 'RM']);

    expect($order->refresh()->currency)->toBe('MYR');
});

it('does not rewrite the persisted currency when saving a partially selected order', function (): void {
    $order = ord08Order('USD');

    $partial = Order::query()->select('id', 'grand_total')->findOrFail($order->getKey());

    $partial->grand_total = 12345;
    $partial->save();

    $order->refresh();

    expect($order->currency)->toBe('USD')
        ->and((int) $order->grand_total)->toBe(12345);
});

it('fails closed when a partially selected order assigns a currency', function (): void {
    $order = ord08Order('USD');

    $partial = Order::query()->select('id', 'grand_total')->findOrFail($order->getKey());

    expect(function () use ($partial): void {
        $partial->currency = 'USD';
        $partial->save();
    })->toThrow(InvalidArgumentException::class, 'currency is immutable after creation');

    expect($order->refresh()->currency)->toBe('USD');
});

it('rejects changing the accounting currency of an order with no children yet', function (): void {
    $order = ord08Order();
    $updatedAt = $order->updated_at;

    expect(fn (): bool => $order->update(['currency' => 'USD']))
        ->toThrow(InvalidArgumentException::class, 'currency is immutable after creation');

    $order->refresh();

    expect($order->currency)->toBe('MYR')
        ->and($order->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and($order->getRawOriginal('currency'))->toBe('MYR');
});

it('rejects a currency edit after a completed receipt and leaves totals, timestamps, and events untouched', function (): void {
    $order = ord08PayableOrder();
    app(RegisterOrderPayment::class)->execute($order, 'ord08-completed', 'stripe', 10000);

    $order->refresh();

    expect($order->status)->toBeInstanceOf(Processing::class)
        ->and($order->paid_at)->not->toBeNull();

    $paidAt = $order->paid_at;
    $payment = $order->payments()->sole();

    Event::fake([OrderPaid::class]);

    expect(fn (): bool => $order->update(['currency' => 'USD']))
        ->toThrow(InvalidArgumentException::class, 'currency is immutable after creation');

    Event::assertNotDispatched(OrderPaid::class);

    $order->refresh();
    $payment->refresh();

    expect($order->currency)->toBe('MYR')
        ->and($order->getTotalPaid())->toBe(10000)
        ->and($order->isFullyPaid())->toBeTrue()
        ->and($order->paid_at->equalTo($paidAt))->toBeTrue()
        ->and($order->status)->toBeInstanceOf(Processing::class)
        ->and($payment->currency)->toBe('MYR')
        ->and($payment->status)->toBe(PaymentStatus::Completed);
});

it('rejects a currency edit after a partial receipt and leaves the balance and receipt intact', function (): void {
    $order = ord08PayableOrder();
    app(RegisterOrderPayment::class)->execute($order, 'ord08-partial', 'stripe', 6000);

    $order->refresh();

    $payment = $order->payments()->sole();
    $paymentPaidAt = $payment->paid_at;

    expect($order->paid_at)->toBeNull();

    expect(fn (): bool => $order->update(['currency' => 'USD']))
        ->toThrow(InvalidArgumentException::class, 'currency is immutable after creation');

    $order->refresh();
    $payment->refresh();

    expect($order->currency)->toBe('MYR')
        ->and($order->getTotalPaid())->toBe(6000)
        ->and($order->getBalanceDue())->toBe(4000)
        ->and($order->paid_at)->toBeNull()
        ->and($order->status)->toBeInstanceOf(PendingPayment::class)
        ->and($payment->currency)->toBe('MYR')
        ->and($payment->paid_at->equalTo($paymentPaidAt))->toBeTrue();
});

it('rejects a currency edit on a refunded order and leaves the settled refund intact', function (): void {
    $order = ord08PayableOrder();
    app(RegisterOrderPayment::class)->execute($order, 'ord08-refund-pay', 'stripe', 10000);

    $order->refresh();

    app(RegisterOrderRefund::class)->execute($order, 10000, 'ord08-refund-provider', 'Full return');

    $order->refresh();

    expect($order->status)->toBeInstanceOf(Refunded::class)
        ->and($order->refunded_at)->not->toBeNull()
        ->and($order->getTotalRefunded())->toBe(10000);

    $refundedAt = $order->refunded_at;
    $refund = $order->refunds()->sole();

    Event::fake([OrderRefunded::class]);

    expect(fn (): bool => $order->update(['currency' => 'USD']))
        ->toThrow(InvalidArgumentException::class, 'currency is immutable after creation');

    Event::assertNotDispatched(OrderRefunded::class);

    $order->refresh();
    $refund->refresh();

    expect($order->currency)->toBe('MYR')
        ->and($order->getTotalRefunded())->toBe(10000)
        ->and($order->getRemainingRefundable())->toBe(0)
        ->and($order->refunded_at->equalTo($refundedAt))->toBeTrue()
        ->and($order->status)->toBeInstanceOf(Refunded::class)
        ->and($refund->currency)->toBe('MYR')
        ->and($refund->status)->toBe(RefundStatus::Completed);
});

it('rejects a currency edit while a refund reservation is still pending', function (): void {
    $order = ord08PayableOrder();
    app(RegisterOrderPayment::class)->execute($order, 'ord08-pending-pay', 'stripe', 10000);

    $order->refresh();

    $reservation = app(RegisterOrderRefund::class)->createPending(
        $order,
        3000,
        'ord08-pending-refund',
        'Partial return',
    )[0];

    $order->refresh();

    expect($order->getTotalPendingRefunded())->toBe(3000);

    expect(fn (): bool => $order->update(['currency' => 'USD']))
        ->toThrow(InvalidArgumentException::class, 'currency is immutable after creation');

    $order->refresh();
    $reservation->refresh();

    expect($order->currency)->toBe('MYR')
        ->and($order->getTotalPendingRefunded())->toBe(3000)
        ->and($order->getTotalRefunded())->toBe(0)
        ->and($order->getRemainingRefundable())->toBe(7000)
        ->and($order->refunds()->count())->toBe(1)
        ->and($reservation->currency)->toBe('MYR')
        ->and($reservation->status)->toBe(RefundStatus::Pending);
});

it('rejects a currency edit while a payment row is still pending', function (): void {
    $order = ord08Order();
    $payment = ord08PendingPayment($order, 'ord08-pending-attempt', 10000);

    expect(fn (): bool => $order->update(['currency' => 'USD']))
        ->toThrow(InvalidArgumentException::class, 'currency is immutable after creation');

    $order->refresh();
    $payment->refresh();

    expect($order->currency)->toBe('MYR')
        ->and($order->getTotalPaid())->toBe(0)
        ->and($payment->currency)->toBe('MYR')
        ->and($payment->status)->toBe(PaymentStatus::Pending);
});

it('rejects direct currency writes through update, save, fill, and forceFill', function (): void {
    $order = ord08Order();

    expect(fn (): bool => $order->update(['currency' => 'USD']))
        ->toThrow(InvalidArgumentException::class, 'currency is immutable after creation');

    expect($order->refresh()->currency)->toBe('MYR');

    expect(function () use ($order): void {
        $order->currency = 'USD';
        $order->save();
    })->toThrow(InvalidArgumentException::class, 'currency is immutable after creation');

    expect($order->refresh()->currency)->toBe('MYR');

    expect(function () use ($order): void {
        $order->fill(['currency' => 'USD'])->save();
    })->toThrow(InvalidArgumentException::class, 'currency is immutable after creation');

    expect($order->refresh()->currency)->toBe('MYR');

    expect(function () use ($order): void {
        $order->forceFill(['currency' => 'USD'])->save();
    })->toThrow(InvalidArgumentException::class, 'currency is immutable after creation');

    expect($order->refresh()->currency)->toBe('MYR')
        ->and($order->getRawOriginal('currency'))->toBe('MYR');
});

it('leaves existing items and their currency untouched when a parent edit is rejected', function (): void {
    $order = ord08Order();

    $item = OrderItem::create([
        'order_id' => $order->getKey(),
        'name' => 'Widget',
        'quantity' => 1,
        'unit_price' => 5000,
        'currency' => 'MYR',
    ])->refresh();

    expect(fn (): bool => $order->update(['currency' => 'USD']))
        ->toThrow(InvalidArgumentException::class, 'currency is immutable after creation');

    $order->refresh();
    $item->refresh();

    expect($order->currency)->toBe('MYR')
        ->and($item->currency)->toBe('MYR')
        ->and($item->total)->toBe(5000);
});

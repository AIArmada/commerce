<?php

declare(strict_types=1);

use AIArmada\Orders\Actions\RegisterOrderPayment;
use AIArmada\Orders\Actions\RegisterOrderRefund;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\Transitions\OrderCanceled;

function makeCancelableOrder(int $grandTotal = 10000): Order
{
    $order = Order::create([
        'order_number' => 'ORD-CANCEL-' . uniqid(),
        'status' => PendingPayment::class,
        'currency' => 'MYR',
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
    ]);

    return $order->refresh();
}

function cancelReservationIdentity(Order $order, string $paymentId): string
{
    return 'cancel:' . $order->getKey() . ':' . $paymentId;
}

/**
 * Settle a refund directly against one payment so attribution is explicit
 * (the ordinary pending-refund path attributes to the first payment).
 */
function settleRefundForPayment(Order $order, OrderPayment $payment, int $amount): void
{
    $order->refunds()->create([
        'payment_id' => $payment->getKey(),
        'gateway' => $payment->gateway,
        'amount' => $amount,
        'currency' => $order->currency,
        'status' => RefundStatus::Completed,
        'reason' => 'Settled refund',
    ]);
}

it('reserves only the remaining refundable balance when cancelling after a settled refund', function (): void {
    $order = makeCancelableOrder(10000);
    $refundAction = app(RegisterOrderRefund::class);

    app(RegisterOrderPayment::class)->execute($order, 'txn_cancel_settled_pay', 'stripe', 10000);
    $order->refresh();

    $settled = $refundAction->createPending($order, 3000, 'txn_cancel_settled_refund', 'Partial return');
    $refundAction->completePending($settled[0], 'txn_cancel_settled_refund');

    $order->refresh();

    expect($order->getTotalRefunded())->toBe(3000)
        ->and($order->getRemainingRefundable())->toBe(7000);

    (new OrderCanceled($order, 'Customer request'))->handle();

    $order->refresh();
    $paymentId = (string) $order->payments()->sole()->getKey();
    $reservation = $order->refunds()->where('status', RefundStatus::Pending->value)->sole();

    expect($order->status)->toBeInstanceOf(Canceled::class)
        ->and($order->refunds()->count())->toBe(2)
        ->and($reservation->amount)->toBe(7000)
        ->and($reservation->payment_id)->toBe($paymentId)
        ->and($reservation->allocation_key)->toBe(cancelReservationIdentity($order, $paymentId))
        ->and($reservation->reason)->toBe('Order canceled: Customer request')
        ->and($order->getTotalPendingRefunded())->toBe(7000)
        ->and($order->getRemainingRefundable())->toBe(0);
});

it('accounts for open pending reservations when cancelling', function (): void {
    $order = makeCancelableOrder(10000);
    $refundAction = app(RegisterOrderRefund::class);

    app(RegisterOrderPayment::class)->execute($order, 'txn_cancel_mixed_pay', 'stripe', 10000);
    $order->refresh();

    $settled = $refundAction->createPending($order, 3000, 'txn_cancel_mixed_settled', 'Partial return');
    $refundAction->completePending($settled[0], 'txn_cancel_mixed_settled');
    $refundAction->createPending($order, 2000, 'txn_cancel_mixed_open', 'Partial return');

    $order->refresh();

    expect($order->getTotalRefunded())->toBe(3000)
        ->and($order->getTotalPendingRefunded())->toBe(2000)
        ->and($order->getRemainingRefundable())->toBe(5000);

    (new OrderCanceled($order, 'Customer request'))->handle();

    $order->refresh();

    expect($order->refunds()->count())->toBe(3)
        ->and($order->getTotalRefunded())->toBe(3000)
        ->and($order->getTotalPendingRefunded())->toBe(7000)
        ->and($order->getRemainingRefundable())->toBe(0);
});

it('creates no cancellation reservation when nothing remains refundable', function (): void {
    $order = makeCancelableOrder(10000);

    app(RegisterOrderPayment::class)->execute($order, 'txn_cancel_none_pay', 'stripe', 10000);
    $order->refresh();

    app(RegisterOrderRefund::class)->createPending($order, 10000, 'txn_cancel_none_open', 'Full return');

    (new OrderCanceled($order, 'Customer request'))->handle();

    $order->refresh();

    expect($order->refunds()->count())->toBe(1)
        ->and($order->getTotalPendingRefunded())->toBe(10000)
        ->and($order->getRemainingRefundable())->toBe(0);
});

it('reserves the received money when cancelling a partially paid order', function (): void {
    $order = makeCancelableOrder(10000);

    app(RegisterOrderPayment::class)->execute($order, 'txn_cancel_partial_pay', 'stripe', 4000);

    $order->refresh();

    expect($order->status)->toBeInstanceOf(PendingPayment::class)
        ->and($order->paid_at)->toBeNull();

    (new OrderCanceled($order, 'Customer request'))->handle();

    $order->refresh();

    expect($order->getTotalPendingRefunded())->toBe(4000)
        ->and($order->getRemainingRefundable())->toBe(0)
        ->and($order->refunds()->count())->toBe(1);
});

it('allocates cancellation reservations per payment by remaining balance', function (): void {
    $order = makeCancelableOrder(10000);
    $paymentAction = app(RegisterOrderPayment::class);

    $paymentAction->execute($order, 'txn_cancel_alloc_1', 'stripe', 6000);
    $paymentAction->execute($order->refresh(), 'txn_cancel_alloc_2', 'stripe', 4000);

    $order->refresh();

    $first = $order->payments()->where('transaction_id', 'txn_cancel_alloc_1')->sole();
    $second = $order->payments()->where('transaction_id', 'txn_cancel_alloc_2')->sole();

    settleRefundForPayment($order, $first, 3000);

    (new OrderCanceled($order->refresh(), 'Customer request'))->handle();

    $order->refresh();

    $reservations = $order->refunds()
        ->where('status', RefundStatus::Pending->value)
        ->get()
        ->keyBy('payment_id');

    expect($reservations)->toHaveCount(2)
        ->and($reservations[$first->getKey()]->amount)->toBe(3000)
        ->and($reservations[$first->getKey()]->allocation_key)->toBe(cancelReservationIdentity($order, $first->getKey()))
        ->and($reservations[$second->getKey()]->amount)->toBe(4000)
        ->and($reservations[$second->getKey()]->allocation_key)->toBe(cancelReservationIdentity($order, $second->getKey()))
        ->and($order->getTotalPendingRefunded())->toBe(7000)
        ->and($order->getRemainingRefundable())->toBe(0);
});

it('skips payments whose own refundable balance is exhausted', function (): void {
    $order = makeCancelableOrder(10000);
    $paymentAction = app(RegisterOrderPayment::class);

    $paymentAction->execute($order, 'txn_cancel_drain_1', 'stripe', 6000);
    $paymentAction->execute($order->refresh(), 'txn_cancel_drain_2', 'stripe', 4000);

    $order->refresh();

    $first = $order->payments()->where('transaction_id', 'txn_cancel_drain_1')->sole();
    $second = $order->payments()->where('transaction_id', 'txn_cancel_drain_2')->sole();

    settleRefundForPayment($order, $first, 6000);

    (new OrderCanceled($order->refresh(), 'Customer request'))->handle();

    $order->refresh();
    $reservation = $order->refunds()->where('status', RefundStatus::Pending->value)->sole();

    expect($reservation->amount)->toBe(4000)
        ->and($reservation->payment_id)->toBe($second->getKey())
        ->and($order->getTotalPendingRefunded())->toBe(4000);
});

it('does not double-reserve when cancellation reservations are requested twice', function (): void {
    $order = makeCancelableOrder(10000);

    app(RegisterOrderPayment::class)->execute($order, 'txn_cancel_repeat_pay', 'stripe', 10000);

    (new OrderCanceled($order, 'Customer request'))->handle();

    $order->refresh();
    $paymentId = (string) $order->payments()->sole()->getKey();

    expect($order->refunds()->count())->toBe(1);

    $again = app(RegisterOrderRefund::class)->createCancellationReservations($order, 'Customer request');

    $order->refresh();

    expect($again)->toBeArray()->toBeEmpty()
        ->and($order->refunds()->count())->toBe(1)
        ->and($order->refunds()->sole()->allocation_key)->toBe(cancelReservationIdentity($order, $paymentId))
        ->and($order->getTotalPendingRefunded())->toBe(10000);
});

it('does not reserve against the order total fallback for a never-paid order', function (): void {
    $order = makeCancelableOrder(10000);
    $order->forceFill(['paid_at' => now()])->save();

    (new OrderCanceled($order, 'Customer request'))->handle();

    $order->refresh();

    // Refundable cash is money actually received: no payment, no
    // reservation — there is no grand-total fallback.
    expect($order->refunds()->count())->toBe(0)
        ->and($order->getTotalPendingRefunded())->toBe(0)
        ->and($order->getTotalPaid())->toBe(0);
});

it('rejects cancellation reservations for orders that are not cancelled', function (): void {
    $order = makeCancelableOrder(10000);

    app(RegisterOrderPayment::class)->execute($order, 'txn_cancel_guard_pay', 'stripe', 10000);
    $order->refresh();

    expect(fn (): array => app(RegisterOrderRefund::class)->createCancellationReservations($order, 'Customer request'))
        ->toThrow(RuntimeException::class, 'canceled');

    expect($order->refunds()->count())->toBe(0)
        ->and($order->getTotalPendingRefunded())->toBe(0);
});

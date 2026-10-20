<?php

declare(strict_types=1);

use AIArmada\Orders\Actions\CancelOrder;
use AIArmada\Orders\Actions\RegisterOrderRefund;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Events\OrderRefunded;
use AIArmada\Orders\Events\OrderRefundFailed;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Models\OrderRefund;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Refunded;
use Illuminate\Support\Facades\Event;

/**
 * ORD-04 regressions: refunds are bounded by both the order's received
 * money and each referenced payment's own remaining capacity.
 */
beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('orders.owner.auto_assign_on_create', false);
});

function ord04Order(int $grandTotal = 10000): Order
{
    return Order::create([
        'order_number' => 'ORD-ORD04-' . mb_strtoupper(uniqid()),
        'status' => Processing::class,
        'currency' => 'MYR',
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
    ])->refresh();
}

function ord04Payment(
    Order $order,
    string $transactionId,
    int $amount,
    string $gateway = 'stripe',
    string $currency = 'MYR',
): OrderPayment {
    return OrderPayment::create([
        'order_id' => $order->getKey(),
        'gateway' => $gateway,
        'transaction_id' => $transactionId,
        'amount' => $amount,
        'currency' => $currency,
        'status' => PaymentStatus::Completed,
        'paid_at' => now(),
    ])->refresh();
}

/**
 * A payment attempt that never received money. It stays pending here;
 * declined charges are covered by calling markAsFailed() in the test.
 */
function ord04UnreceivedPayment(Order $order, string $transactionId, int $amount, string $gateway = 'chip'): OrderPayment
{
    return OrderPayment::create([
        'order_id' => $order->getKey(),
        'gateway' => $gateway,
        'transaction_id' => $transactionId,
        'amount' => $amount,
        'currency' => 'MYR',
        'status' => PaymentStatus::Pending,
    ])->refresh();
}

/**
 * @param  list<OrderRefund>  $refunds
 * @return array<string, OrderRefund>
 */
function ord04ByPayment(array $refunds): array
{
    $keyed = [];

    foreach ($refunds as $refund) {
        $keyed[(string) $refund->payment_id] = $refund;
    }

    return $keyed;
}

it('splits an order-level refund across receipts instead of over-allocating one payment', function (): void {
    $order = ord04Order();
    $first = ord04Payment($order, 'ord04-split-a', 5000, 'stripe');
    $second = ord04Payment($order, 'ord04-split-b', 5000, 'chip');
    $order->refresh();

    $refunds = app(RegisterOrderRefund::class)->createPending($order, 7000, 'ord04-split-refund', 'Split refund');

    expect($refunds)->toBeArray()->toHaveCount(2);

    $byPayment = ord04ByPayment($refunds);

    expect((int) $byPayment[$first->getKey()]->amount)->toBe(5000)
        ->and((int) $byPayment[$second->getKey()]->amount)->toBe(2000)
        ->and($byPayment[$first->getKey()]->gateway)->toBe('stripe')
        ->and($byPayment[$second->getKey()]->gateway)->toBe('chip')
        ->and($byPayment[$first->getKey()]->currency)->toBe('MYR')
        ->and($byPayment[$second->getKey()]->currency)->toBe('MYR')
        ->and($byPayment[$first->getKey()]->allocation_key)->toBe('ord04-split-refund')
        ->and($byPayment[$second->getKey()]->allocation_key)->toBe('ord04-split-refund')
        ->and($byPayment[$first->getKey()]->status)->toBe(RefundStatus::Pending)
        ->and($byPayment[$second->getKey()]->status)->toBe(RefundStatus::Pending);

    $order->refresh();

    expect($order->getTotalPendingRefunded())->toBe(7000)
        ->and($order->getTotalRefunded())->toBe(0)
        ->and($order->getRemainingRefundable())->toBe(3000);
});

it('rejects a foreign-currency receipt on an order instead of mixing currencies', function (): void {
    $order = ord04Order();

    expect(fn (): OrderPayment => ord04Payment($order, 'ord04-fx-a', 5000, 'stripe', 'USD'))
        ->toThrow(InvalidArgumentException::class);

    $order->refresh();

    expect($order->getTotalPaid())->toBe(0)
        ->and($order->payments()->count())->toBe(0);
});

it('rejects a refund whose currency does not match its payment and order', function (): void {
    $order = ord04Order();
    $payment = ord04Payment($order, 'ord04-fx-pay', 5000, 'stripe', 'MYR');
    $order->refresh();

    expect(fn (): OrderRefund => OrderRefund::create([
        'order_id' => $order->getKey(),
        'payment_id' => $payment->getKey(),
        'gateway' => 'stripe',
        'transaction_id' => 'ord04-fx-refund',
        'allocation_key' => 'ord04-fx-refund',
        'amount' => 1000,
        'currency' => 'USD',
        'status' => RefundStatus::Pending,
        'reason' => 'Mixed currency refund',
    ]))->toThrow(InvalidArgumentException::class);

    expect($order->refresh()->refunds()->count())->toBe(0)
        ->and($order->getTotalPendingRefunded())->toBe(0);
});

it('respects a prior refund on the first receipt when splitting the next refund', function (): void {
    $order = ord04Order();
    $first = ord04Payment($order, 'ord04-prior-a', 5000);
    $second = ord04Payment($order, 'ord04-prior-b', 5000);
    $order->refresh();

    $action = app(RegisterOrderRefund::class);
    $settled = $action->createPending($order, 3000, 'ord04-prior-refund', 'First refund');
    $action->completePending($settled[0], 'ord04-prior-provider');

    $order->refresh();

    expect($order->getTotalRefunded())->toBe(3000)
        ->and($order->getRemainingRefundable())->toBe(7000);

    $refunds = $action->createPending($order, 5000, 'ord04-prior-refund-2', 'Second refund');
    $byPayment = ord04ByPayment($refunds);

    expect($refunds)->toHaveCount(2)
        ->and((int) $byPayment[$first->getKey()]->amount)->toBe(2000)
        ->and((int) $byPayment[$second->getKey()]->amount)->toBe(3000);
});

it('rejects an order-level refund above the money actually received', function (): void {
    $order = ord04Order();
    ord04Payment($order, 'ord04-over-a', 5000);
    ord04Payment($order, 'ord04-over-b', 5000);
    $order->refresh();

    $action = app(RegisterOrderRefund::class);

    expect(fn (): array => $action->createPending($order, 12000, 'ord04-over', 'Too much'))
        ->toThrow(InvalidArgumentException::class, 'remaining refundable amount of 10000');

    expect($order->refresh()->refunds()->count())->toBe(0)
        ->and($order->getTotalPendingRefunded())->toBe(0)
        ->and($order->getRemainingRefundable())->toBe(10000);
});

it('rejects a refund against a specified payment beyond that payment capacity', function (): void {
    $order = ord04Order();
    $first = ord04Payment($order, 'ord04-spec-a', 5000, 'stripe');
    $second = ord04Payment($order, 'ord04-spec-b', 5000, 'chip');
    $order->refresh();

    $action = app(RegisterOrderRefund::class);

    expect(fn (): array => $action->createPending($order, 6000, 'ord04-spec-over', 'Over payment', [], $first))
        ->toThrow(InvalidArgumentException::class, 'specified payment');

    expect($order->refresh()->refunds()->count())->toBe(0)
        ->and($order->getTotalPendingRefunded())->toBe(0);

    $allocated = $action->createPending($order, 5000, 'ord04-spec-ok', 'Exact payment', [], $first);

    expect($allocated)->toHaveCount(1)
        ->and($allocated[0]->payment_id)->toBe($first->getKey())
        ->and((int) $allocated[0]->amount)->toBe(5000)
        ->and($order->refresh()->getTotalPendingRefunded())->toBe(5000);

    // The spec'd payment is exhausted, so the order remainder can only
    // come from the other receipt.
    $remainder = $action->createPending($order, 5000, 'ord04-spec-rest', 'Remaining payment', [], $second);

    expect($remainder)->toHaveCount(1)
        ->and($remainder[0]->payment_id)->toBe($second->getKey())
        ->and((int) $remainder[0]->amount)->toBe(5000);
});

it('rejects a refund attributed to a payment from another order', function (): void {
    $order = ord04Order();
    ord04Payment($order, 'ord04-cross-a', 5000);
    $order->refresh();

    $other = ord04Order(2000);
    $foreign = ord04Payment($other, 'ord04-cross-b', 2000);

    expect(fn (): array => app(RegisterOrderRefund::class)->createPending($order, 1000, 'ord04-cross', 'Foreign payment', [], $foreign))
        ->toThrow(InvalidArgumentException::class, 'same order');

    expect($order->refresh()->refunds()->count())->toBe(0);
});

it('never derives refundable cash from the order total when no money was received', function (): void {
    $order = ord04Order(10000);
    $order->forceFill(['paid_at' => now()])->save();

    $action = app(RegisterOrderRefund::class);

    expect(fn (): Order => $action->execute($order, 5000, 'ord04-unpaid', 'Unpaid refund'))
        ->toThrow(InvalidArgumentException::class, 'remaining refundable amount of 0');

    expect(fn (): array => $action->createPending($order, 5000, 'ord04-unpaid-pending', 'Unpaid refund'))
        ->toThrow(InvalidArgumentException::class, 'remaining refundable amount of 0');

    expect($order->refresh()->refunds()->count())->toBe(0)
        ->and($order->getRemainingRefundable())->toBe(0);
});

it('marks each receipt refunded as its own capacity is consumed', function (): void {
    $order = ord04Order();
    $first = ord04Payment($order, 'ord04-term-a', 5000, 'stripe');
    $second = ord04Payment($order, 'ord04-term-b', 5000, 'chip');
    $order->refresh();

    $action = app(RegisterOrderRefund::class);
    $pending = $action->createPending($order, 5000, 'ord04-term-r1', 'First receipt', [], $first);
    $action->completePending($pending[0], 'ord04-term-p1');

    expect($first->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($second->refresh()->status)->toBe(PaymentStatus::Completed);

    $order->refresh();

    expect($order->status)->not->toBeInstanceOf(Refunded::class)
        ->and($order->getTotalRefunded())->toBe(5000)
        ->and($order->getRemainingRefundable())->toBe(5000);

    $pending = $action->createPending($order, 5000, 'ord04-term-r2', 'Second receipt', [], $second);
    $action->completePending($pending[0], 'ord04-term-p2');

    expect($second->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($order->refresh()->status)->toBeInstanceOf(Refunded::class)
        ->and($order->getTotalRefunded())->toBe(10000)
        ->and($order->getRemainingRefundable())->toBe(0);
});

it('replays split refunds and provider ids idempotently across retries', function (): void {
    $order = ord04Order();
    ord04Payment($order, 'ord04-retry-a', 5000, 'stripe');
    ord04Payment($order, 'ord04-retry-b', 5000, 'chip');
    $order->refresh();

    $action = app(RegisterOrderRefund::class);

    $first = $action->createPending($order, 7000, 'ord04-retry-key', 'Split refund');
    $again = $action->createPending($order, 7000, 'ord04-retry-key', 'Split refund');

    $firstIds = array_map(static fn (OrderRefund $refund): string => (string) $refund->getKey(), $first);
    $againIds = array_map(static fn (OrderRefund $refund): string => (string) $refund->getKey(), $again);

    expect($firstIds)->toHaveCount(2)
        ->and($againIds)->toEqualCanonicalizing($firstIds)
        ->and($order->refresh()->refunds()->count())->toBe(2);

    $action->completePending($first[0], 'ord04-provider-a');
    $action->completePending($first[1], 'ord04-provider-b');

    $afterProvider = $action->createPending($order->refresh(), 7000, 'ord04-retry-key', 'Split refund');
    $afterProviderIds = array_map(static fn (OrderRefund $refund): string => (string) $refund->getKey(), $afterProvider);
    $providerIds = array_map(static fn (OrderRefund $refund): string => (string) $refund->transaction_id, $afterProvider);

    expect($afterProviderIds)->toEqualCanonicalizing($firstIds)
        ->and($providerIds)->toEqualCanonicalizing(['ord04-provider-a', 'ord04-provider-b'])
        ->and($order->refresh()->refunds()->count())->toBe(2)
        ->and($order->getTotalRefunded())->toBe(7000)
        ->and($order->getRemainingRefundable())->toBe(3000);
});

it('releases capacity when a reservation fails and rejects a failed replay', function (): void {
    Event::fake([OrderRefundFailed::class, OrderRefunded::class]);

    $order = ord04Order();
    $first = ord04Payment($order, 'ord04-fail-a', 5000, 'stripe');
    $second = ord04Payment($order, 'ord04-fail-b', 5000, 'chip');
    $order->refresh();

    $action = app(RegisterOrderRefund::class);
    $reservation = $action->createPending($order, 5000, 'ord04-fail-key', 'Failing refund', [], $first);
    $action->failPending($reservation[0], 'Provider rejected');

    $order->refresh();

    expect($order->getTotalPendingRefunded())->toBe(0)
        ->and($order->getTotalRefunded())->toBe(0)
        ->and($order->getRemainingRefundable())->toBe(10000);

    Event::assertDispatchedTimes(OrderRefundFailed::class, 1);

    expect(fn (): array => $action->createPending($order, 5000, 'ord04-fail-key', 'Retry', [], $first))
        ->toThrow(RuntimeException::class, 'has already failed');

    // The released capacity funds a fresh request, allocated FIFO from the
    // failed payment's freed capacity.
    $replacement = $action->createPending($order, 6000, 'ord04-fail-key-2', 'Replacement');
    $replacementByPayment = ord04ByPayment($replacement);

    expect($replacement)->toHaveCount(2)
        ->and((int) $replacementByPayment[$first->getKey()]->amount)->toBe(5000)
        ->and((int) $replacementByPayment[$second->getKey()]->amount)->toBe(1000)
        ->and($order->refresh()->getTotalPendingRefunded())->toBe(6000);
});

it('emits one OrderRefunded per allocation with its persisted identity', function (): void {
    Event::fake([OrderRefunded::class]);

    $order = ord04Order();
    ord04Payment($order, 'ord04-event-a', 5000, 'stripe');
    ord04Payment($order, 'ord04-event-b', 5000, 'chip');
    $order->refresh();

    $action = app(RegisterOrderRefund::class);
    $refunds = $action->createPending($order, 7000, 'ord04-event-key', 'Split refund');

    foreach ($refunds as $refund) {
        $action->completePending($refund, 'provider-' . $refund->getKey());
    }

    Event::assertDispatchedTimes(OrderRefunded::class, 2);

    Event::assertDispatched(
        OrderRefunded::class,
        fn (OrderRefunded $event): bool => $event->refundId === (string) $refunds[0]->getKey()
            && $event->amount === 5000
            && $event->reason === 'Split refund',
    );

    Event::assertDispatched(
        OrderRefunded::class,
        fn (OrderRefunded $event): bool => $event->refundId === (string) $refunds[1]->getKey()
            && $event->amount === 2000
            && $event->reason === 'Split refund',
    );
});

it('allocates an immediate refund across receipts and marks each receipt refunded', function (): void {
    Event::fake([OrderRefunded::class]);

    $order = ord04Order();
    $first = ord04Payment($order, 'ord04-now-a', 5000, 'stripe');
    $second = ord04Payment($order, 'ord04-now-b', 5000, 'chip');
    $order->refresh();

    $result = app(RegisterOrderRefund::class)->execute($order, 10000, 'ord04-now-provider', 'Full return');

    expect($result->status)->toBeInstanceOf(Refunded::class)
        ->and($first->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($second->refresh()->status)->toBe(PaymentStatus::Refunded);

    $refunds = $result->refunds()->get();

    expect($refunds)->toHaveCount(2)
        ->and((int) $refunds->sum('amount'))->toBe(10000)
        ->and((int) $result->refresh()->getTotalRefunded())->toBe(10000)
        ->and($result->getRemainingRefundable())->toBe(0);

    foreach ($refunds as $refund) {
        expect($refund->payment_id)->not->toBeNull()
            ->and($refund->allocation_key)->toBe('ord04-now-provider');
    }

    Event::assertDispatchedTimes(OrderRefunded::class, 2);
});

it('routes cancellation reservations through the shared allocator after a split refund', function (): void {
    $order = ord04Order();
    $first = ord04Payment($order, 'ord04-cancel-a', 5000, 'stripe');
    $second = ord04Payment($order, 'ord04-cancel-b', 5000, 'chip');
    $order->refresh();

    $action = app(RegisterOrderRefund::class);
    $settled = $action->createPending($order, 3000, 'ord04-cancel-refund', 'Partial return', [], $first);
    $action->completePending($settled[0], 'ord04-cancel-provider');

    app(CancelOrder::class)->execute($order->refresh(), 'Customer request');

    $order->refresh();

    $reservations = $order->refunds()
        ->where('status', RefundStatus::Pending->value)
        ->get()
        ->keyBy('payment_id');

    expect($order->status)->toBeInstanceOf(Canceled::class)
        ->and($reservations)->toHaveCount(2)
        ->and((int) $reservations[$first->getKey()]->amount)->toBe(2000)
        ->and((int) $reservations[$second->getKey()]->amount)->toBe(5000)
        ->and($reservations[$first->getKey()]->allocation_key)->toBe('cancel:' . $order->getKey() . ':' . $first->getKey())
        ->and($reservations[$second->getKey()]->allocation_key)->toBe('cancel:' . $order->getKey() . ':' . $second->getKey())
        ->and($order->getTotalPendingRefunded())->toBe(7000)
        ->and($order->getRemainingRefundable())->toBe(0);

    // Re-running the cancellation allocator reserves nothing new.
    $again = $action->createCancellationReservations($order, 'Customer request');

    expect($again)->toBeEmpty()
        ->and($order->refresh()->refunds()->count())->toBe(3);
});

it('rejects a specified refund against a failed payment that never received money', function (): void {
    $order = ord04Order();
    ord04Payment($order, 'ord04-guard-paid-a', 5000, 'stripe');
    $declined = ord04UnreceivedPayment($order, 'ord04-guard-declined-a', 5000);
    $declined->markAsFailed('Declined');
    $order->refresh();

    $action = app(RegisterOrderRefund::class);

    expect(fn (): array => $action->createPending($order, 5000, 'ord04-guard-declined-key-a', 'Never received', [], $declined))
        ->toThrow(InvalidArgumentException::class, 'The specified payment has not received any refundable money.');

    expect($order->refresh()->refunds()->count())->toBe(0)
        ->and($order->getTotalPendingRefunded())->toBe(0)
        ->and($order->getTotalRefunded())->toBe(0)
        ->and($order->getRemainingRefundable())->toBe(5000)
        ->and($declined->refresh()->status)->toBe(PaymentStatus::Failed);
});

it('rejects a specified refund against a still-pending payment', function (): void {
    $order = ord04Order();
    ord04Payment($order, 'ord04-guard-paid-b', 5000, 'stripe');
    $pending = ord04UnreceivedPayment($order, 'ord04-guard-pending-b', 5000);
    $order->refresh();

    $action = app(RegisterOrderRefund::class);

    expect(fn (): array => $action->createPending($order, 5000, 'ord04-guard-pending-key-b', 'Still pending', [], $pending))
        ->toThrow(InvalidArgumentException::class, 'The specified payment has not received any refundable money.');

    expect($order->refresh()->refunds()->count())->toBe(0)
        ->and($order->getTotalPendingRefunded())->toBe(0)
        ->and($order->getRemainingRefundable())->toBe(5000)
        ->and($pending->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects an immediate refund against a failed payment that never received money', function (): void {
    $order = ord04Order();
    ord04Payment($order, 'ord04-guard-now-paid', 5000, 'stripe');
    $declined = ord04UnreceivedPayment($order, 'ord04-guard-now-declined', 5000);
    $declined->markAsFailed('Declined');
    $order->refresh();

    $action = app(RegisterOrderRefund::class);

    expect(fn (): Order => $action->execute($order, 5000, 'ord04-guard-now-key', 'Never received', [], $declined))
        ->toThrow(InvalidArgumentException::class, 'The specified payment has not received any refundable money.');

    expect($order->refresh()->refunds()->count())->toBe(0)
        ->and($order->getTotalPendingRefunded())->toBe(0)
        ->and($order->getTotalRefunded())->toBe(0)
        ->and($order->getRemainingRefundable())->toBe(5000)
        ->and($declined->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($declined->refunded_at)->toBeNull();
});

it('dispatches the refund failed event with the persisted failure state', function (): void {
    Event::fake([OrderRefundFailed::class]);

    $order = ord04Order();
    $payment = ord04Payment($order, 'ord04-payload-paid', 5000);
    $order->refresh();

    $action = app(RegisterOrderRefund::class);
    $reservation = $action->createPending($order, 2000, 'ord04-payload-key', 'Failing refund', [], $payment);
    $failed = $action->failPending($reservation[0], 'Provider rejected');

    expect($failed->status)->toBe(RefundStatus::Failed)
        ->and($failed->failed_at)->not->toBeNull()
        ->and($failed->notes)->toBe('Provider rejected');

    Event::assertDispatched(
        OrderRefundFailed::class,
        fn (OrderRefundFailed $event): bool => (string) $event->refund->getKey() === (string) $reservation[0]->getKey()
            && $event->refund->status === RefundStatus::Failed
            && $event->refund->failed_at !== null
            && $event->refund->notes === 'Provider rejected',
    );
});

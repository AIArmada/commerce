<?php

declare(strict_types=1);

use AIArmada\Orders\Contracts\OrderPaymentLifecycle;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Events\OrderRefunded;
use AIArmada\Orders\Events\OrderRefundFailed;
use AIArmada\Orders\Features\Payments\Actions\RegisterOrderRefund;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\States\Processing;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('orders.owner.auto_assign_on_create', false);
});

function ord06Order(): Order
{
    return Order::create([
        'order_number' => 'ORD-ORD06-' . mb_strtoupper(uniqid()),
        'status' => Processing::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ])->refresh();
}

function ord06Payment(Order $order, string $transactionId): OrderPayment
{
    return OrderPayment::create([
        'order_id' => $order->getKey(),
        'gateway' => 'stripe',
        'transaction_id' => $transactionId,
        'amount' => 5000,
        'currency' => 'MYR',
        'status' => PaymentStatus::Completed,
        'paid_at' => now(),
    ])->refresh();
}

it('preserves provider identity and terminal timestamp on a completed replay', function (): void {
    Event::fake([OrderRefunded::class]);

    $order = ord06Order();
    $payment = ord06Payment($order, 'ord06-replay-pay');
    $order->refresh();

    $action = app(RegisterOrderRefund::class);
    $pending = $action->createPending($order, 5000, 'ord06-replay-key', 'Replay refund', [], $payment);
    $action->completePending($pending[0], 'provider-first');

    $order->refresh();
    $refund = $order->refunds()->sole();
    $terminalAt = $refund->refunded_at;

    Event::assertDispatchedTimes(OrderRefunded::class, 1);

    // Replay with a different provider id must not replace identity or timestamp.
    $action->completePending($refund->fresh(), 'provider-second');

    $order->refresh();
    $replayed = $order->refunds()->sole();

    expect($replayed->status)->toBe(RefundStatus::Completed)
        ->and($replayed->transaction_id)->toBe('provider-first')
        ->and($replayed->refunded_at?->toDateTimeString())->toBe($terminalAt?->toDateTimeString());

    Event::assertDispatchedTimes(OrderRefunded::class, 1);
});

it('rejects completion of a failed refund', function (): void {
    $order = ord06Order();
    $payment = ord06Payment($order, 'ord06-failed-pay');
    $order->refresh();

    $action = app(RegisterOrderRefund::class);
    $pending = $action->createPending($order, 5000, 'ord06-failed-key', 'Failing refund', [], $payment);
    $action->failPending($pending[0], 'Provider rejected');

    $order->refresh();
    $failed = $order->refunds()->sole();

    expect(fn (): Order => $action->completePending($failed->fresh(), 'late-provider'))
        ->toThrow(RuntimeException::class, 'Only pending refunds');

    $order->refresh();
    $stillFailed = $order->refunds()->sole();

    expect($stillFailed->status)->toBe(RefundStatus::Failed)
        ->and($order->getTotalRefunded())->toBe(0)
        ->and($order->getTotalPendingRefunded())->toBe(0);
});

it('emits exactly one event for two completion attempts with different provider ids', function (): void {
    Event::fake([OrderRefunded::class]);

    $order = ord06Order();
    $payment = ord06Payment($order, 'ord06-event-pay');
    $order->refresh();

    $action = app(RegisterOrderRefund::class);
    $pending = $action->createPending($order, 5000, 'ord06-event-key', 'Event refund', [], $payment);

    $action->completePending($pending[0], 'provider-a');
    $action->completePending($pending[0]->fresh(), 'provider-b');

    Event::assertDispatchedTimes(OrderRefunded::class, 1);
});

it('emits a refund failure event only on the first pending-to-failed transition', function (): void {
    Event::fake([OrderRefundFailed::class]);

    $order = ord06Order();
    $payment = ord06Payment($order, 'ord06-failure-event-pay');
    $order->refresh();

    $action = app(RegisterOrderRefund::class);
    $refund = $action->createPending($order, 5000, 'ord06-failure-event', 'Refund', [], $payment)[0];

    $action->failPending($refund, 'Provider rejected');
    $action->failPending($refund->fresh(), 'Duplicate notification');

    Event::assertDispatchedTimes(OrderRefundFailed::class, 1);
});

it('does not rewrite a previously refunded payment timestamp when another payment is refunded', function (): void {
    $order = ord06Order();
    $first = ord06Payment($order, 'first-paid');
    $second = ord06Payment($order, 'second-paid');
    $action = app(RegisterOrderRefund::class);

    $firstRefund = $action->createPending($order->refresh(), 5000, 'first-refund', 'First refund', [], $first);
    $action->completePending($firstRefund[0], 'first-provider-refund');
    $timestamp = $first->refresh()->refunded_at;

    $this->travel(1)->minutes();
    $secondRefund = $action->createPending($order->refresh(), 5000, 'second-refund', 'Second refund', [], $second);
    $action->completePending($secondRefund[0], 'second-provider-refund');

    expect($first->refresh()->refunded_at?->toDateTimeString())->toBe($timestamp?->toDateTimeString())
        ->and($second->refresh()->status)->toBe(PaymentStatus::Refunded);

    $this->travelBack();
});

it('rejects lifecycle settlement without the corresponding accounting totals', function (): void {
    $order = ord06Order();
    $lifecycle = app(OrderPaymentLifecycle::class);

    expect(fn () => $lifecycle->markPaid($order->id))
        ->toThrow(LogicException::class, 'Only fully paid orders');

    expect(fn () => $lifecycle->markRefunded($order->id))
        ->toThrow(LogicException::class, 'Only fully refunded orders');

    ord06Payment($order, 'unrefunded-payment');

    expect(fn () => $lifecycle->markRefunded($order->id))
        ->toThrow(LogicException::class, 'Only fully refunded orders')
        ->and($order->refresh()->refunded_at)->toBeNull();
});

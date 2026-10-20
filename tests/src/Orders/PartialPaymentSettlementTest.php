<?php

declare(strict_types=1);

use AIArmada\Orders\Actions\RegisterOrderPayment;
use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Events\OrderProcessingStarted;
use AIArmada\Orders\Exceptions\OrderNotAwaitingPayment;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Fraud;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Refunded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * @param  class-string  $status
 */
function makePartialPaymentOrder(int $grandTotal = 10000, string $status = PendingPayment::class): Order
{
    $order = Order::create([
        'order_number' => 'ORD-PARTIAL-' . uniqid(),
        'status' => $status,
        'currency' => 'MYR',
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
    ]);

    return $order->refresh();
}

/**
 * @return array<int, object>
 */
function partialPaymentOutboxRows(): array
{
    return DB::table(config('orders.database.tables.order_outbox', 'order_outbox_messages'))
        ->orderBy('event_class')
        ->get()
        ->all();
}

it('keeps the order awaiting payment after a partial payment', function (): void {
    $order = makePartialPaymentOrder(10000);

    $flushHooks = captureAfterCommitHooks();
    Event::fake([OrderPaid::class, OrderProcessingStarted::class, OrderFulfillmentRequired::class]);

    $result = app(RegisterOrderPayment::class)->execute($order, 'txn_partial_1', 'stripe', 1000);

    expect($result->status)->toBeInstanceOf(PendingPayment::class)
        ->and($result->paid_at)->toBeNull()
        ->and($result->getTotalPaid())->toBe(1000)
        ->and($result->getBalanceDue())->toBe(9000)
        ->and($result->isFullyPaid())->toBeFalse();

    expect(partialPaymentOutboxRows())->toHaveCount(0);

    $flushHooks();

    // Every accepted payment still emits its own receipt event with the
    // actual amount so revenue recognition sees partial settlements.
    Event::assertDispatchedTimes(OrderPaid::class, 1);
    Event::assertDispatched(
        OrderPaid::class,
        fn (OrderPaid $event): bool => $event->amount === 1000
    );
    Event::assertNotDispatched(OrderProcessingStarted::class);
    Event::assertNotDispatched(OrderFulfillmentRequired::class);
});

it('settles the order exactly once when the final payment completes the balance', function (): void {
    $order = makePartialPaymentOrder(10000);
    $action = app(RegisterOrderPayment::class);

    $flushHooks = captureAfterCommitHooks();
    Event::fake([OrderPaid::class, OrderProcessingStarted::class, OrderFulfillmentRequired::class]);

    $action->execute($order, 'txn_settle_first', 'stripe', 1000);

    expect(partialPaymentOutboxRows())->toHaveCount(0);

    $result = $action->execute($order->refresh(), 'txn_settle_final', 'stripe', 9000);

    expect($result->status)->toBeInstanceOf(Processing::class)
        ->and($result->paid_at)->not->toBeNull()
        ->and($result->getTotalPaid())->toBe(10000)
        ->and($result->isFullyPaid())->toBeTrue();

    // Only the settling payment stages fulfillment, exactly once.
    $rows = partialPaymentOutboxRows();

    expect($rows)->toHaveCount(2)
        ->and(array_column($rows, 'transaction_id'))->toBe(['txn_settle_final', 'txn_settle_final'])
        ->and(array_column($rows, 'event_class'))->toBe([
            OrderFulfillmentRequired::class,
            OrderProcessingStarted::class,
        ]);

    $flushHooks();

    Event::assertDispatchedTimes(OrderPaid::class, 2);
    Event::assertDispatched(
        OrderPaid::class,
        fn (OrderPaid $event): bool => $event->amount === 1000
    );
    Event::assertDispatched(
        OrderPaid::class,
        fn (OrderPaid $event): bool => $event->amount === 9000
    );
    Event::assertDispatchedTimes(OrderProcessingStarted::class, 1);
    Event::assertDispatchedTimes(OrderFulfillmentRequired::class, 1);
});

it('rejects a payment that would exceed the outstanding balance after a partial payment', function (): void {
    $order = makePartialPaymentOrder(10000);
    $action = app(RegisterOrderPayment::class);

    captureAfterCommitHooks();

    $action->execute($order, 'txn_overpay_first', 'stripe', 1000);

    expect(fn (): Order => $action->execute($order->refresh(), 'txn_overpay_final', 'stripe', 9001))
        ->toThrow(InvalidArgumentException::class, 'outstanding balance');

    $order->refresh();

    expect($order->status)->toBeInstanceOf(PendingPayment::class)
        ->and($order->getTotalPaid())->toBe(1000)
        ->and($order->payments()->count())->toBe(1)
        ->and($order->paid_at)->toBeNull();
});

it('replays accepted payment identities without new state changes or effects', function (): void {
    $order = makePartialPaymentOrder(10000);
    $action = app(RegisterOrderPayment::class);

    $flushHooks = captureAfterCommitHooks();
    Event::fake([OrderPaid::class, OrderProcessingStarted::class, OrderFulfillmentRequired::class]);

    $action->execute($order, 'txn_replay_partial', 'stripe', 1000);
    $action->execute($order->refresh(), 'txn_replay_final', 'stripe', 9000);

    // Replays arrive after the order moved to Processing (a non-payable
    // state): the identity lookup must run before the lifecycle gate.
    $action->execute($order->refresh(), 'txn_replay_partial', 'stripe', 1000);
    $action->execute($order->refresh(), 'txn_replay_final', 'stripe', 9000);

    $flushHooks();

    expect($order->refresh()->payments()->count())->toBe(2)
        ->and(partialPaymentOutboxRows())->toHaveCount(2);

    Event::assertDispatchedTimes(OrderPaid::class, 2);
    Event::assertDispatchedTimes(OrderProcessingStarted::class, 1);
    Event::assertDispatchedTimes(OrderFulfillmentRequired::class, 1);
});

it('rejects new payment identities on non-payable orders', function (string $state): void {
    $order = makePartialPaymentOrder(10000, $state);

    expect(fn (): Order => app(RegisterOrderPayment::class)->execute(
        $order,
        'txn_nonpayable_' . Str::random(8),
        'stripe',
        1000,
    ))->toThrow(OrderNotAwaitingPayment::class);

    $order->refresh();

    expect($order->payments()->count())->toBe(0)
        ->and($order->getTotalPaid())->toBe(0)
        ->and($order->paid_at)->toBeNull();
})->with([
    'canceled' => [Canceled::class],
    'fraud' => [Fraud::class],
    'refunded' => [Refunded::class],
    'processing' => [Processing::class],
]);

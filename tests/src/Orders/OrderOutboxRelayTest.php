<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Actions\Outbox\RelayOrderOutbox;
use AIArmada\Orders\Enums\OutboxStatus;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Events\OrderProcessingStarted;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\OrdersServiceProvider;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Fraud;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Refunded;
use AIArmada\Orders\Transitions\FreeOrderConfirmed;
use AIArmada\Orders\Transitions\OrderCanceled;
use AIArmada\Orders\Transitions\OrderFlaggedAsFraud;
use AIArmada\Orders\Transitions\PaymentConfirmed;
use AIArmada\Orders\Transitions\RefundCompleted;
use AIArmada\Orders\Transitions\RefundProcessed;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionServiceProvider;

function makeOutboxRelayOrder(string $number, int $grandTotal = 10000): Order
{
    $order = new Order;
    $order->forceFill([
        'order_number' => $number,
        'status' => PendingPayment::class,
        'currency' => 'USD',
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
    ]);
    $order->save();

    return $order;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function insertOutboxRelayRow(Order $order, array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::table(config('orders.database.tables.order_outbox', 'order_outbox_messages'))->insert([
        'id' => $id,
        'order_id' => $order->getKey(),
        'owner_type' => $order->owner_type,
        'owner_id' => $order->owner_id,
        'event_class' => OrderProcessingStarted::class,
        'transaction_id' => 'txn-relay-1',
        'gateway' => 'stripe',
        'status' => OutboxStatus::Pending->value,
        'attempts' => 0,
        'claimed_at' => null,
        'relayed_at' => null,
        'next_retry_at' => null,
        'last_error' => null,
        'created_at' => CarbonImmutable::now(),
        'updated_at' => CarbonImmutable::now(),
        ...$overrides,
    ]);

    return $id;
}

function outboxRelayRows(): array
{
    return DB::table(config('orders.database.tables.order_outbox', 'order_outbox_messages'))
        ->orderBy('event_class')
        ->get()
        ->all();
}

it('stages pending rows for the replayable events when payment confirms', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-STAGE-1');

    // Capture the commit hooks so the live dispatch does not mark the
    // staged rows before we assert them.
    captureAfterCommitHooks();

    (new PaymentConfirmed($order, 'txn-relay-stage-1', 'stripe', 10000))->handle();

    $rows = outboxRelayRows();

    expect($rows)->toHaveCount(2)
        ->and(array_column($rows, 'event_class'))->toBe([
            OrderFulfillmentRequired::class,
            OrderProcessingStarted::class,
        ])
        ->and(array_column($rows, 'status'))->toBe([OutboxStatus::Pending->value, OutboxStatus::Pending->value])
        ->and(array_column($rows, 'transaction_id'))->toBe(['txn-relay-stage-1', 'txn-relay-stage-1'])
        ->and(array_column($rows, 'gateway'))->toBe(['stripe', 'stripe']);

    // OrderPaid stays direct: invoice creation and payment emails are not replayable.
    expect(collect($rows)->pluck('event_class'))->not->toContain(OrderPaid::class);
});

it('stages pending rows when a free order confirms', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-STAGE-2', 0);

    captureAfterCommitHooks();

    (new FreeOrderConfirmed($order))->handle();

    $rows = outboxRelayRows();

    expect($rows)->toHaveCount(2)
        ->and(array_column($rows, 'gateway'))->toBe(['free', 'free']);
});

it('stages no rows for duplicate payment delivery', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-STAGE-3');

    (new PaymentConfirmed($order, 'txn-relay-stage-3', 'stripe', 10000))->handle();
    (new PaymentConfirmed($order, 'txn-relay-stage-3', 'stripe', 10000))->handle();

    expect(outboxRelayRows())->toHaveCount(2);
});

it('marks staged rows relayed when the live commit hooks run', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-STAGE-4');

    Event::fake([OrderProcessingStarted::class, OrderFulfillmentRequired::class, OrderPaid::class]);

    $invokeCommitHooks = captureAfterCommitHooks();

    $result = (new PaymentConfirmed($order, 'txn-relay-stage-4', 'stripe', 10000))->handle();

    expect($result->status)->toBeInstanceOf(Processing::class);

    $invokeCommitHooks();

    Event::assertDispatched(OrderPaid::class);
    Event::assertDispatched(OrderProcessingStarted::class);
    Event::assertDispatched(OrderFulfillmentRequired::class);

    $rows = outboxRelayRows();

    expect($rows)->toHaveCount(2)
        ->and(array_column($rows, 'status'))->toBe([OutboxStatus::Relayed->value, OutboxStatus::Relayed->value]);
});

it('marks staged rows relayed through the live commit path', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-STAGE-5');

    (new PaymentConfirmed($order, 'txn-relay-stage-5', 'stripe', 10000))->handle();

    $rows = outboxRelayRows();

    expect($rows)->toHaveCount(2)
        ->and(array_column($rows, 'status'))->toBe([OutboxStatus::Relayed->value, OutboxStatus::Relayed->value]);
});

it('relays backdated pending rows and marks them relayed', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-1');

    Event::fake([OrderProcessingStarted::class, OrderFulfillmentRequired::class]);

    $id = insertOutboxRelayRow($order, [
        'event_class' => OrderFulfillmentRequired::class,
        'created_at' => CarbonImmutable::now()->subMinutes(10),
    ]);

    $result = RelayOrderOutbox::run();

    expect($result['relayed'])->toBe(1);

    Event::assertDispatched(
        OrderFulfillmentRequired::class,
        fn (OrderFulfillmentRequired $event): bool => $event->order->is($order)
            && $event->transactionId === 'txn-relay-1'
            && $event->gateway === 'stripe'
    );

    $row = DB::table(config('orders.database.tables.order_outbox', 'order_outbox_messages'))->find($id);

    expect($row->status)->toBe(OutboxStatus::Relayed->value)
        ->and($row->relayed_at)->not->toBeNull();
});

it('skips rows within the grace window', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-2');

    Event::fake([OrderProcessingStarted::class]);

    insertOutboxRelayRow($order);

    $result = RelayOrderOutbox::run();

    expect($result['relayed'])->toBe(0)
        ->and($result['skipped'])->toBe(0);

    Event::assertNotDispatched(OrderProcessingStarted::class);

    expect(outboxRelayRows()[0]->status)->toBe(OutboxStatus::Pending->value);
});

it('dispatches inside the order owner context', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-3');

    Queue::fake();

    $seen = [];
    Event::listen(OrderProcessingStarted::class, function () use (&$seen): void {
        $seen[] = OwnerContext::resolve();
    });

    insertOutboxRelayRow($order, [
        'created_at' => CarbonImmutable::now()->subMinutes(10),
    ]);

    RelayOrderOutbox::run();

    expect($seen)->toHaveCount(1)
        ->and($seen[0]->is($order->owner))->toBeTrue();
});

it('fails with backoff when dispatch throws, then deads after max attempts', function (): void {
    config()->set('orders.outbox.max_attempts', 2);
    config()->set('orders.outbox.retry_base_seconds', 60);

    $order = makeOutboxRelayOrder('ORD-RELAY-4');

    Queue::fake();

    Event::listen(OrderProcessingStarted::class, function (): void {
        throw new RuntimeException('boom');
    });

    $table = config('orders.database.tables.order_outbox', 'order_outbox_messages');

    $id = insertOutboxRelayRow($order, [
        'created_at' => CarbonImmutable::now()->subMinutes(10),
    ]);

    $result = RelayOrderOutbox::run();

    expect($result['failed'])->toBe(1);

    $row = DB::table($table)->find($id);

    expect($row->status)->toBe(OutboxStatus::Failed->value)
        ->and((int) $row->attempts)->toBe(1)
        ->and($row->next_retry_at)->not->toBeNull()
        ->and($row->last_error)->toContain('boom');

    // Not due yet: relay leaves it alone.
    expect(RelayOrderOutbox::run()['failed'])->toBe(0);

    DB::table($table)->where('id', $id)->update([
        'next_retry_at' => CarbonImmutable::now()->subMinute(),
    ]);

    $result = RelayOrderOutbox::run();

    expect($result['dead'])->toBe(1)
        ->and(DB::table($table)->find($id)->status)->toBe(OutboxStatus::Dead->value);
});

it('deads unknown event classes immediately without dispatching', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-5');

    Event::fake([OrderProcessingStarted::class]);

    $table = config('orders.database.tables.order_outbox', 'order_outbox_messages');

    $id = insertOutboxRelayRow($order, [
        'event_class' => 'App\\Removed\\Nope',
        'created_at' => CarbonImmutable::now()->subMinutes(10),
    ]);

    $result = RelayOrderOutbox::run();

    expect($result['dead'])->toBe(1)
        ->and(DB::table($table)->find($id)->status)->toBe(OutboxStatus::Dead->value);

    Event::assertNotDispatched(OrderProcessingStarted::class);
});

it('deads rows whose order is gone', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-6');

    $table = config('orders.database.tables.order_outbox', 'order_outbox_messages');

    $id = insertOutboxRelayRow($order, [
        'created_at' => CarbonImmutable::now()->subMinutes(10),
    ]);

    $order->delete();

    $result = RelayOrderOutbox::run();

    expect($result['dead'])->toBe(1)
        ->and(DB::table($table)->find($id)->status)->toBe(OutboxStatus::Dead->value);
});

it('runs as an artisan command', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-7');

    Event::fake([OrderProcessingStarted::class]);

    insertOutboxRelayRow($order, [
        'created_at' => CarbonImmutable::now()->subMinutes(10),
    ]);

    // ActionServiceProvider is discovery-registered in host apps; the
    // suite TestCase only loads explicit providers.
    app()->register(ActionServiceProvider::class);
    app()->register(OrdersServiceProvider::class);

    $this->artisan('orders:outbox-relay')
        ->expectsOutputToContain('relayed: 1')
        ->assertSuccessful();

    Event::assertDispatched(OrderProcessingStarted::class);
});

it('suppresses staged rows when the order is cancelled', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-CANCEL');

    // Capture the live commit hooks so the staged rows stay pending,
    // simulating a crash between commit and dispatch.
    captureAfterCommitHooks();
    (new PaymentConfirmed($order, 'txn-relay-cancel', 'stripe', 10000))->handle();

    expect(outboxRelayRows())->toHaveCount(2);

    (new OrderCanceled($order->fresh(), 'Customer request'))->handle();

    expect($order->fresh()->status)->toBeInstanceOf(Canceled::class);

    foreach (outboxRelayRows() as $row) {
        expect($row->status)->toBe(OutboxStatus::Suppressed->value);
    }

    Event::fake([OrderFulfillmentRequired::class, OrderProcessingStarted::class]);

    $result = RelayOrderOutbox::run();

    expect($result['relayed'])->toBe(0)
        ->and($result['suppressed'])->toBe(0);

    Event::assertNotDispatched(OrderFulfillmentRequired::class);
    Event::assertNotDispatched(OrderProcessingStarted::class);
});

it('relay suppresses aged rows for cancelled orders instead of dispatching', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-CANCEL2');
    (new OrderCanceled($order, 'Customer request'))->handle();

    Event::fake([OrderFulfillmentRequired::class]);

    $id = insertOutboxRelayRow($order->fresh(), [
        'event_class' => OrderFulfillmentRequired::class,
        'created_at' => CarbonImmutable::now()->subMinutes(10),
    ]);

    $result = RelayOrderOutbox::run();

    expect($result['suppressed'])->toBe(1)
        ->and($result['relayed'])->toBe(0);

    Event::assertNotDispatched(OrderFulfillmentRequired::class);

    $table = config('orders.database.tables.order_outbox', 'order_outbox_messages');

    expect(DB::table($table)->find($id)->status)->toBe(OutboxStatus::Suppressed->value);
});

it('suppresses staged rows when the order is fully refunded', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-REFUND');

    captureAfterCommitHooks();
    (new PaymentConfirmed($order, 'txn-relay-refund', 'stripe', 10000))->handle();

    $payment = $order->payments()->where('status', PaymentStatus::Completed)->firstOrFail();

    $refund = $order->refunds()->create([
        'payment_id' => $payment->getKey(),
        'gateway' => $payment->gateway,
        'amount' => 10000,
        'currency' => $order->currency,
        'status' => RefundStatus::Pending,
        'reason' => 'Full refund',
    ]);

    (new RefundCompleted($refund, 'txn-refund-1'))->handle();

    expect($order->fresh()->status)->toBeInstanceOf(Refunded::class);

    foreach (outboxRelayRows() as $row) {
        expect($row->status)->toBe(OutboxStatus::Suppressed->value);
    }
});

it('live dispatch skips replayable events when cancel commits before the commit hooks run', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-LIVE1');

    $flushHooks = captureAfterCommitHooks();
    (new PaymentConfirmed($order, 'txn-live-1', 'stripe', 10000))->handle();
    (new OrderCanceled($order->fresh(), 'Customer request'))->handle();

    Event::fake();

    $flushHooks();

    Event::assertNotDispatched(OrderFulfillmentRequired::class);
    Event::assertNotDispatched(OrderProcessingStarted::class);
    // The payment really happened: its non-replayable event still fires.
    Event::assertDispatched(OrderPaid::class);

    foreach (outboxRelayRows() as $row) {
        expect($row->status)->toBe(OutboxStatus::Suppressed->value);
    }
});

it('live dispatch skips replayable events for free orders cancelled before the commit hooks run', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-LIVE2', 0);

    $flushHooks = captureAfterCommitHooks();
    (new FreeOrderConfirmed($order))->handle();
    (new OrderCanceled($order->fresh(), 'Customer request'))->handle();

    Event::fake();

    $flushHooks();

    Event::assertNotDispatched(OrderFulfillmentRequired::class);
    Event::assertNotDispatched(OrderProcessingStarted::class);

    foreach (outboxRelayRows() as $row) {
        expect($row->status)->toBe(OutboxStatus::Suppressed->value);
    }
});

it('suppresses staged rows when a processed refund completes the full amount', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-REFPROC');

    captureAfterCommitHooks();
    (new PaymentConfirmed($order, 'txn-relay-refproc', 'stripe', 10000))->handle();

    (new RefundProcessed($order->fresh(), 10000, 'txn-refproc-1', 'Returned items'))->handle();

    expect($order->fresh()->status)->toBeInstanceOf(Refunded::class);

    foreach (outboxRelayRows() as $row) {
        expect($row->status)->toBe(OutboxStatus::Suppressed->value);
    }
});

it('does not suppress staged rows on partial processed refund', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-REFPROC-PART');

    captureAfterCommitHooks();
    (new PaymentConfirmed($order, 'txn-relay-refproc-part', 'stripe', 10000))->handle();

    (new RefundProcessed($order->fresh(), 4000, 'txn-refproc-part-1', 'Partial return'))->handle();

    expect($order->fresh()->status)->toBeInstanceOf(Processing::class);

    foreach (outboxRelayRows() as $row) {
        expect($row->status)->toBe(OutboxStatus::Pending->value);
    }
});

it('suppresses staged rows when the order is flagged as fraud', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-FRAUD');

    captureAfterCommitHooks();
    (new PaymentConfirmed($order, 'txn-relay-fraud', 'stripe', 10000))->handle();
    (new OrderFlaggedAsFraud($order->fresh(), 'Stolen card'))->handle();

    expect($order->fresh()->status)->toBeInstanceOf(Fraud::class);

    foreach (outboxRelayRows() as $row) {
        expect($row->status)->toBe(OutboxStatus::Suppressed->value);
    }
});

it('relay suppresses aged rows for fraud orders instead of dispatching', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-FRAUD2');

    captureAfterCommitHooks();
    (new PaymentConfirmed($order, 'txn-relay-fraud2', 'stripe', 10000))->handle();
    (new OrderFlaggedAsFraud($order->fresh(), 'Stolen card'))->handle();

    Event::fake([OrderFulfillmentRequired::class]);

    // Escaped row (staged outside the suppressed set): the relay must
    // still refuse to dispatch for a fraud order.
    $id = insertOutboxRelayRow($order->fresh(), [
        'event_class' => OrderFulfillmentRequired::class,
        'created_at' => CarbonImmutable::now()->subMinutes(10),
    ]);

    $result = RelayOrderOutbox::run();

    expect($result['suppressed'])->toBe(1)
        ->and($result['relayed'])->toBe(0);

    Event::assertNotDispatched(OrderFulfillmentRequired::class);

    $table = config('orders.database.tables.order_outbox', 'order_outbox_messages');

    expect(DB::table($table)->find($id)->status)->toBe(OutboxStatus::Suppressed->value);
});

it('live dispatch skips replayable events when fraud is flagged before the commit hooks run', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-FRAUD3');

    $flushHooks = captureAfterCommitHooks();
    (new PaymentConfirmed($order, 'txn-relay-fraud3', 'stripe', 10000))->handle();
    (new OrderFlaggedAsFraud($order->fresh(), 'Stolen card'))->handle();

    Event::fake();

    $flushHooks();

    Event::assertNotDispatched(OrderFulfillmentRequired::class);
    Event::assertNotDispatched(OrderProcessingStarted::class);
    Event::assertDispatched(OrderPaid::class);
});

it('does not suppress staged rows on partial refund', function (): void {
    $order = makeOutboxRelayOrder('ORD-RELAY-PARTIAL');

    captureAfterCommitHooks();
    (new PaymentConfirmed($order, 'txn-relay-partial', 'stripe', 10000))->handle();

    $payment = $order->payments()->where('status', PaymentStatus::Completed)->firstOrFail();

    $refund = $order->refunds()->create([
        'payment_id' => $payment->getKey(),
        'gateway' => $payment->gateway,
        'amount' => 4000,
        'currency' => $order->currency,
        'status' => RefundStatus::Pending,
        'reason' => 'Partial refund',
    ]);

    Event::fake([OrderFulfillmentRequired::class, OrderProcessingStarted::class]);

    (new RefundCompleted($refund, 'txn-refund-partial'))->handle();

    expect($order->fresh()->status)->toBeInstanceOf(Processing::class);

    foreach (outboxRelayRows() as $row) {
        expect($row->status)->toBe(OutboxStatus::Pending->value);
    }
});

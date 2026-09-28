<?php

declare(strict_types=1);

use AIArmada\Orders\Actions\Outbox\SweepOrderOutbox;
use AIArmada\Orders\Enums\OutboxStatus;
use AIArmada\Orders\Events\OrderProcessingStarted;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\OrdersServiceProvider;
use AIArmada\Orders\States\PendingPayment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionServiceProvider;

function makeOutboxSweepOrder(string $number): Order
{
    $order = new Order;
    $order->forceFill([
        'order_number' => $number,
        'status' => PendingPayment::class,
        'currency' => 'USD',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);
    $order->save();

    return $order;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function insertOutboxSweepRow(Order $order, array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::table(config('orders.database.tables.order_outbox', 'order_outbox_messages'))->insert([
        'id' => $id,
        'order_id' => $order->getKey(),
        'owner_type' => $order->owner_type,
        'owner_id' => $order->owner_id,
        'event_class' => OrderProcessingStarted::class,
        'transaction_id' => 'txn-sweep-1',
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

function outboxSweepTable(): string
{
    return config('orders.database.tables.order_outbox', 'order_outbox_messages');
}

it('requeues rows stuck in relaying past the claim timeout', function (): void {
    config()->set('orders.outbox.claim_timeout_seconds', 600);

    $order = makeOutboxSweepOrder('ORD-SWEEP-1');

    $stuckId = insertOutboxSweepRow($order, [
        'status' => OutboxStatus::Relaying->value,
        'attempts' => 1,
        'claimed_at' => CarbonImmutable::now()->subMinutes(20),
    ]);

    $freshId = insertOutboxSweepRow($order, [
        'status' => OutboxStatus::Relaying->value,
        'attempts' => 1,
        'claimed_at' => CarbonImmutable::now()->subMinute(),
    ]);

    $result = SweepOrderOutbox::run();

    expect($result['requeued'])->toBe(1)
        ->and(DB::table(outboxSweepTable())->find($stuckId)->status)->toBe(OutboxStatus::Pending->value)
        ->and(DB::table(outboxSweepTable())->find($stuckId)->claimed_at)->toBeNull()
        ->and(DB::table(outboxSweepTable())->find($freshId)->status)->toBe(OutboxStatus::Relaying->value);
});

it('purges relayed rows past retention and keeps recent ones', function (): void {
    config()->set('orders.outbox.retention_days', 30);

    $order = makeOutboxSweepOrder('ORD-SWEEP-2');

    $oldId = insertOutboxSweepRow($order, [
        'status' => OutboxStatus::Relayed->value,
        'relayed_at' => CarbonImmutable::now()->subDays(31),
    ]);

    $recentId = insertOutboxSweepRow($order, [
        'status' => OutboxStatus::Relayed->value,
        'relayed_at' => CarbonImmutable::now()->subDay(),
    ]);

    $result = SweepOrderOutbox::run();

    expect($result['purged'])->toBe(1)
        ->and(DB::table(outboxSweepTable())->find($oldId))->toBeNull()
        ->and(DB::table(outboxSweepTable())->find($recentId))->not->toBeNull();
});

it('reports dead rows without touching them', function (): void {
    $order = makeOutboxSweepOrder('ORD-SWEEP-3');

    $deadId = insertOutboxSweepRow($order, [
        'status' => OutboxStatus::Dead->value,
        'attempts' => 10,
        'last_error' => 'boom',
    ]);

    $result = SweepOrderOutbox::run();

    expect($result['dead'])->toBe(1)
        ->and(DB::table(outboxSweepTable())->find($deadId)->status)->toBe(OutboxStatus::Dead->value);
});

it('runs as an artisan command', function (): void {
    $order = makeOutboxSweepOrder('ORD-SWEEP-4');

    insertOutboxSweepRow($order, [
        'status' => OutboxStatus::Relaying->value,
        'attempts' => 1,
        'claimed_at' => CarbonImmutable::now()->subMinutes(20),
    ]);

    // ActionServiceProvider is discovery-registered in host apps; the
    // suite TestCase only loads explicit providers.
    app()->register(ActionServiceProvider::class);
    app()->register(OrdersServiceProvider::class);

    $this->artisan('orders:outbox-sweep')
        ->expectsOutputToContain('requeued: 1')
        ->assertSuccessful();
});

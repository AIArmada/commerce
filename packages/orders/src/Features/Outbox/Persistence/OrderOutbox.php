<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Outbox\Persistence;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Orders\Contracts\OrderOutboxWriter;
use AIArmada\Orders\Data\OutboxStatus;
use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Orders\Events\OrderProcessingStarted;
use AIArmada\Orders\Features\Outbox\Models\OrderOutboxMessage;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Fraud;
use AIArmada\Orders\States\Refunded;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * In-transaction staging for the order outbox.
 */
final readonly class OrderOutbox implements OrderOutboxWriter
{
    /**
     * Stage one replayable event row. Call inside the caller's transaction
     * so the row commits atomically with the order state change.
     *
     * @return string|null The staged row id, or null when the outbox is disabled.
     */
    public function stage(string $orderId, string $eventClass, string $transactionId, string $gateway): ?string
    {
        if (! config('orders.outbox.enabled', true)) {
            return null;
        }

        if (! in_array($eventClass, OrderOutboxMessage::REPLAYABLE_EVENTS, true)) {
            throw new InvalidArgumentException("Event class is not replayable through the order outbox: {$eventClass}.");
        }

        $order = Order::query()->forOwner()->findOrFail($orderId);

        // Require the caller's matching owner context before staging.
        return OwnerContext::withOwner(OwnerContext::fromTypeAndIdOrFail($order->owner_type, $order->owner_id), static fn (): string => OrderOutboxMessage::query()->create([
            'order_id' => $order->getKey(),
            'owner_type' => $order->owner_type,
            'owner_id' => $order->owner_id,
            'event_class' => $eventClass,
            'transaction_id' => $transactionId,
            'gateway' => $gateway,
            'status' => OutboxStatus::Pending,
        ])->getKey());
    }

    /**
     * Terminally suppress an order's unrelayed rows. Call inside the
     * caller's transaction (cancel, full refund) so suppression commits
     * atomically with the lifecycle change: the relay's conditional
     * claim only picks up pending/failed rows, so a suppressed row can
     * never dispatch after cleanup ran.
     */
    public function suppressForOrder(string $orderId, string $reason): int
    {
        Order::query()->forOwner()->findOrFail($orderId);

        return OrderOutboxMessage::query()
            ->forOwner()
            ->where('order_id', $orderId)
            ->whereIn('status', [OutboxStatus::Pending->value, OutboxStatus::Failed->value])
            ->update([
                'status' => OutboxStatus::Suppressed->value,
                'claimed_at' => null,
                'next_retry_at' => null,
                'last_error' => $reason,
                'updated_at' => CarbonImmutable::now(),
            ]);
    }

    /**
     * Live-dispatch staged rows, serialized with lifecycle changes. Call
     * from an after-commit hook: the order is re-locked and its status
     * re-read, so a cancel, full refund, or fraud flag that committed
     * first wins (replayable dispatch skipped — the lifecycle transition
     * already suppressed the rows) while a later lifecycle change lands
     * after the dispatch, same as ever. Rows are marked relayed only
     * when the dispatch actually runs.
     *
     * @param  list<string|null>  $ids
     */
    public function dispatchReplayable(string $orderId, array $ids, string $transactionId, string $gateway): void
    {
        (new Order)->getConnection()->transaction(function () use ($orderId, $ids, $transactionId, $gateway): void {
            $locked = Order::query()
                ->withoutGlobalScope(OwnerScope::class)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof Order
                || $locked->status instanceof Canceled
                || $locked->status instanceof Refunded
                || $locked->status instanceof Fraud
                || ($locked->hasOwner() && $locked->owner === null)) {
                return;
            }

            OwnerContext::withOwner($locked->owner, function () use ($locked, $ids, $transactionId, $gateway): void {
                // Disabled outbox: no durable rows were staged.
                if ($ids === [null, null]) {
                    event(new OrderProcessingStarted($locked, $transactionId, $gateway));
                    event(new OrderFulfillmentRequired($locked, $transactionId, $gateway));

                    return;
                }

                $rows = OrderOutboxMessage::query()
                    ->withoutGlobalScope(OwnerScope::class)
                    ->where('order_id', $locked->getKey())
                    ->whereIn('id', array_values(array_filter($ids)))
                    ->where('status', OutboxStatus::Pending->value)
                    ->where('owner_type', $locked->owner_type)
                    ->where('owner_id', $locked->owner_id)
                    ->where('transaction_id', $transactionId)
                    ->where('gateway', $gateway)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // Preserve staging order and skip rows already claimed or delivered
                // by a relay worker before the after-commit callback ran.
                foreach (array_unique(array_filter($ids)) as $id) {
                    $row = $rows->get($id);
                    if (! $row instanceof OrderOutboxMessage || ! in_array($row->event_class, OrderOutboxMessage::REPLAYABLE_EVENTS, true)) {
                        continue;
                    }

                    $eventClass = $row->event_class;
                    event(new $eventClass($locked, $transactionId, $gateway));

                    $row->forceFill([
                        'status' => OutboxStatus::Relayed,
                        'relayed_at' => CarbonImmutable::now(),
                        'claimed_at' => null,
                        'next_retry_at' => null,
                        'last_error' => null,
                    ])->save();
                }
            });
        });
    }
}

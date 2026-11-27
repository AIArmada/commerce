<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Outbox\Actions;

use AIArmada\Orders\Data\OutboxStatus;
use AIArmada\Orders\Features\Outbox\Models\OrderOutboxMessage;
use Carbon\CarbonImmutable;

/**
 * Reconcile the order outbox: requeue rows stuck in relaying (a relayer
 * crashed mid-row), purge relayed history past retention, and report dead
 * rows for operator review. Never repairs or invents rows.
 */
final readonly class SweepOrderOutbox
{
    /**
     * @return array{requeued: int, purged: int, dead: int}
     */
    public function execute(): array
    {
        // Intentionally cross-tenant: the sweep is a system operation over
        // row state, not owner data.
        $requeued = OrderOutboxMessage::query()
            ->system()
            ->stuckRelaying(
                CarbonImmutable::now()->subSeconds((int) config('orders.outbox.claim_timeout_seconds', 600))
            )
            ->update([
                'status' => OutboxStatus::Pending->value,
                'claimed_at' => null,
                'updated_at' => CarbonImmutable::now(),
            ]);

        $purged = 0;
        $retentionDays = (int) config('orders.outbox.retention_days', 30);

        if ($retentionDays > 0) {
            $purged = OrderOutboxMessage::query()
                ->system()
                ->relayedBefore(CarbonImmutable::now()->subDays($retentionDays))
                ->delete();
        }

        $dead = OrderOutboxMessage::query()
            ->system()
            ->dead()
            ->count();

        return ['requeued' => $requeued, 'purged' => $purged, 'dead' => $dead];
    }
}

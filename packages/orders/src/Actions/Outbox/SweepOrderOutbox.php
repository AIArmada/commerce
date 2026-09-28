<?php

declare(strict_types=1);

namespace AIArmada\Orders\Actions\Outbox;

use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Orders\Enums\OutboxStatus;
use AIArmada\Orders\Models\OrderOutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reconcile the order outbox: requeue rows stuck in relaying (a relayer
 * crashed mid-row), purge relayed history past retention, and report dead
 * rows for operator review. Never repairs or invents rows.
 */
final class SweepOrderOutbox
{
    use AsAction;

    public string $commandSignature = 'orders:outbox-sweep';

    public string $commandDescription = 'Reconcile stuck order outbox rows and purge relayed history.';

    /**
     * @return array{requeued: int, purged: int, dead: int}
     */
    public function handle(): array
    {
        // Intentionally cross-tenant: the sweep is a system operation over
        // row state, not owner data.
        $requeued = OrderOutboxMessage::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('status', OutboxStatus::Relaying->value)
            ->where(
                'claimed_at',
                '<',
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
                ->withoutGlobalScope(OwnerScope::class)
                ->where('status', OutboxStatus::Relayed->value)
                ->where('relayed_at', '<', CarbonImmutable::now()->subDays($retentionDays))
                ->delete();
        }

        $dead = OrderOutboxMessage::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('status', OutboxStatus::Dead->value)
            ->count();

        return ['requeued' => $requeued, 'purged' => $purged, 'dead' => $dead];
    }

    public function asCommand(Command $command): void
    {
        $result = $this->handle();

        $command->info(sprintf(
            'Outbox sweep complete — requeued: %d, purged: %d, dead: %d.',
            $result['requeued'],
            $result['purged'],
            $result['dead'],
        ));

        if ($result['dead'] > 0) {
            $command->warn(sprintf('%d dead outbox message(s) need operator review.', $result['dead']));
        }
    }
}

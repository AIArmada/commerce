<?php

declare(strict_types=1);

namespace AIArmada\Orders\EntryPoints\Commands;

use AIArmada\Orders\Actions\Outbox\SweepOrderOutbox;
use Illuminate\Console\Command;

final class SweepOrderOutboxCommand extends Command
{
    protected $signature = 'orders:outbox-sweep';

    protected $description = 'Reconcile stuck order outbox rows and purge relayed history.';

    public function handle(SweepOrderOutbox $action): int
    {
        $result = $action->execute();

        $this->info(sprintf(
            'Outbox sweep complete — requeued: %d, purged: %d, dead: %d.',
            $result['requeued'],
            $result['purged'],
            $result['dead'],
        ));

        if ($result['dead'] > 0) {
            $this->warn(sprintf('%d dead outbox message(s) need operator review.', $result['dead']));
        }
        return self::SUCCESS;
    }
}

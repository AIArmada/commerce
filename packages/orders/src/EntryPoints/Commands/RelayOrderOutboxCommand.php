<?php

declare(strict_types=1);

namespace AIArmada\Orders\EntryPoints\Commands;

use AIArmada\Orders\Actions\Outbox\RelayOrderOutbox;
use Illuminate\Console\Command;

final class RelayOrderOutboxCommand extends Command
{
    protected $signature = 'orders:outbox-relay {--limit= : Maximum rows to relay}';

    protected $description = 'Relay pending order outbox messages missed by live dispatch.';

    public function handle(RelayOrderOutbox $action): int
    {
        $limit = $this->option('limit');

        if ($limit !== null && (filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
            $this->error('The --limit option must be a positive integer.');

            return self::INVALID;
        }

        $result = $action->execute($limit !== null ? (int) $limit : null);

        $this->info(sprintf(
            'Outbox relay complete — relayed: %d, failed: %d, dead: %d, skipped: %d, suppressed: %d.',
            $result['relayed'],
            $result['failed'],
            $result['dead'],
            $result['skipped'],
            $result['suppressed'],
        ));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Communications\Console\Commands;

use AIArmada\Communications\Services\NotificationInboxService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class PruneNotificationInboxesCommand extends Command
{
    protected $signature = 'communications:prune-inboxes
        {--before= : Prune inboxes archived before this date}
        {--dry-run : Preview without deleting}';

    protected $description = 'Prune old archived notification inbox entries';

    public function __construct(
        private readonly NotificationInboxService $inboxService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $before = $this->option('before')
            ? CarbonImmutable::parse($this->option('before'))
            : CarbonImmutable::now()->subDays(90);

        $this->info("Pruning inboxes archived before: {$before->toDateTimeString()}");

        if ($this->option('dry-run')) {
            $count = $this->inboxService->countPrunable($before);

            $this->info("Would prune {$count} inbox entries.");

            return self::SUCCESS;
        }

        $pruned = $this->inboxService->prune($before);

        $this->info("Pruned {$pruned} inbox entries.");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Links\Console\Commands;

use AIArmada\Links\Models\LinkClick;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class PruneLinkClicksCommand extends Command
{
    protected $signature = 'links:prune-clicks
        {--days= : Delete clicks older than this many days. Defaults to links.retention.prune_clicks_after_days.}';

    protected $description = 'Delete tracked link clicks older than the retention window.';

    public function handle(): int
    {
        $option = $this->option('days');
        $days = $option !== null && $option !== ''
            ? (int) $option
            : config('links.features.retention.prune_clicks_after_days');

        if (! is_int($days) || $days <= 0) {
            $this->info('Click pruning is disabled.');

            return self::SUCCESS;
        }

        $cutoff = CarbonImmutable::now()->subDays($days);
        $pruned = 0;

        do {
            $deleted = LinkClick::query()
                ->withoutOwnerScope()
                ->where('occurred_at', '<', $cutoff)
                ->limit(1000)
                ->delete();

            $pruned += $deleted;
        } while ($deleted > 0);

        $this->info("Pruned {$pruned} link click(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}

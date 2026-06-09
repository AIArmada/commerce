<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Support\WebhookOwnerBatchRunner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CleanWebhooksCommand extends Command
{
    protected $signature = 'chip:clean-webhooks
                            {--days=30 : Delete webhooks older than this many days}
                            {--status=processed : Only delete webhooks with this status (processed, failed, all)}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clean old webhook records from the database';

    public function handle(WebhookOwnerBatchRunner $batchRunner): int
    {
        $days = (int) $this->option('days');
        $status = (string) $this->option('status');
        $cutoffDate = CarbonImmutable::now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        return $batchRunner->run(function (?Model $owner, ?int $_remainingLimit = null) use ($cutoffDate, $status, $days, $dryRun): array {
            return $this->handleForOwner($cutoffDate, $status, $days, $dryRun, $owner);
        })['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int}
     */
    private function handleForOwner(CarbonImmutable $cutoffDate, string $status, int $days, bool $dryRun, ?Model $owner): array
    {
        $query = $this->buildQuery($cutoffDate, $status, $owner);
        $count = $query->count();

        if ($count === 0) {
            $this->info('No webhooks found matching the criteria.');

            return ['processed' => 0, 'succeeded' => 0, 'failed' => 0];
        }

        $this->info("Found {$count} webhook(s) older than {$days} days with status '{$status}'.");

        if ($dryRun) {
            $this->warn('Dry run mode - no webhooks will be deleted.');

            return ['processed' => $count, 'succeeded' => 0, 'failed' => 0];
        }

        if (! $this->confirm("Are you sure you want to delete {$count} webhook records?")) {
            $this->info('Operation cancelled.');

            return ['processed' => 0, 'succeeded' => 0, 'failed' => 0];
        }

        $deleted = $query->delete();

        $this->info("Successfully deleted {$deleted} webhook record(s).");

        return ['processed' => $count, 'succeeded' => $deleted, 'failed' => 0];
    }

    private function buildQuery(CarbonImmutable $cutoffDate, string $status, ?Model $owner): Builder
    {
        $query = Webhook::query()
            ->forOwner($owner, false)
            ->where('created_at', '<', $cutoffDate);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return $query;
    }
}

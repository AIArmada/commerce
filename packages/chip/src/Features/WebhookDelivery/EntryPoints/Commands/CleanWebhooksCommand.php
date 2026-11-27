<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\WebhookDelivery\EntryPoints\Commands;

use AIArmada\Chip\Features\WebhookDelivery\Actions\CleanWebhooks;
use AIArmada\Chip\Features\WebhookDelivery\Support\WebhookOwnerBatchRunner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

final class CleanWebhooksCommand extends Command
{
    protected $signature = 'chip:clean-webhooks
                            {--days=30 : Delete webhooks older than this many days}
                            {--status=processed : Only delete webhooks with this status (processed, failed, superseded, all)}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clean old webhook records from the database';

    public function handle(WebhookOwnerBatchRunner $batchRunner, CleanWebhooks $action): int
    {
        $days = (int) $this->option('days');
        $status = (string) $this->option('status');
        if ($days < 1 || ! in_array($status, ['processed', 'failed', 'superseded', 'all'], true)) {
            $this->error('Days must be positive and status must be processed, failed, superseded, or all.');

            return self::FAILURE;
        }

        $cutoffDate = CarbonImmutable::now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        return $batchRunner->run(function (?Model $owner, ?int $_remainingLimit = null) use ($action, $cutoffDate, $status, $days, $dryRun): array {
            return $this->handleForOwner($action, $cutoffDate, $status, $days, $dryRun, $owner);
        })['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int}
     */
    private function handleForOwner(CleanWebhooks $action, CarbonImmutable $cutoffDate, string $status, int $days, bool $dryRun, ?Model $owner): array
    {
        $count = $action->count($cutoffDate, $status, $owner);

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

        $deleted = $action->handle($cutoffDate, $status, $owner);

        $this->info("Successfully deleted {$deleted} webhook record(s).");

        return ['processed' => $count, 'succeeded' => $deleted, 'failed' => 0];
    }
}

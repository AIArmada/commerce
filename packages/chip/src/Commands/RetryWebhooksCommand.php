<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Support\WebhookOwnerBatchRunner;
use AIArmada\Chip\Webhooks\WebhookRetryManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

final class RetryWebhooksCommand extends Command
{
    protected $signature = 'chip:retry-webhooks
                            {--limit=100 : Maximum number of webhooks to retry}
                            {--dry-run : Show what would be retried without actually processing}';

    protected $description = 'Retry failed webhooks with exponential backoff';

    public function handle(WebhookRetryManager $retryManager, WebhookOwnerBatchRunner $batchRunner): int
    {
        $limit = max(0, (int) $this->option('limit'));

        $totals = $batchRunner->run(function (?Model $_owner, ?int $remainingLimit = null) use ($retryManager): array {
            return $this->processRetries($retryManager, $remainingLimit ?? (int) $this->option('limit'));
        }, $limit);

        $this->info("Retry complete: {$totals['succeeded']} succeeded, {$totals['failed']} failed.");

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int}
     */
    private function processRetries(WebhookRetryManager $retryManager, int $limit): array
    {
        $webhooks = $retryManager->getRetryableWebhooks()
            ->take($limit);

        if ($webhooks->isEmpty()) {
            $this->info('No webhooks are eligible for retry.');

            return [
                'processed' => 0,
                'succeeded' => 0,
                'failed' => 0,
            ];
        }

        $this->info("Found {$webhooks->count()} webhook(s) eligible for retry.");

        if ($this->option('dry-run')) {
            $this->warn('Dry run mode - no webhooks will be processed.');
            $this->newLine();

            $this->table(
                ['ID', 'Event', 'Retry Count', 'Last Error'],
                $webhooks->map(fn ($w) => [
                    $w->id,
                    $w->event,
                    $w->retry_count,
                    Str::limit($w->last_error ?? 'N/A', 50),
                ])->toArray()
            );

            return [
                'processed' => $webhooks->count(),
                'succeeded' => 0,
                'failed' => 0,
            ];
        }

        $this->newLine();

        $succeeded = 0;
        $failed = 0;

        foreach ($webhooks as $webhook) {
            $this->line("Retrying webhook {$webhook->id} ({$webhook->event})...");

            try {
                $result = $retryManager->retry($webhook);

                if ($result->isSuccess()) {
                    $this->info('  ✓ Retry succeeded');
                    $succeeded++;
                } else {
                    $this->warn("  ✗ Retry failed: {$result->message}");
                    $failed++;
                }
            } catch (Throwable $e) {
                $this->error("  ✗ Error: {$e->getMessage()}");
                $failed++;
                report($e);
            }
        }

        $this->newLine();

        return [
            'processed' => $webhooks->count(),
            'succeeded' => $succeeded,
            'failed' => $failed,
        ];
    }
}

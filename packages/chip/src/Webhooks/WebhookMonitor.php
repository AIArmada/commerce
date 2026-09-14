<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Data\WebhookHealth;
use AIArmada\Chip\Models\Webhook;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Monitors webhook health and provides statistics.
 */
class WebhookMonitor
{
    /**
     * Get webhook health metrics for the last 24 hours.
     */
    public function getHealth(?CarbonImmutable $since = null): WebhookHealth
    {
        $since ??= CarbonImmutable::now()->subDay();

        $stats = Webhook::query()
            ->forOwner()
            ->where('created_at', '>=', $since)
            ->toBase()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                AVG(processing_time_ms) as avg_processing_time_ms
            ")
            ->first();

        return WebhookHealth::fromStats(
            total: (int) ($stats->total ?? 0),
            processed: (int) ($stats->processed ?? 0),
            failed: (int) ($stats->failed ?? 0),
            pending: (int) ($stats->pending ?? 0),
            avgProcessingTimeMs: (float) ($stats->avg_processing_time_ms ?? 0),
        );
    }

    /**
     * Get event distribution for the last 24 hours.
     *
     * @return array<string, int>
     */
    public function getEventDistribution(?CarbonImmutable $since = null): array
    {
        $since ??= CarbonImmutable::now()->subDay();

        return Webhook::query()
            ->forOwner()
            ->where('created_at', '>=', $since)
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();
    }

    /**
     * Get failed webhooks count by error reason.
     *
     * @return array<string, int>
     */
    public function getFailureBreakdown(?CarbonImmutable $since = null): array
    {
        $since ??= CarbonImmutable::now()->subDay();

        return Webhook::query()
            ->forOwner()
            ->where('created_at', '>=', $since)
            ->where('status', 'failed')
            ->selectRaw("COALESCE(last_error, 'Unknown') as error, COUNT(*) as count")
            ->groupBy('error')
            ->pluck('count', 'error')
            ->toArray();
    }

    /**
     * Get hourly webhook volume for the last 24 hours.
     *
     * Uses PHP-based grouping for database portability (works with MySQL, PostgreSQL, SQLite).
     *
     * @return array<string, array{total: int, processed: int, failed: int}>
     */
    public function getHourlyVolume(?CarbonImmutable $since = null): array
    {
        $since ??= CarbonImmutable::now()->subDay();

        // Group in PHP for database portability, but stream in chunks so a
        // busy day never hydrates every webhook row at once.
        /** @var array<string, array{total: int, processed: int, failed: int}> $buckets */
        $buckets = [];

        Webhook::query()
            ->forOwner()
            ->where('created_at', '>=', $since)
            ->select(['created_at', 'status'])
            ->orderBy('id')
            ->chunk(1000, function ($webhooks) use (&$buckets): void {
                foreach ($webhooks as $webhook) {
                    $hour = CarbonImmutable::parse($webhook->created_at)->format('Y-m-d H:00:00');

                    $buckets[$hour] ??= ['total' => 0, 'processed' => 0, 'failed' => 0];
                    $buckets[$hour]['total']++;

                    if ($webhook->status === 'processed') {
                        $buckets[$hour]['processed']++;
                    } elseif ($webhook->status === 'failed') {
                        $buckets[$hour]['failed']++;
                    }
                }
            });

        ksort($buckets);

        return $buckets;
    }

    /**
     * Get pending webhooks that haven't been processed.
     *
     * @return Collection<int, Webhook>
     */
    public function getPendingWebhooks(int $limit = 100): Collection
    {
        return Webhook::query()
            ->forOwner()
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recently failed webhooks.
     *
     * @return Collection<int, Webhook>
     */
    public function getRecentFailures(int $limit = 50): Collection
    {
        return Webhook::query()
            ->forOwner()
            ->where('status', 'failed')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}

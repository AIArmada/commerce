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
        $since ??= now()->subDay();

        $stats = Webhook::query()
            ->forOwner()
            ->where('created_at', '>=', $since)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                AVG(processing_time_ms) as avg_processing_time
            ")
            ->first();

        return WebhookHealth::fromStats(
            total: (int) ($stats->total ?? 0),
            processed: (int) ($stats->processed ?? 0),
            failed: (int) ($stats->failed ?? 0),
            pending: (int) ($stats->pending ?? 0),
            avgProcessingTimeMs: (float) ($stats->avg_processing_time ?? 0),
        );
    }

    /**
     * Get event distribution for the last 24 hours.
     *
     * @return array<string, int>
     */
    public function getEventDistribution(?CarbonImmutable $since = null): array
    {
        $since ??= now()->subDay();

        return Webhook::query()
            ->forOwner()
            ->where('created_at', '>=', $since)
            ->selectRaw('event, COUNT(*) as count')
            ->groupBy('event')
            ->pluck('count', 'event')
            ->toArray();
    }

    /**
     * Get failed webhooks count by error reason.
     *
     * @return array<string, int>
     */
    public function getFailureBreakdown(?CarbonImmutable $since = null): array
    {
        $since ??= now()->subDay();

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
        $since ??= now()->subDay();

        // Fetch raw data and group in PHP for database portability
        $webhooks = Webhook::query()
            ->forOwner()
            ->where('created_at', '>=', $since)
            ->select(['created_at', 'status'])
            ->get();

        return $webhooks
            ->groupBy(fn ($webhook): string => CarbonImmutable::parse($webhook->created_at)->format('Y-m-d H:00:00'))
            ->map(fn ($group): array => [
                'total' => $group->count(),
                'processed' => $group->where('status', 'processed')->count(),
                'failed' => $group->where('status', 'failed')->count(),
            ])
            ->sortKeys()
            ->toArray();
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

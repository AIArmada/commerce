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

        $webhooks = Webhook::query()
            ->forOwner()
            ->where('created_at', '>=', $since)
            ->select(['status', 'processing_time_ms'])
            ->get();

        $processingTimes = $webhooks
            ->pluck('processing_time_ms')
            ->filter(static fn (mixed $value): bool => $value !== null);

        return WebhookHealth::fromStats(
            total: $webhooks->count(),
            processed: $webhooks->where('status', 'processed')->count(),
            failed: $webhooks->where('status', 'failed')->count(),
            pending: $webhooks->where('status', 'pending')->count(),
            avgProcessingTimeMs: (float) ($processingTimes->avg() ?? 0),
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

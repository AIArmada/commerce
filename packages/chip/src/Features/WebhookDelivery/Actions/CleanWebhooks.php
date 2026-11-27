<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\WebhookDelivery\Actions;

use AIArmada\Chip\Features\WebhookDelivery\Models\Webhook;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class CleanWebhooks
{
    public function count(CarbonImmutable $cutoffDate, string $status, ?Model $owner): int
    {
        return $this->buildQuery($cutoffDate, $status, $owner)->count();
    }

    public function handle(CarbonImmutable $cutoffDate, string $status, ?Model $owner): int
    {
        return $this->buildQuery($cutoffDate, $status, $owner)->delete();
    }

    private function buildQuery(CarbonImmutable $cutoffDate, string $status, ?Model $owner): Builder
    {
        if (! in_array($status, ['processed', 'failed', 'superseded', 'all'], true) || $cutoffDate->greaterThanOrEqualTo(CarbonImmutable::now())) {
            throw new InvalidArgumentException('A past cutoff date and a valid webhook status are required.');
        }

        $query = Webhook::query()
            ->forOwner($owner, false)
            ->where('created_at', '<', $cutoffDate)
            ->where('status', '!=', 'processing');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return $query;
    }
}

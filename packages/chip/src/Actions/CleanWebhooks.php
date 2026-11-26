<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions;

use AIArmada\Chip\Models\Webhook;
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
        if (! in_array($status, ['processed', 'failed', 'all'], true) || $cutoffDate->greaterThanOrEqualTo(CarbonImmutable::now())) {
            throw new InvalidArgumentException('A past cutoff date and a valid webhook status are required.');
        }

        $query = Webhook::query()
            ->forOwner($owner, false)
            ->where('created_at', '<', $cutoffDate);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return $query;
    }
}

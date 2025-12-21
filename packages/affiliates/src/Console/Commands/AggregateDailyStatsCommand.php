<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Events\DailyStatsAggregated;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\DailyAggregationService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class AggregateDailyStatsCommand extends Command
{
    protected $signature = 'affiliates:aggregate-daily
                            {--date= : The date to aggregate (Y-m-d format, defaults to yesterday)}
                            {--backfill : Backfill from the start of data}
                            {--from= : Start date for backfill (Y-m-d)}
                            {--to= : End date for backfill (Y-m-d)}';

    protected $description = 'Aggregate daily affiliate statistics';

    public function handle(DailyAggregationService $service): int
    {
        if ($this->option('backfill')) {
            return $this->handleBackfill($service);
        }

        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now()->subDay();

        $this->info("Aggregating stats for {$date->toDateString()}...");

        $count = $this->processForOwners(fn (): int => $service->aggregate($date));

        $this->info("Aggregated stats for {$count} affiliates.");

        event(new DailyStatsAggregated($date, $count));

        return self::SUCCESS;
    }

    private function handleBackfill(DailyAggregationService $service): int
    {
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))
            : now()->subDays(30);

        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))
            : now()->subDay();

        $this->info("Backfilling stats from {$from->toDateString()} to {$to->toDateString()}...");

        $totalProcessed = $this->processForOwners(fn (): int => $service->backfill($from, $to));

        $this->info("Backfill complete. Processed {$totalProcessed} affiliate-days.");

        return self::SUCCESS;
    }

    private function processForOwners(callable $callback): int
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return (int) $callback();
        }

        $owner = OwnerContext::resolve();
        if ($owner !== null) {
            return (int) $callback();
        }

        $owners = Affiliate::query()
            ->withoutOwnerScope()
            ->select(['owner_type', 'owner_id'])
            ->distinct()
            ->get();

        if ($owners->isEmpty()) {
            return (int) $callback();
        }

        $total = 0;

        foreach ($owners as $row) {
            $owner = $this->resolveOwnerFromRow($row);
            $total += (int) OwnerContext::withOwner($owner, $callback);
        }

        return $total;
    }

    private function resolveOwnerFromRow(object $row): ?Model
    {
        $ownerType = $row->owner_type ?? null;
        $ownerId = $row->owner_id ?? null;

        return OwnerContext::fromTypeAndId(
            is_string($ownerType) ? $ownerType : null,
            is_string($ownerId) || is_int($ownerId) ? $ownerId : null
        );
    }
}

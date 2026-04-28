<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Events\DailyStatsAggregated;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\DailyAggregationService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use Illuminate\Console\Command;
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

        $columns = OwnerTupleColumns::forModelClass(Affiliate::class);

        $owners = Affiliate::query()
            ->withoutOwnerScope()
            ->select([$columns->ownerTypeColumn, $columns->ownerIdColumn])
            ->distinct()
            ->get();

        if ($owners->isEmpty()) {
            return (int) OwnerContext::withOwner(null, $callback);
        }

        $includeGlobal = (bool) config('affiliates.owner.include_global', false);
        if ($includeGlobal) {
            config()->set('affiliates.owner.include_global', false);
        }

        $total = 0;
        $processedGlobal = false;

        try {
            foreach ($owners as $row) {
                $parsed = OwnerTupleParser::fromRow($row, $columns);

                if ($parsed->isExplicitGlobal()) {
                    if ($processedGlobal) {
                        continue;
                    }

                    $processedGlobal = true;
                }

                $total += (int) OwnerContext::withOwner($parsed->toOwnerModel(), $callback);
            }
        } finally {
            if ($includeGlobal) {
                config()->set('affiliates.owner.include_global', true);
            }
        }

        return $total;
    }
}

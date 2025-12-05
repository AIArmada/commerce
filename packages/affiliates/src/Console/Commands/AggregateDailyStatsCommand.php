<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Events\DailyStatsAggregated;
use AIArmada\Affiliates\Services\DailyAggregationService;
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

        $count = $service->aggregate($date);

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

        $totalProcessed = $service->backfill($from, $to);

        $this->info("Backfill complete. Processed {$totalProcessed} affiliate-days.");

        return self::SUCCESS;
    }
}

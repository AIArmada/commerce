<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Events\DailyStatsAggregated;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\DailyAggregationService;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

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
            ? CarbonImmutable::parse($this->option('date'))
            : CarbonImmutable::now()->subDay();

        $this->info("Aggregating stats for {$date->toDateString()}...");

        $runner = new OwnerBatchRunner(
            Affiliate::class,
            ['enabled' => 'affiliates.owner.enabled', 'include_global' => 'affiliates.owner.include_global'],
        );

        $count = $runner->run(fn (): int => $service->aggregate($date)) ?? 0;

        $this->info("Aggregated stats for {$count} affiliates.");

        event(new DailyStatsAggregated($date, $count));

        return self::SUCCESS;
    }

    private function handleBackfill(DailyAggregationService $service): int
    {
        $from = $this->option('from')
            ? CarbonImmutable::parse($this->option('from'))
            : CarbonImmutable::now()->subDays(30);

        $to = $this->option('to')
            ? CarbonImmutable::parse($this->option('to'))
            : CarbonImmutable::now()->subDay();

        $this->info("Backfilling stats from {$from->toDateString()} to {$to->toDateString()}...");

        $runner = new OwnerBatchRunner(
            Affiliate::class,
            ['enabled' => 'affiliates.owner.enabled', 'include_global' => 'affiliates.owner.include_global'],
        );

        $totalProcessed = $runner->run(fn (): int => $service->backfill($from, $to)) ?? 0;

        $this->info("Backfill complete. Processed {$totalProcessed} affiliate-days.");

        return self::SUCCESS;
    }
}

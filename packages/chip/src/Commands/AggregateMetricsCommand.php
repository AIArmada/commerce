<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Services\MetricsAggregator;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

final class AggregateMetricsCommand extends Command
{
    protected $signature = 'chip:aggregate-metrics
                            {--date= : Specific date to aggregate (YYYY-MM-DD)}
                            {--from= : Start date for backfill (YYYY-MM-DD)}
                            {--to= : End date for backfill (YYYY-MM-DD)}';

    protected $description = 'Aggregate purchase metrics into daily summaries';

    public function handle(MetricsAggregator $aggregator): int
    {
        if ((bool) config('chip.owner.enabled', false) && OwnerContext::resolve() === null) {
            $owners = Purchase::query()
                ->withoutOwnerScope()
                ->select(['owner_type', 'owner_id'])
                ->distinct()
                ->get();

            if ($owners->isEmpty()) {
                $this->runAggregation($aggregator);

                return self::SUCCESS;
            }

            foreach ($owners as $row) {
                $owner = $this->resolveOwnerFromRow($row);

                OwnerContext::withOwner($owner, function () use ($aggregator): void {
                    $this->runAggregation($aggregator);
                });
            }

            return self::SUCCESS;
        }

        $this->runAggregation($aggregator);

        return self::SUCCESS;
    }

    private function runAggregation(MetricsAggregator $aggregator): void
    {
        // Specific date
        if ($dateOption = $this->option('date')) {
            $date = CarbonImmutable::parse($dateOption);
            $this->info("Aggregating metrics for {$date->toDateString()}...");

            $aggregator->aggregateForDate($date);

            $this->info('Done.');

            return;
        }

        // Date range (backfill)
        if ($this->option('from') && $this->option('to')) {
            $from = CarbonImmutable::parse($this->option('from'));
            $to = CarbonImmutable::parse($this->option('to'));

            $this->info("Backfilling metrics from {$from->toDateString()} to {$to->toDateString()}...");

            $days = $aggregator->backfill($from, $to);

            $this->info("Aggregated metrics for {$days} day(s).");

            return;
        }

        // Default: aggregate yesterday
        $yesterday = CarbonImmutable::yesterday();
        $this->info("Aggregating metrics for {$yesterday->toDateString()} (yesterday)...");

        $aggregator->aggregateForDate($yesterday);

        $this->info('Done.');
    }

    private function resolveOwnerFromRow(object $row): ?Model
    {
        return OwnerTupleParser::fromRow($row, OwnerTupleColumns::forModelClass(Purchase::class))->toOwnerModel();
    }
}

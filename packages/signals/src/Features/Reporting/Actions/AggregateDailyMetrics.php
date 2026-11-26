<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Reporting\Actions;

use AIArmada\Signals\Features\Ingestion\Api\DailyTelemetry;
use AIArmada\Signals\Features\Ingestion\Api\DailyTelemetryRead;
use AIArmada\Signals\Features\Reporting\Models\SignalDailyMetric;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

final readonly class AggregateDailyMetrics
{
    public function __construct(private DailyTelemetryRead $telemetry) {}

    public function aggregateForDate(Carbon $date, string $propertyId): SignalDailyMetric
    {
        return $this->persist($this->telemetry->forProperty(CarbonImmutable::instance($date), $propertyId));
    }

    public function aggregateRange(Carbon $from, Carbon $to): int
    {
        $count = 0;

        foreach ($this->telemetry->forRange(CarbonImmutable::instance($from), CarbonImmutable::instance($to)) as $row) {
            $this->persist($row);
            $count++;
        }

        return $count;
    }

    public function aggregateForOwners(Carbon $from, Carbon $to): int
    {
        return $this->telemetry->forOwners(fn (): int => $this->aggregateRange($from, $to));
    }

    private function persist(DailyTelemetry $row): SignalDailyMetric
    {
        return SignalDailyMetric::query()->forOwner()->updateOrCreate(
            ['tracked_property_id' => $row->propertyId, 'date' => $row->date],
            [
                'owner_type' => $row->ownerType,
                'owner_id' => $row->ownerId,
                'unique_identities' => $row->identities,
                'sessions' => $row->sessions,
                'bounced_sessions' => $row->bouncedSessions,
                'page_views' => $row->pageViews,
                'events' => $row->events,
                'conversions' => $row->conversions,
                'revenue_minor' => $row->revenueMinor,
                'currency' => $row->currency,
            ],
        );
    }
}

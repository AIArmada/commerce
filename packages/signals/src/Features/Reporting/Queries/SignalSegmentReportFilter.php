<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Reporting\Queries;

use AIArmada\Signals\Features\Reporting\Api\SegmentOptions;
use AIArmada\Signals\Features\Ingestion\Models\SignalEvent;
use AIArmada\Signals\Features\Ingestion\Models\SignalIdentity;
use AIArmada\Signals\Features\Reporting\Models\SignalSegment;
use AIArmada\Signals\Features\Ingestion\Models\SignalSession;
use Illuminate\Database\Eloquent\Builder;

final class SignalSegmentReportFilter implements SegmentOptions
{
    public function __construct(private readonly SignalEventConditionQueryService $conditionQueryService) {}

    /**
     * @return array<string, string>
     */
    public function getSegmentOptions(): array
    {
        return SignalSegment::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  Builder<SignalEvent>  $query
     * @return Builder<SignalEvent>
     */
    public function applyToEventQuery(Builder $query, ?string $signalSegmentId): Builder
    {
        $segment = $this->resolveSegment($signalSegmentId);

        if ($signalSegmentId !== null && $signalSegmentId !== '' && $segment === null) {
            return $this->conditionQueryService->failClosed($query);
        }

        if ($segment === null) {
            return $query;
        }

        return $this->conditionQueryService->apply($query, $segment->conditions, $segment->match_type);
    }

    /**
     * @param  Builder<SignalSession>  $query
     * @return Builder<SignalSession>
     */
    public function applyToSessionQuery(Builder $query, ?string $signalSegmentId): Builder
    {
        $segment = $this->resolveSegment($signalSegmentId);

        if ($signalSegmentId !== null && $signalSegmentId !== '' && $segment === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($segment === null) {
            return $query;
        }

        return $query->whereHas('events', function (Builder $eventQuery) use ($segment): void {
            $this->conditionQueryService->apply($eventQuery, $segment->conditions, $segment->match_type);
        });
    }

    /**
     * @param  Builder<SignalIdentity>  $query
     * @return Builder<SignalIdentity>
     */
    public function applyToIdentityQuery(Builder $query, ?string $signalSegmentId): Builder
    {
        $segment = $this->resolveSegment($signalSegmentId);

        if ($signalSegmentId !== null && $signalSegmentId !== '' && $segment === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($segment === null) {
            return $query;
        }

        return $query->whereHas('events', function (Builder $eventQuery) use ($segment): void {
            $this->conditionQueryService->apply($eventQuery, $segment->conditions, $segment->match_type);
        });
    }

    private function resolveSegment(?string $signalSegmentId): ?SignalSegment
    {
        if ($signalSegmentId === null || $signalSegmentId === '') {
            return null;
        }

        return SignalSegment::query()
            ->where('is_active', true)
            ->whereKey($signalSegmentId)
            ->first();
    }
}

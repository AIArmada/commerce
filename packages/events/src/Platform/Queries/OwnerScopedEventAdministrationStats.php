<?php

declare(strict_types=1);

namespace AIArmada\Events\Platform\Queries;

use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Events\Contracts\EventAdministrationStats;
use AIArmada\Events\Data\EventAdministrationStatsData;
use AIArmada\Events\Platform\ModelResolver;
use Carbon\CarbonImmutable;

final readonly class OwnerScopedEventAdministrationStats implements EventAdministrationStats
{
    public function read(): EventAdministrationStatsData
    {
        return OwnerScopeOverride::withoutIncludeGlobal(fn (): EventAdministrationStatsData => $this->readScoped());
    }

    private function readScoped(): EventAdministrationStatsData
    {
        $eventClass = ModelResolver::eventClass();
        $occurrenceClass = ModelResolver::occurrenceClass();
        $registrationClass = ModelResolver::registrationClass();
        $attendanceClass = ModelResolver::attendanceClass();

        $events = $eventClass::query()
            ->selectRaw('COUNT(*) as total_events')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as published_events', ['published'])
            ->toBase()
            ->first();

        return new EventAdministrationStatsData(
            totalEvents: (int) ($events->total_events ?? 0),
            publishedEvents: (int) ($events->published_events ?? 0),
            upcomingOccurrences: $occurrenceClass::query()
                ->whereHas('event')
                ->where('starts_at', '>=', CarbonImmutable::now())
                ->count(),
            totalRegistrations: $registrationClass::query()->whereHas('event')->count(),
            totalAttendances: $attendanceClass::query()->whereHas('event')->count(),
        );
    }
}

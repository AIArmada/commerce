<?php

declare(strict_types=1);

namespace AIArmada\Events\Queries;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Contracts\EventAdministrationStats;
use AIArmada\Events\Data\EventAdministrationStatsData;
use AIArmada\Events\Platform\ModelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class OwnerScopedEventAdministrationStats implements EventAdministrationStats
{
    public function read(): EventAdministrationStatsData
    {
        $eventClass = ModelResolver::eventClass();
        $occurrenceClass = ModelResolver::occurrenceClass();
        $registrationClass = ModelResolver::registrationClass();
        $attendanceClass = ModelResolver::attendanceClass();

        $scope = static fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false);

        $events = $scope($eventClass::query())
            ->selectRaw('COUNT(*) as total_events')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as published_events', ['published'])
            ->toBase()
            ->first();

        return new EventAdministrationStatsData(
            totalEvents: (int) ($events->total_events ?? 0),
            publishedEvents: (int) ($events->published_events ?? 0),
            upcomingOccurrences: $occurrenceClass::query()
                ->whereHas('event', $scope)
                ->where('starts_at', '>=', CarbonImmutable::now())
                ->count(),
            totalRegistrations: $registrationClass::query()->whereHas('event', $scope)->count(),
            totalAttendances: $attendanceClass::query()->whereHas('event', $scope)->count(),
        );
    }
}

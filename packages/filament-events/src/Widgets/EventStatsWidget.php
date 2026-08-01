<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAttendance;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

final class EventStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $eventQuery = OwnerUiScope::apply(Event::query(), includeGlobal: false);
        $eventTotals = (clone $eventQuery)
            ->selectRaw('COUNT(*) as total_events')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as published_events', [Event::PUBLISHED])
            ->toBase()
            ->first();

        return [
            Stat::make('Total Events', (int) ($eventTotals->total_events ?? 0))
                ->description('All events in the system')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('primary'),
            Stat::make('Published Events', (int) ($eventTotals->published_events ?? 0))
                ->description('Currently published')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make(
                'Upcoming Occurrences',
                EventOccurrence::query()
                    ->whereHas('event', fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false))
                    ->where('starts_at', '>=', CarbonImmutable::now())
                    ->count()
            )
                ->description('Scheduled for the future')
                ->descriptionIcon('heroicon-o-clock')
                ->color('info'),
            Stat::make(
                'Total Registrations',
                EventRegistration::query()
                    ->whereHas('event', fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false))
                    ->count()
            )
                ->description('All registrations')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('warning'),
            Stat::make(
                'Total Attendances',
                EventAttendance::query()
                    ->whereHas('event', fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false))
                    ->count()
            )
                ->description('All check-ins')
                ->descriptionIcon('heroicon-o-clipboard-document-check')
                ->color('gray'),
        ];
    }

    protected function getColumns(): int
    {
        return 5;
    }
}

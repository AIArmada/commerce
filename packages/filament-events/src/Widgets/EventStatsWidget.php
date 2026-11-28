<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Widgets;

use AIArmada\Events\Contracts\EventAdministrationStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class EventStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $stats = app(EventAdministrationStats::class)->read();

        return [
            Stat::make('Total Events', $stats->totalEvents)
                ->description('All events in the system')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('primary'),
            Stat::make('Published Events', $stats->publishedEvents)
                ->description('Currently published')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Upcoming Occurrences', $stats->upcomingOccurrences)
                ->description('Scheduled for the future')
                ->descriptionIcon('heroicon-o-clock')
                ->color('info'),
            Stat::make('Total Registrations', $stats->totalRegistrations)
                ->description('All registrations')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('warning'),
            Stat::make('Total Attendances', $stats->totalAttendances)
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

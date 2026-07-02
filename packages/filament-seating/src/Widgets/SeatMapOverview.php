<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Widgets;

use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatMap as SeatMapModel;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SeatMapOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $mapCount = SeatMapModel::count();
        $seatCount = Seat::count();
        $blockedCount = Seat::where('status', 'blocked')->count();

        return [
            Stat::make('Seat Maps', $mapCount),
            Stat::make('Total Seats', $seatCount),
            Stat::make('Blocked Seats', $blockedCount),
        ];
    }
}

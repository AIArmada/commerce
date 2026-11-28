<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Seating\Enums\SeatStatus;
use AIArmada\Seating\Features\Maps\Models\Seat;
use AIArmada\Seating\Features\Maps\Models\SeatMap as SeatMapModel;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SeatMapOverview extends BaseWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return OwnerUiScope::canCreate(SeatMapModel::class);
    }

    protected function getStats(): array
    {
        $mapCount = SeatMapModel::query()->forOwner()->count();
        $seatCounts = Seat::query()
            ->forOwner()
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as blocked', [SeatStatus::Blocked->value])
            ->first();
        $seatCount = (int) ($seatCounts?->getAttribute('total') ?? 0);
        $blockedCount = (int) ($seatCounts?->getAttribute('blocked') ?? 0);

        return [
            Stat::make('Seat Maps', $mapCount),
            Stat::make('Total Seats', $seatCount),
            Stat::make('Blocked Seats', $blockedCount),
        ];
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class SeatMapOccupancy extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-eye';

    protected string $view = 'filament-seating::pages.seat-map-occupancy';

    public ?string $seatMapId = null;

    public function mount(?string $seatMapId = null): void
    {
        $this->seatMapId = $seatMapId;
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-seating.navigation.group');
    }

    public static function canAccess(): bool
    {
        return false;
    }
}

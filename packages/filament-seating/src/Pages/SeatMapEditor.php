<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class SeatMapEditor extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-pencil-square';

    protected string $view = 'filament-seating::pages.seat-map-editor';

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

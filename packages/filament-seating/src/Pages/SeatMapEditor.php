<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\Seating\Models\SeatMap as SeatMapModel;
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
        if ($seatMapId === null || $seatMapId === '') {
            $this->seatMapId = null;

            return;
        }

        $allowed = OwnerScopedIds::allowedIds(SeatMapModel::class, [$seatMapId]);

        abort_if($allowed === [], 404);

        $this->seatMapId = $allowed[0];
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

<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Resources\SeatMapResource\Pages;

use AIArmada\FilamentSeating\Resources\SeatMapResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewSeatMap extends ViewRecord
{
    protected static string $resource = SeatMapResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}

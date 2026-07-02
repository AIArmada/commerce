<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Resources\SeatMapResource\Pages;

use AIArmada\FilamentSeating\Resources\SeatMapResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditSeatMap extends EditRecord
{
    protected static string $resource = SeatMapResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }
}

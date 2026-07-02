<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Resources\SeatMapResource\Pages;

use AIArmada\FilamentSeating\Resources\SeatMapResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListSeatMaps extends ListRecords
{
    protected static string $resource = SeatMapResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSubLocationResource\Pages;

use AIArmada\FilamentEvents\Resources\EventSubLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListEventSubLocations extends ListRecords
{
    protected static string $resource = EventSubLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

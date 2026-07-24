<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\Pages;

use AIArmada\FilamentPersons\Resources\PersonResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListPersons extends ListRecords
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

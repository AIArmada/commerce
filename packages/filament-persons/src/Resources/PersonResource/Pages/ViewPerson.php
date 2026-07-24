<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\Pages;

use AIArmada\FilamentPersons\Resources\PersonResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewPerson extends ViewRecord
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

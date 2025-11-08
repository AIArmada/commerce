<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Resources\UserResource\Pages;

use AIArmada\FilamentPermissions\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}

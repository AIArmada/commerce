<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Resources\RoleResource\Pages;

use AIArmada\FilamentPermissions\Resources\RoleResource;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}

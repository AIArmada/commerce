<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\PermissionResource\Pages;

use AIArmada\FilamentAuthz\Resources\PermissionResource;
use Filament\Resources\Pages\ListRecords;

class ListPermissions extends ListRecords
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}

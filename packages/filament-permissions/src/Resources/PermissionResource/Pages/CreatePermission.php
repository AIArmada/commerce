<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Resources\PermissionResource\Pages;

use AIArmada\FilamentPermissions\Resources\PermissionResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

class CreatePermission extends CreateRecord
{
    protected static string $resource = PermissionResource::class;

    protected function afterCreate(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

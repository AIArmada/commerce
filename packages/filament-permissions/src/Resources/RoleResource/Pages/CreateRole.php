<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Resources\RoleResource\Pages;

use AIArmada\FilamentPermissions\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function afterCreate(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

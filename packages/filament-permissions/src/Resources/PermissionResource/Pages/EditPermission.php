<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Resources\PermissionResource\Pages;

use AIArmada\FilamentPermissions\Resources\PermissionResource;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

class EditPermission extends EditRecord
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

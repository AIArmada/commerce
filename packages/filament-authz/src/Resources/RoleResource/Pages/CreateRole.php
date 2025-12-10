<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\RoleResource\Pages;

use AIArmada\FilamentAuthz\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

class CreateRole extends CreateRecord
{
    /**
     * @var list<string>
     */
    protected array $permissionIds = [];

    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->permissionIds = array_map('strval', $data['permissions'] ?? []);
        unset($data['permissions']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->permissionIds !== []) {
            $this->record->syncPermissions($this->permissionIds);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

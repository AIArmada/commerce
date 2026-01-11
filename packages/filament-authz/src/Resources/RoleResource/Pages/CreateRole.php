<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\RoleResource\Pages;

use AIArmada\FilamentAuthz\Concerns\SyncsRolePermissions;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    use SyncsRolePermissions;

    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractPermissionIds($data);
    }

    protected function afterCreate(): void
    {
        $this->syncPermissionsToRole();
    }
}

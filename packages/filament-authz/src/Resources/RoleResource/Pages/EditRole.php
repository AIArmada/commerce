<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\RoleResource\Pages;

use AIArmada\FilamentAuthz\Resources\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * @property Role $record
 */
class EditRole extends EditRecord
{
    /**
     * @var list<string>
     */
    protected array $permissionIds = [];

    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->permissionIds = array_map('strval', $data['permissions'] ?? []);
        unset($data['permissions']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->permissionIds !== []) {
            // @phpstan-ignore method.nonObject (Record is guaranteed to be Role in afterSave)
            $this->record->syncPermissions($this->permissionIds);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

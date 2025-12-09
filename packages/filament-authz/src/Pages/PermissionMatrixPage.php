<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionMatrixPage extends Page
{
    public ?string $selectedRole = null;

    /** @var array<string, bool> */
    public array $permissions = [];

    /** @var Collection<int, Permission> */
    public Collection $allPermissions;

    /** @var Collection<int, Role> */
    public Collection $allRoles;

    /** @var array<string, array<string, Permission>> */
    public array $groupedPermissions = [];

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-table-cells';

    protected string $view = 'filament-authz::pages.permission-matrix';

    protected static ?string $title = 'Permission Matrix';

    protected static ?string $navigationLabel = 'Permission Matrix';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-authz.navigation.group', 'Administration');
    }

    public function mount(): void
    {
        $this->allPermissions = Permission::all();
        $this->allRoles = Role::all();
        $this->groupPermissions();
    }

    public function selectRole(string $roleId): void
    {
        $this->selectedRole = $roleId;
        $role = Role::find($roleId);

        if ($role !== null) {
            $rolePermissions = $role->permissions->pluck('id')->toArray();
            $this->permissions = [];

            foreach ($this->allPermissions as $permission) {
                $this->permissions[$permission->id] = in_array($permission->id, $rolePermissions, true);
            }
        }
    }

    public function togglePermission(string $permissionId): void
    {
        $this->permissions[$permissionId] = ! ($this->permissions[$permissionId] ?? false);
    }

    public function savePermissions(): void
    {
        if ($this->selectedRole === null) {
            return;
        }

        $role = Role::find($this->selectedRole);

        if ($role === null) {
            return;
        }

        $enabledPermissions = collect($this->permissions)
            ->filter(fn (bool $enabled): bool => $enabled)
            ->keys()
            ->toArray();

        $role->syncPermissions($enabledPermissions);

        Notification::make()
            ->title('Permissions Updated')
            ->body("Permissions for role '{$role->name}' have been updated.")
            ->success()
            ->send();
    }

    public function getSelectedRoleName(): ?string
    {
        if ($this->selectedRole === null) {
            return null;
        }

        return Role::find($this->selectedRole)?->name;
    }

    /**
     * Get the permission matrix data.
     *
     * @return array<string, array<string, array{id: string, name: string, has: bool, source: string}>>
     */
    public function getMatrixData(): array
    {
        $matrix = [];

        foreach ($this->groupedPermissions as $group => $permissions) {
            $matrix[$group] = [];

            foreach ($permissions as $permission) {
                $has = $this->permissions[$permission->id] ?? false;
                $source = $has ? 'direct' : 'none';

                $matrix[$group][$permission->name] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'has' => $has,
                    'source' => $source,
                ];
            }
        }

        return $matrix;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('selectRole')
                ->label('Select Role')
                ->form([
                    Select::make('role')
                        ->label('Role')
                        ->options($this->allRoles->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    $this->selectRole($data['role']);
                }),
            Action::make('saveChanges')
                ->label('Save Changes')
                ->action(fn () => $this->savePermissions())
                ->visible(fn () => $this->selectedRole !== null)
                ->color('primary'),
        ];
    }

    protected function groupPermissions(): void
    {
        $this->groupedPermissions = $this->allPermissions
            ->groupBy(function (Permission $permission): string {
                $parts = explode('.', $permission->name);

                return $parts[0] ?? 'other';
            })
            ->toArray();
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Pages;

use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class PermissionMatrixPage extends Page
{
    public ?string $selectedRole = null;

    /** @var array<string, bool> */
    public array $permissions = [];

    /**
     * @var array<string, string>
     */
    public array $roleOptions = [];

    /**
     * @var array<string, array<int, array{id: string, name: string, label: string}>>
     */
    public array $permissionGroups = [];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

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
        $this->roleOptions = Role::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn(string $name, mixed $id): array => [(string) $id => $name])
            ->all();

        $this->permissionGroups = $this->buildPermissionGroups();
    }

    public function selectRole(string|int $roleId): void
    {
        $this->selectedRole = (string) $roleId;
        $role = Role::find($roleId);

        if ($role !== null) {
            $rolePermissions = $role->permissions
                ->pluck('id')
                ->map(fn(mixed $id): string => (string) $id)
                ->all();
            $this->permissions = [];

            foreach ($this->permissionGroups as $group) {
                foreach ($group as $permissionRow) {
                    $permissionId = $permissionRow['id'];
                    $this->permissions[$permissionId] = in_array($permissionId, $rolePermissions, true);
                }
            }

            return;
        }

        $this->permissions = [];
    }

    public function togglePermission(string|int $permissionId): void
    {
        $permissionId = (string) $permissionId;
        $this->permissions[$permissionId] = !($this->permissions[$permissionId] ?? false);
    }

    public function selectAllPermissions(): void
    {
        foreach ($this->permissionGroups as $group) {
            foreach ($group as $permissionRow) {
                $this->permissions[$permissionRow['id']] = true;
            }
        }
    }

    public function deselectAllPermissions(): void
    {
        foreach ($this->permissionGroups as $group) {
            foreach ($group as $permissionRow) {
                $this->permissions[$permissionRow['id']] = false;
            }
        }
    }

    public function selectGroupPermissions(string $groupName): void
    {
        if (!isset($this->permissionGroups[$groupName])) {
            return;
        }

        foreach ($this->permissionGroups[$groupName] as $permissionRow) {
            $this->permissions[$permissionRow['id']] = true;
        }
    }

    public function deselectGroupPermissions(string $groupName): void
    {
        if (!isset($this->permissionGroups[$groupName])) {
            return;
        }

        foreach ($this->permissionGroups[$groupName] as $permissionRow) {
            $this->permissions[$permissionRow['id']] = false;
        }
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
            ->filter(fn(bool $enabled): bool => $enabled)
            ->keys()
            ->toArray();

        $role->syncPermissions($enabledPermissions);

        if (!app()->runningInConsole()) {
            Notification::make()
                ->title('Permissions Updated')
                ->body("Permissions for role '{$role->name}' have been updated.")
                ->success()
                ->send();
        }
    }

    public function getSelectedRoleName(): ?string
    {
        if ($this->selectedRole === null) {
            return null;
        }

        return $this->roleOptions[$this->selectedRole] ?? null;
    }

    /**
     * Get the permission matrix data.
     *
     * @return array<string, array<string, array{id: string, name: string, has: bool, source: string}>>
     */
    public function getMatrixData(): array
    {
        $matrix = [];

        foreach ($this->permissionGroups as $group => $permissions) {
            $matrix[$group] = [];

            foreach ($permissions as $permissionRow) {
                $permissionId = $permissionRow['id'];
                $has = $this->permissions[$permissionId] ?? false;
                $source = $has ? 'direct' : 'none';

                $matrix[$group][$permissionRow['name']] = [
                    'id' => $permissionId,
                    'name' => $permissionRow['name'],
                    'has' => $has,
                    'source' => $source,
                ];
            }
        }

        return $matrix;
    }

    /**
     * Get the permission matrix data categorized by type (resources, pages, widgets).
     *
     * @return array{resources: array<string, array<string, array{id: string, name: string, has: bool}>>, pages: array<string, array{id: string, name: string, has: bool}>, widgets: array<string, array{id: string, name: string, has: bool}>}
     */
    public function getMatrixDataByType(): array
    {
        $matrixData = $this->getMatrixData();

        $resources = [];
        $pages = [];
        $widgets = [];

        foreach ($matrixData as $group => $permissions) {
            if ($group === 'page') {
                // All page permissions go into pages
                foreach ($permissions as $permissionName => $permissionData) {
                    $pages[$permissionName] = $permissionData;
                }
            } elseif ($group === 'widget') {
                // All widget permissions go into widgets
                foreach ($permissions as $permissionName => $permissionData) {
                    $widgets[$permissionName] = $permissionData;
                }
            } else {
                // Everything else is a resource permission
                if (!isset($resources[$group])) {
                    $resources[$group] = [];
                }
                foreach ($permissions as $permissionName => $permissionData) {
                    $resources[$group][$permissionName] = $permissionData;
                }
            }
        }

        return [
            'resources' => $resources,
            'pages' => $pages,
            'widgets' => $widgets,
        ];
    }

    /**
     * Select all permissions of a specific type.
     */
    public function selectTypePermissions(string $type): void
    {
        $data = $this->getMatrixDataByType();

        if ($type === 'pages') {
            foreach ($data['pages'] as $permissionData) {
                $this->permissions[$permissionData['id']] = true;
            }
        } elseif ($type === 'widgets') {
            foreach ($data['widgets'] as $permissionData) {
                $this->permissions[$permissionData['id']] = true;
            }
        } elseif ($type === 'resources') {
            foreach ($data['resources'] as $group) {
                foreach ($group as $permissionData) {
                    $this->permissions[$permissionData['id']] = true;
                }
            }
        }
    }

    /**
     * Deselect all permissions of a specific type.
     */
    public function deselectTypePermissions(string $type): void
    {
        $data = $this->getMatrixDataByType();

        if ($type === 'pages') {
            foreach ($data['pages'] as $permissionData) {
                $this->permissions[$permissionData['id']] = false;
            }
        } elseif ($type === 'widgets') {
            foreach ($data['widgets'] as $permissionData) {
                $this->permissions[$permissionData['id']] = false;
            }
        } elseif ($type === 'resources') {
            foreach ($data['resources'] as $group) {
                foreach ($group as $permissionData) {
                    $this->permissions[$permissionData['id']] = false;
                }
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('selectRole')
                ->label('Select Role')
                ->form([
                    Select::make('role')
                        ->label('Role')
                        ->options($this->roleOptions)
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    $this->selectRole($data['role']);
                }),
            Action::make('saveChanges')
                ->label('Save Changes')
                ->action(fn() => $this->savePermissions())
                ->visible(fn() => $this->selectedRole !== null)
                ->color('primary'),
        ];
    }

    /**
     * @return array<string, array<int, array{id: string, name: string, label: string}>>
     */
    private function buildPermissionGroups(): array
    {
        /** @var array<string, array<int, array{id: string, name: string, label: string}>> $groups */
        $groups = [];

        $permissions = Permission::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach ($permissions as $permission) {
            $name = (string) $permission->name;
            $group = Str::before($name, '.');
            $group = $group !== '' ? $group : 'other';

            $label = Str::after($name, '.');
            $label = $label !== '' && $label !== $name ? $label : $name;

            $groups[$group][] = [
                'id' => (string) $permission->id,
                'name' => $name,
                'label' => $label,
            ];
        }

        ksort($groups);

        return $groups;
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionExplorer extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::MagnifyingGlass;

    protected string $view = 'filament-permissions::pages.permission-explorer';

    protected static ?string $title = 'Permission Explorer';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-permissions.navigation.group');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->can('permission.viewAny') || $user?->hasRole(config('filament-permissions.super_admin_role'));
    }

    /**
     * @return array<string, array<int, array{name: string, guard_name: string, roles: array<int, string>}>>
     */
    public function getPermissionsGrouped(): array
    {
        $permissions = Permission::orderBy('name')->get();

        return $permissions->groupBy(function ($permission) {
            $parts = explode('.', $permission->name);

            return $parts[0] ?? 'Other';
        })->map(function ($group) {
            return $group->map(function ($permission) {
                // Load roles separately to avoid eager loading issues
                $roles = $permission->roles()->pluck('name')->toArray();

                return [
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                    'roles' => $roles,
                ];
            })->toArray();
        })->toArray();
    }

    /**
     * @return array<int, array{name: string, guard_name: string, permissions_count: int}>
     */
    public function getRolesWithPermissionCounts(): array
    {
        return Role::withCount('permissions')->orderBy('name')->get()->map(function ($role) {
            return [
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions_count' => $role->permissions_count,
            ];
        })->toArray();
    }
}

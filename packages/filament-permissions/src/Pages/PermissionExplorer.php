<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        $canView = false;
        if (method_exists($user, 'can')) {
            $canView = $user->can('permission.viewAny'); // @phpstan-ignore-line
        }

        $isSuperAdmin = false;
        if (method_exists($user, 'hasRole')) {
            $isSuperAdmin = $user->hasRole(config('filament-permissions.super_admin_role')); // @phpstan-ignore-line
        }

        return $canView || $isSuperAdmin;
    }

    /**
     * @return array<string, array<int, array{name: string, guard_name: string, roles: array<int, string>}>>
     */
    public function getPermissionsGrouped(): array
    {
        /** @var class-string<Model> $permissionModel */
        $permissionModel = config('permission.models.permission', 'Spatie\\Permission\\Models\\Permission');
        $permissions = $permissionModel::orderBy('name')->get();

        return $permissions->groupBy(function (Model $permission): string {
            /** @var string $name */
            $name = $permission->getAttribute('name');
            $parts = explode('.', $name);

            return $parts[0] ?? 'Other';
        })->map(function ($group): array {
            return $group->map(function (Model $permission): array {
                /** @var string $name */
                $name = $permission->getAttribute('name');
                /** @var string $guardName */
                $guardName = $permission->getAttribute('guard_name');

                // Load roles separately to avoid eager loading issues
                /** @var array<int, string> $roles */
                $roles = method_exists($permission, 'roles')
                    ? $permission->roles()->pluck('name')->toArray()
                    : [];

                return [
                    'name' => $name,
                    'guard_name' => $guardName,
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
        /** @var class-string<Model> $roleModel */
        $roleModel = config('permission.models.role', 'Spatie\\Permission\\Models\\Role');

        return $roleModel::withCount('permissions')->orderBy('name')->get()->map(function (Model $role): array {
            /** @var string $name */
            $name = $role->getAttribute('name');
            /** @var string $guardName */
            $guardName = $role->getAttribute('guard_name');
            /** @var int $permissionsCount */
            $permissionsCount = $role->getAttribute('permissions_count') ?? 0;

            return [
                'name' => $name,
                'guard_name' => $guardName,
                'permissions_count' => $permissionsCount,
            ];
        })->toArray();
    }
}

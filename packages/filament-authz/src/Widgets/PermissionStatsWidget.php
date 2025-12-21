<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Support\PermissionTeamScope;

class PermissionStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalRoles = Role::count();
        $totalPermissions = Permission::count();
        $totalUsers = $this->countUsersWithRoles();
        $unassignedPermissions = $this->countUnassignedPermissions();

        return [
            Stat::make('Total Roles', $totalRoles)
                ->description('Active roles in system')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('Total Permissions', $totalPermissions)
                ->description('Defined permissions')
                ->descriptionIcon('heroicon-m-key')
                ->color('primary'),

            Stat::make('Users with Roles', $totalUsers)
                ->description('Users assigned roles')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Unassigned Permissions', $unassignedPermissions)
                ->description('Permissions not in any role')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($unassignedPermissions > 0 ? 'warning' : 'success'),
        ];
    }

    protected function countUsersWithRoles(): int
    {
        $table = config('permission.table_names.model_has_roles', 'model_has_roles');
        $modelIdColumn = (string) config('permission.column_names.model_morph_key', 'model_id');

        $query = DB::table($table)->distinct();
        PermissionTeamScope::apply($query, $table);

        return $query->count($modelIdColumn);
    }

    protected function countUnassignedPermissions(): int
    {
        $rolePermissionsTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $rolesTable = config('permission.table_names.roles', 'roles');

        $assignedPermissionIds = DB::table($rolePermissionsTable)
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$rolePermissionsTable}.role_id");
        PermissionTeamScope::apply($assignedPermissionIds, $rolesTable);
        $assignedPermissionIds = $assignedPermissionIds
            ->distinct()
            ->pluck("{$rolePermissionsTable}.permission_id");

        return Permission::whereNotIn('id', $assignedPermissionIds)->count();
    }
}

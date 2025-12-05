<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Widgets;

use DB;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
        return DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->distinct('model_id')
            ->count('model_id');
    }

    protected function countUnassignedPermissions(): int
    {
        $assignedPermissionIds = DB::table(config('permission.table_names.role_has_permissions', 'role_has_permissions'))
            ->distinct()
            ->pluck('permission_id');

        return Permission::whereNotIn('id', $assignedPermissionIds)->count();
    }
}

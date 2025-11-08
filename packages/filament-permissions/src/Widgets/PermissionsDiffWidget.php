<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsDiffWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Permissions Overview';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->can('permission.viewAny') || $user?->hasRole(config('filament-permissions.super_admin_role'));
    }

    protected function getStats(): array
    {
        $totalPermissions = Permission::count();
        $totalRoles = Role::count();
        $unusedPermissions = Permission::query()->whereDoesntHave('roles')->count();

        return [
            Stat::make('Total Permissions', $totalPermissions)
                ->icon('heroicon-o-shield-check')
                ->color('primary'),
            Stat::make('Total Roles', $totalRoles)
                ->icon('heroicon-o-key')
                ->color('success'),
            Stat::make('Unused Permissions', $unusedPermissions)
                ->icon('heroicon-o-exclamation-triangle')
                ->color($unusedPermissions > 0 ? 'warning' : 'gray'),
        ];
    }
}

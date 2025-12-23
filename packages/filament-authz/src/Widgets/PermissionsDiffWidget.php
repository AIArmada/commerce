<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Widgets;

use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class PermissionsDiffWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Permissions Overview';

    public static function canView(): bool
    {
        $user = Auth::user();

        /** @phpstan-ignore method.notFound */
        return $user?->can('permission.viewAny') || $user?->hasRole(config('filament-authz.super_admin_role'));
    }

    protected function getStats(): array
    {
        $totalPermissions = Permission::count();
        $totalRoles = Role::count();
        $unusedPermissions = Permission::query()->whereDoesntHave('roles')->count();

        return [
            Stat::make('Total Permissions', $totalPermissions)
                ->icon(Heroicon::ShieldCheck)
                ->color('primary'),
            Stat::make('Total Roles', $totalRoles)
                ->icon(Heroicon::Key)
                ->color('success'),
            Stat::make('Unused Permissions', $unusedPermissions)
                ->icon(Heroicon::ExclamationTriangle)
                ->color($unusedPermissions > 0 ? 'warning' : 'gray'),
        ];
    }
}

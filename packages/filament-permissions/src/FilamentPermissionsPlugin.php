<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions;

use AIArmada\FilamentPermissions\Http\Middleware\AuthorizePanelRoles;
use AIArmada\FilamentPermissions\Pages\AuditLogPage;
use AIArmada\FilamentPermissions\Pages\PermissionMatrixPage;
use AIArmada\FilamentPermissions\Pages\RoleHierarchyPage;
use AIArmada\FilamentPermissions\Resources\PermissionResource;
use AIArmada\FilamentPermissions\Resources\RoleResource;
use AIArmada\FilamentPermissions\Resources\UserResource;
use AIArmada\FilamentPermissions\Widgets\PermissionStatsWidget;
use AIArmada\FilamentPermissions\Widgets\RecentActivityWidget;
use AIArmada\FilamentPermissions\Widgets\RoleHierarchyWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentPermissionsPlugin implements Plugin
{
    public static function make(): self
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'aiarmada-filament-permissions';
    }

    public function register(Panel $panel): void
    {
        $resources = [
            RoleResource::class,
            PermissionResource::class,
        ];

        if ((bool) config('filament-permissions.enable_user_resource')) {
            $resources[] = UserResource::class;
        }

        $pages = [];
        if ((bool) config('filament-permissions.features.permission_explorer')) {
            $pages[] = Pages\PermissionExplorer::class;
        }

        // New enterprise pages
        if ((bool) config('filament-permissions.features.permission_matrix', true)) {
            $pages[] = PermissionMatrixPage::class;
        }

        if ((bool) config('filament-permissions.features.role_hierarchy', true)) {
            $pages[] = RoleHierarchyPage::class;
        }

        if ((bool) config('filament-permissions.audit.enabled', true)) {
            $pages[] = AuditLogPage::class;
        }

        $widgets = [];
        if ((bool) config('filament-permissions.features.diff_widget')) {
            $widgets[] = Widgets\PermissionsDiffWidget::class;
        }

        if ((bool) config('filament-permissions.features.impersonation_banner')) {
            $widgets[] = Widgets\ImpersonationBannerWidget::class;
        }

        // New enterprise widgets
        if ((bool) config('filament-permissions.features.stats_widget', true)) {
            $widgets[] = PermissionStatsWidget::class;
        }

        if ((bool) config('filament-permissions.features.hierarchy_widget', true)) {
            $widgets[] = RoleHierarchyWidget::class;
        }

        if ((bool) config('filament-permissions.features.activity_widget', true) && config('filament-permissions.audit.enabled', true)) {
            $widgets[] = RecentActivityWidget::class;
        }

        $panel
            ->resources($resources)
            ->pages($pages)
            ->widgets($widgets);

        $map = (array) config('filament-permissions.panel_guard_map');
        if ((bool) config('filament-permissions.features.auto_panel_middleware') && isset($map[$panel->getId()])) {
            $guard = (string) $map[$panel->getId()];
            $panel->authGuard($guard);
            $panel->middleware([
                'web',
                'auth:'.$guard,
                'permission:access '.$panel->getId(),
            ]);
        }

        if ((bool) config('filament-permissions.features.panel_role_authorization')) {
            $panel->authMiddleware([
                AuthorizePanelRoles::class,
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        // No-op for now; reserved for future cross-cutting boot logic.
    }
}

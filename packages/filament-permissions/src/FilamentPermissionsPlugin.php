<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions;

use AIArmada\FilamentPermissions\Resources\PermissionResource;
use AIArmada\FilamentPermissions\Resources\RoleResource;
use AIArmada\FilamentPermissions\Resources\UserResource;
use Filament\Contracts\Plugin;
use Filament\Panel;
use AIArmada\FilamentPermissions\Http\Middleware\AuthorizePanelRoles;

class FilamentPermissionsPlugin implements Plugin
{
    public static function make(): static
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

        $widgets = [];
        if ((bool) config('filament-permissions.features.diff_widget')) {
            $widgets[] = Widgets\PermissionsDiffWidget::class;
        }

        if ((bool) config('filament-permissions.features.impersonation_banner')) {
            $widgets[] = Widgets\ImpersonationBannerWidget::class;
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

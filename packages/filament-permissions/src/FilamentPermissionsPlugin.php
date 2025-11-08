<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions;

use AIArmada\FilamentPermissions\Resources\PermissionResource;
use AIArmada\FilamentPermissions\Resources\RoleResource;
use AIArmada\FilamentPermissions\Resources\UserResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

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
        $panel = $panel->resources([
            RoleResource::class,
            PermissionResource::class,
        ]);

        if ((bool) config('filament-permissions.enable_user_resource')) {
            $panel->resources([
                UserResource::class,
            ]);
        }

        if ((bool) config('filament-permissions.features.permission_explorer')) {
            $panel->pages([
                \AIArmada\FilamentPermissions\Pages\PermissionExplorer::class,
            ]);
        }

        if ((bool) config('filament-permissions.features.diff_widget')) {
            $panel->widgets([
                \AIArmada\FilamentPermissions\Widgets\PermissionsDiffWidget::class,
            ]);
        }

        if ((bool) config('filament-permissions.features.impersonation_banner')) {
            $panel->widgets([
                \AIArmada\FilamentPermissions\Widgets\ImpersonationBannerWidget::class,
            ]);
        }

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
    }

    public function boot(Panel $panel): void
    {
        // No-op for now; reserved for future cross-cutting boot logic.
    }
}

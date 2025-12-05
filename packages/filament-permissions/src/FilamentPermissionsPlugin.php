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
use AIArmada\FilamentPermissions\Services\PermissionRegistry;
use AIArmada\FilamentPermissions\Support\ResourcePermissionDiscovery;
use AIArmada\FilamentPermissions\Widgets\PermissionStatsWidget;
use AIArmada\FilamentPermissions\Widgets\RecentActivityWidget;
use AIArmada\FilamentPermissions\Widgets\RoleHierarchyWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentPermissionsPlugin implements Plugin
{
    protected bool $autoDiscoverPermissions = false;

    /**
     * @var array<string>
     */
    protected array $discoveryNamespaces = [];

    public static function make(): self
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'aiarmada-filament-permissions';
    }

    /**
     * Enable automatic permission discovery from resources.
     */
    public function discoverPermissions(bool $enabled = true): static
    {
        $this->autoDiscoverPermissions = $enabled;

        return $this;
    }

    /**
     * Add namespaces to scan for resource permissions.
     *
     * @param  array<string>  $namespaces
     */
    public function discoverPermissionsFrom(array $namespaces): static
    {
        $this->discoveryNamespaces = array_merge($this->discoveryNamespaces, $namespaces);
        $this->autoDiscoverPermissions = true;

        return $this;
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
        if ($this->shouldAutoDiscoverPermissions()) {
            $this->runPermissionDiscovery($panel);
        }
    }

    protected function shouldAutoDiscoverPermissions(): bool
    {
        return $this->autoDiscoverPermissions || config('filament-permissions.discovery.enabled', false);
    }

    protected function runPermissionDiscovery(Panel $panel): void
    {
        /** @var PermissionRegistry $registry */
        $registry = app(PermissionRegistry::class);

        /** @var ResourcePermissionDiscovery $discovery */
        $discovery = new ResourcePermissionDiscovery($registry);

        // Discover from panel resources
        $discovery->discoverFromPanel($panel);

        // Discover from configured namespaces
        $namespaces = array_merge(
            (array) config('filament-permissions.discovery.namespaces', []),
            $this->discoveryNamespaces
        );

        if (! empty($namespaces)) {
            $discovery->discoverFromNamespaces($namespaces);
        }

        // Auto-sync to database if enabled
        if (config('filament-permissions.discovery.auto_sync', false)) {
            $guard = config('filament-permissions.default_guard', 'web');
            $registry->sync($guard);
        }
    }
}

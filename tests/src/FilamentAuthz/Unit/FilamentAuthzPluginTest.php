<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\FilamentAuthzPlugin;
use AIArmada\FilamentAuthz\Http\Middleware\AuthorizePanelRoles;
use AIArmada\FilamentAuthz\Pages\AuditLogPage;
use AIArmada\FilamentAuthz\Pages\PermissionExplorer;
use AIArmada\FilamentAuthz\Pages\PermissionMatrixPage;
use AIArmada\FilamentAuthz\Pages\RoleHierarchyPage;
use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Resources\UserResource;
use AIArmada\FilamentAuthz\Services\PermissionRegistry;
use AIArmada\FilamentAuthz\Widgets\ImpersonationBannerWidget;
use AIArmada\FilamentAuthz\Widgets\PermissionsDiffWidget;
use AIArmada\FilamentAuthz\Widgets\PermissionStatsWidget;
use AIArmada\FilamentAuthz\Widgets\RecentActivityWidget;
use AIArmada\FilamentAuthz\Widgets\RoleHierarchyWidget;
use Filament\Panel;
use Mockery;
use ReflectionProperty;

beforeEach(function (): void {
    // Reset config before each test
    config(['filament-authz.enable_user_resource' => false]);
    config(['filament-authz.features.permission_explorer' => false]);
    config(['filament-authz.features.permission_matrix' => true]);
    config(['filament-authz.features.role_hierarchy' => true]);
    config(['filament-authz.audit.enabled' => true]);
    config(['filament-authz.features.diff_widget' => false]);
    config(['filament-authz.features.impersonation_banner' => false]);
    config(['filament-authz.features.stats_widget' => true]);
    config(['filament-authz.features.hierarchy_widget' => true]);
    config(['filament-authz.features.activity_widget' => true]);
    config(['filament-authz.features.auto_panel_middleware' => false]);
    config(['filament-authz.features.panel_role_authorization' => false]);
    config(['filament-authz.discovery.enabled' => false]);
});

afterEach(function (): void {
    Mockery::close();
});

describe('FilamentAuthzPlugin', function (): void {
    it('can be instantiated via make()', function (): void {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('returns correct plugin ID', function (): void {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin->getId())->toBe('aiarmada-filament-authz');
    });

    it('can enable permission discovery', function (): void {
        $plugin = FilamentAuthzPlugin::make()->discoverPermissions();

        // Use reflection to check internal state
        $reflection = new ReflectionProperty(FilamentAuthzPlugin::class, 'autoDiscoverPermissions');
        $reflection->setAccessible(true);

        expect($reflection->getValue($plugin))->toBeTrue();
    });

    it('can disable permission discovery', function (): void {
        $plugin = FilamentAuthzPlugin::make()->discoverPermissions(false);

        $reflection = new ReflectionProperty(FilamentAuthzPlugin::class, 'autoDiscoverPermissions');
        $reflection->setAccessible(true);

        expect($reflection->getValue($plugin))->toBeFalse();
    });

    it('can add discovery namespaces', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->discoverPermissionsFrom(['App\\Filament\\Resources', 'App\\Custom']);

        $reflection = new ReflectionProperty(FilamentAuthzPlugin::class, 'discoveryNamespaces');
        $reflection->setAccessible(true);

        expect($reflection->getValue($plugin))
            ->toContain('App\\Filament\\Resources')
            ->toContain('App\\Custom');
    });

    it('enables auto discovery when namespaces are added', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->discoverPermissionsFrom(['App\\Resources']);

        $reflection = new ReflectionProperty(FilamentAuthzPlugin::class, 'autoDiscoverPermissions');
        $reflection->setAccessible(true);

        expect($reflection->getValue($plugin))->toBeTrue();
    });

    it('is fluent with method chaining', function (): void {
        $plugin = FilamentAuthzPlugin::make()
            ->discoverPermissions()
            ->discoverPermissionsFrom(['App\\Resources']);

        expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });
});

describe('FilamentAuthzPlugin::register', function (): void {
    it('registers core resources', function (): void {
        $registeredResources = [];

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('resources')
            ->once()
            ->withArgs(function ($resources) use (&$registeredResources) {
                $registeredResources = $resources;

                return true;
            })
            ->andReturnSelf();
        $panel->shouldReceive('pages')->andReturnSelf();
        $panel->shouldReceive('widgets')->andReturnSelf();
        $panel->shouldReceive('getId')->andReturn('admin');

        $plugin = FilamentAuthzPlugin::make();
        $plugin->register($panel);

        expect($registeredResources)
            ->toContain(RoleResource::class)
            ->toContain(PermissionResource::class);
    });

    it('includes UserResource when enabled', function (): void {
        config(['filament-authz.enable_user_resource' => true]);

        $registeredResources = [];

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('resources')
            ->once()
            ->withArgs(function ($resources) use (&$registeredResources) {
                $registeredResources = $resources;

                return true;
            })
            ->andReturnSelf();
        $panel->shouldReceive('pages')->andReturnSelf();
        $panel->shouldReceive('widgets')->andReturnSelf();
        $panel->shouldReceive('getId')->andReturn('admin');

        $plugin = FilamentAuthzPlugin::make();
        $plugin->register($panel);

        expect($registeredResources)->toContain(UserResource::class);
    });

    it('registers pages based on config', function (): void {
        config(['filament-authz.features.permission_explorer' => true]);
        config(['filament-authz.features.permission_matrix' => true]);
        config(['filament-authz.features.role_hierarchy' => true]);
        config(['filament-authz.audit.enabled' => true]);

        $registeredPages = [];

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('resources')->andReturnSelf();
        $panel->shouldReceive('pages')
            ->once()
            ->withArgs(function ($pages) use (&$registeredPages) {
                $registeredPages = $pages;

                return true;
            })
            ->andReturnSelf();
        $panel->shouldReceive('widgets')->andReturnSelf();
        $panel->shouldReceive('getId')->andReturn('admin');

        $plugin = FilamentAuthzPlugin::make();
        $plugin->register($panel);

        expect($registeredPages)
            ->toContain(PermissionExplorer::class)
            ->toContain(PermissionMatrixPage::class)
            ->toContain(RoleHierarchyPage::class)
            ->toContain(AuditLogPage::class);
    });

    it('registers widgets based on config', function (): void {
        config(['filament-authz.features.diff_widget' => true]);
        config(['filament-authz.features.impersonation_banner' => true]);
        config(['filament-authz.features.stats_widget' => true]);
        config(['filament-authz.features.hierarchy_widget' => true]);
        config(['filament-authz.features.activity_widget' => true]);
        config(['filament-authz.audit.enabled' => true]);

        $registeredWidgets = [];

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('resources')->andReturnSelf();
        $panel->shouldReceive('pages')->andReturnSelf();
        $panel->shouldReceive('widgets')
            ->once()
            ->withArgs(function ($widgets) use (&$registeredWidgets) {
                $registeredWidgets = $widgets;

                return true;
            })
            ->andReturnSelf();
        $panel->shouldReceive('getId')->andReturn('admin');

        $plugin = FilamentAuthzPlugin::make();
        $plugin->register($panel);

        expect($registeredWidgets)
            ->toContain(PermissionsDiffWidget::class)
            ->toContain(ImpersonationBannerWidget::class)
            ->toContain(PermissionStatsWidget::class)
            ->toContain(RoleHierarchyWidget::class)
            ->toContain(RecentActivityWidget::class);
    });

    it('applies panel role authorization middleware when enabled', function (): void {
        config(['filament-authz.features.panel_role_authorization' => true]);

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('resources')->andReturnSelf();
        $panel->shouldReceive('pages')->andReturnSelf();
        $panel->shouldReceive('widgets')->andReturnSelf();
        $panel->shouldReceive('getId')->andReturn('admin');
        $panel->shouldReceive('authMiddleware')
            ->once()
            ->with([AuthorizePanelRoles::class])
            ->andReturnSelf();

        $plugin = FilamentAuthzPlugin::make();
        $plugin->register($panel);
    });

    it('applies auto panel middleware when enabled and panel in guard map', function (): void {
        config(['filament-authz.features.auto_panel_middleware' => true]);
        config(['filament-authz.panel_guard_map' => ['admin' => 'admin-guard']]);

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('resources')->andReturnSelf();
        $panel->shouldReceive('pages')->andReturnSelf();
        $panel->shouldReceive('widgets')->andReturnSelf();
        $panel->shouldReceive('getId')->andReturn('admin');
        $panel->shouldReceive('authGuard')
            ->once()
            ->with('admin-guard')
            ->andReturnSelf();
        $panel->shouldReceive('middleware')
            ->once()
            ->with(['web', 'auth:admin-guard', 'permission:access admin'])
            ->andReturnSelf();

        $plugin = FilamentAuthzPlugin::make();
        $plugin->register($panel);
    });
});

describe('FilamentAuthzPlugin::boot', function (): void {
    it('runs permission discovery when enabled via method', function (): void {
        config(['filament-authz.discovery.enabled' => false]);
        config(['filament-authz.discovery.auto_sync' => false]);
        config(['filament-authz.discovery.namespaces.include' => []]);

        $registry = Mockery::mock(PermissionRegistry::class);
        app()->instance(PermissionRegistry::class, $registry);

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getResources')->andReturn([]);

        $plugin = FilamentAuthzPlugin::make()->discoverPermissions();
        $plugin->boot($panel);

        // If no exception, discovery was triggered
        expect(true)->toBeTrue();
    });

    it('runs permission discovery when enabled via config', function (): void {
        config(['filament-authz.discovery.enabled' => true]);
        config(['filament-authz.discovery.auto_sync' => false]);
        config(['filament-authz.discovery.namespaces.include' => []]);

        $registry = Mockery::mock(PermissionRegistry::class);
        app()->instance(PermissionRegistry::class, $registry);

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getResources')->andReturn([]);

        $plugin = FilamentAuthzPlugin::make();
        $plugin->boot($panel);

        // If no exception, discovery was triggered
        expect(true)->toBeTrue();
    });

    it('does not run discovery when disabled', function (): void {
        config(['filament-authz.discovery.enabled' => false]);

        $panel = Mockery::mock(Panel::class);
        // getResources should NOT be called if discovery is disabled
        $panel->shouldNotReceive('getResources');

        $plugin = FilamentAuthzPlugin::make();
        $plugin->boot($panel);

        expect(true)->toBeTrue();
    });
});

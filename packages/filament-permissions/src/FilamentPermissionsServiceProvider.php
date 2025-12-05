<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions;

use AIArmada\FilamentPermissions\Listeners\PermissionEventSubscriber;
use AIArmada\FilamentPermissions\Services\AuditLogger;
use AIArmada\FilamentPermissions\Services\ComplianceReportService;
use AIArmada\FilamentPermissions\Services\ContextualAuthorizationService;
use AIArmada\FilamentPermissions\Services\ImplicitPermissionService;
use AIArmada\FilamentPermissions\Services\PermissionAggregator;
use AIArmada\FilamentPermissions\Services\PermissionCacheService;
use AIArmada\FilamentPermissions\Services\PermissionGroupService;
use AIArmada\FilamentPermissions\Services\PermissionImpactAnalyzer;
use AIArmada\FilamentPermissions\Services\PermissionRegistry;
use AIArmada\FilamentPermissions\Services\PermissionTester;
use AIArmada\FilamentPermissions\Services\PolicyEngine;
use AIArmada\FilamentPermissions\Services\RoleComparer;
use AIArmada\FilamentPermissions\Services\RoleInheritanceService;
use AIArmada\FilamentPermissions\Services\RoleTemplateService;
use AIArmada\FilamentPermissions\Services\TeamPermissionService;
use AIArmada\FilamentPermissions\Services\TemporalPermissionService;
use AIArmada\FilamentPermissions\Services\WildcardPermissionResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FilamentPermissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FilamentPermissionsPlugin::class);
        $this->mergeConfigFrom(__DIR__.'/../config/filament-permissions.php', 'filament-permissions');

        $this->registerServices();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-permissions');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/filament-permissions.php' => config_path('filament-permissions.php'),
        ], 'filament-permissions-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/filament-permissions'),
        ], 'filament-permissions-views');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'filament-permissions-migrations');

        $this->registerGateBefore();
        $this->registerCommands();
        $this->registerMacros();
        $this->registerEventSubscriber();
    }

    protected function registerServices(): void
    {
        // Core services as singletons
        $this->app->singleton(WildcardPermissionResolver::class);
        $this->app->singleton(ImplicitPermissionService::class);
        $this->app->singleton(PermissionGroupService::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(RoleInheritanceService::class);
        $this->app->singleton(RoleTemplateService::class);
        $this->app->singleton(PolicyEngine::class);
        $this->app->singleton(PermissionCacheService::class);

        // Services with dependencies
        $this->app->singleton(PermissionAggregator::class, function ($app) {
            return new PermissionAggregator(
                $app->make(RoleInheritanceService::class),
                $app->make(WildcardPermissionResolver::class),
                $app->make(ImplicitPermissionService::class)
            );
        });

        $this->app->singleton(ContextualAuthorizationService::class, function ($app) {
            return new ContextualAuthorizationService(
                $app->make(PermissionAggregator::class)
            );
        });

        $this->app->singleton(TeamPermissionService::class, function ($app) {
            return new TeamPermissionService(
                $app->make(ContextualAuthorizationService::class)
            );
        });

        $this->app->singleton(TemporalPermissionService::class, function ($app) {
            return new TemporalPermissionService(
                $app->make(ContextualAuthorizationService::class)
            );
        });

        $this->app->singleton(PermissionTester::class, function ($app) {
            return new PermissionTester(
                $app->make(PermissionAggregator::class),
                $app->make(PolicyEngine::class),
                $app->make(ContextualAuthorizationService::class)
            );
        });

        $this->app->singleton(RoleComparer::class, function ($app) {
            return new RoleComparer(
                $app->make(RoleInheritanceService::class)
            );
        });

        $this->app->singleton(PermissionImpactAnalyzer::class, function ($app) {
            return new PermissionImpactAnalyzer(
                $app->make(RoleInheritanceService::class)
            );
        });

        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(ComplianceReportService::class);
    }

    protected function registerGateBefore(): void
    {
        $role = (string) config('filament-permissions.super_admin_role');
        if ($role !== '') {
            Gate::before(static function ($user, string $ability) use ($role) {
                return method_exists($user, 'hasRole') && $user->hasRole($role) ? true : null;
            });
        }

        // Register wildcard permission resolution
        if (config('filament-permissions.features.wildcard_permissions', true)) {
            Gate::before(function ($user, string $ability) {
                if (! method_exists($user, 'getAllPermissions')) {
                    return null;
                }

                $resolver = app(WildcardPermissionResolver::class);
                $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

                foreach ($userPermissions as $permission) {
                    if ($resolver->isWildcard($permission) && $resolver->matches($permission, $ability)) {
                        return true;
                    }
                }

                return null;
            });
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\SyncPermissionsCommand::class,
                Console\DoctorPermissionsCommand::class,
                Console\ExportPermissionsCommand::class,
                Console\ImportPermissionsCommand::class,
                Console\GeneratePoliciesCommand::class,
                Console\PermissionGroupsCommand::class,
                Console\RoleHierarchyCommand::class,
                Console\RoleTemplateCommand::class,
                Console\PermissionCacheCommand::class,
            ]);
        }
    }

    protected function registerMacros(): void
    {
        Support\Macros\ActionMacros::register();
        Support\Macros\NavigationItemMacros::register();
        Support\Macros\TableComponentMacros::register();
        Support\Macros\ColumnMacros::register();
        Support\Macros\FilterMacros::register();
        Support\Macros\NavigationMacros::register();
        Support\Macros\FormMacros::register();
    }

    protected function registerEventSubscriber(): void
    {
        if (config('filament-permissions.audit.enabled', true)) {
            Event::subscribe(PermissionEventSubscriber::class);
        }
    }
}

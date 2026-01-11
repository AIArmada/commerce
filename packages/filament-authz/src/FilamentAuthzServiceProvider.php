<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz;

use AIArmada\FilamentAuthz\Console\DiscoverCommand;
use AIArmada\FilamentAuthz\Console\GeneratePoliciesCommand;
use AIArmada\FilamentAuthz\Console\SeederCommand;
use AIArmada\FilamentAuthz\Console\SuperAdminCommand;
use AIArmada\FilamentAuthz\Console\SyncAuthzCommand;
use AIArmada\FilamentAuthz\Models\Permission as AuthzPermission;
use AIArmada\FilamentAuthz\Models\Role as AuthzRole;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\Services\PermissionKeyBuilder;
use AIArmada\FilamentAuthz\Services\WildcardPermissionResolver;
use AIArmada\FilamentAuthz\Support\OwnerContextTeamResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Contracts\PermissionsTeamResolver;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Authz Service Provider.
 *
 * Features:
 * - Cleaner service registration
 * - Proper singleton bindings
 * - Modular command registration
 */
class FilamentAuthzServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filament-authz.php', 'filament-authz');

        $this->configureSpatiePermissions();

        $this->app->singleton(FilamentAuthzPlugin::class);
        $this->app->singleton(WildcardPermissionResolver::class);
        $this->app->singleton(EntityDiscoveryService::class);
        $this->app->singleton(PermissionKeyBuilder::class);

        $this->app->singleton(Authz::class, function ($app): Authz {
            return new Authz(
                $app->make(EntityDiscoveryService::class),
                $app->make(PermissionKeyBuilder::class)
            );
        });

        $this->registerTeamResolver();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/filament-authz.php' => config_path('filament-authz.php'),
        ], 'filament-authz-config');

        $this->registerGateHooks();
        $this->registerCommands();
    }

    protected function registerGateHooks(): void
    {
        $superAdminRole = (string) config('filament-authz.super_admin_role');

        if ($superAdminRole !== '') {
            Gate::before(static function ($user, string $ability) use ($superAdminRole) {
                return method_exists($user, 'hasRole') && $user->hasRole($superAdminRole) ? true : null;
            });
        }

        if (config('filament-authz.wildcard_permissions', true)) {
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
                DiscoverCommand::class,
                GeneratePoliciesCommand::class,
                SeederCommand::class,
                SuperAdminCommand::class,
                SyncAuthzCommand::class,
            ]);
        }
    }

    private function configureSpatiePermissions(): void
    {
        if (config('permission.models.permission') === SpatiePermission::class) {
            config()->set('permission.models.permission', AuthzPermission::class);
        }

        if (config('permission.models.role') === SpatieRole::class) {
            config()->set('permission.models.role', AuthzRole::class);
        }
    }

    private function registerTeamResolver(): void
    {
        if (! class_exists(\AIArmada\CommerceSupport\Support\OwnerContext::class)) {
            return;
        }

        if (! config('permission.teams', false)) {
            return;
        }

        $this->app->singleton(PermissionsTeamResolver::class, OwnerContextTeamResolver::class);
    }
}

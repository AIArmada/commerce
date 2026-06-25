<?php

declare(strict_types=1);

namespace AIArmada\Authz;

use AIArmada\Authz\Console\Commands\SuperAdminCommand;
use AIArmada\Authz\Console\Commands\SyncAuthzCommand;
use AIArmada\Authz\Guard\SessionGuard;
use AIArmada\Authz\Services\ImpersonateManager;
use AIArmada\Authz\Services\PermissionKeyBuilder;
use AIArmada\Authz\Services\WildcardPermissionResolver;
use AIArmada\Authz\Support\AuthzScopeContext;
use AIArmada\Authz\Support\AuthzScopeTeamResolver;
use AIArmada\Authz\Support\UserRoleChecker;
use AIArmada\CommerceSupport\Models\Permission as AuthzPermission;
use AIArmada\CommerceSupport\Models\Role as AuthzRole;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerContextTeamResolver;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Laravel\Octane\Events\RequestReceived;
use Spatie\Permission\Contracts\PermissionsTeamResolver;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

final class AuthzServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/authz.php', 'authz');

        $this->configureSpatiePermissions();

        $this->app->scoped(AuthzScopeContext::class, static fn (): AuthzScopeContext => new AuthzScopeContext);

        $this->app->singleton(WildcardPermissionResolver::class);
        $this->app->singleton(PermissionKeyBuilder::class);

        $this->app->singleton(Authz::class, function ($app): Authz {
            return new Authz(
                $app->make(PermissionKeyBuilder::class)
            );
        });

        $this->app->singleton(ImpersonateManager::class, function ($app): ImpersonateManager {
            return new ImpersonateManager($app);
        });

        $this->app->alias(ImpersonateManager::class, 'impersonate');

        $this->registerTeamResolver();
        $this->registerAuthDriver();
        $this->registerOctaneListeners();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->registerBladeDirectives();
        $this->registerImpersonationEventListeners();

        $this->publishes([
            __DIR__ . '/../config/authz.php' => config_path('authz.php'),
        ], 'authz-config');

        $this->registerGateHooks();
        $this->registerCommands();
    }

    protected function registerGateHooks(): void
    {
        $superAdminRole = (string) config('authz.super_admin_role');

        if ($superAdminRole !== '') {
            Gate::before(static function ($user, string $ability) use ($superAdminRole) {
                if (! method_exists($user, 'hasRole')) {
                    return null;
                }

                $registrar = app(PermissionRegistrar::class);
                $teams = $registrar->teams;
                $registrar->teams = false;

                try {
                    return UserRoleChecker::hasRole($user, $superAdminRole) ? true : null;
                } finally {
                    $registrar->teams = $teams;
                }
            });
        }

        if (config('authz.wildcard_permissions', true)) {
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
                SuperAdminCommand::class,
                SyncAuthzCommand::class,
            ]);
        }
    }

    private function registerTeamResolver(): void
    {
        if (config('authz.scopes.enabled', false)) {
            $this->app->singleton(PermissionsTeamResolver::class, AuthzScopeTeamResolver::class);

            return;
        }

        if (! class_exists(OwnerContext::class)) {
            return;
        }

        if (! config('permission.teams', false)) {
            return;
        }

        $this->app->singleton(PermissionsTeamResolver::class, OwnerContextTeamResolver::class);
    }

    private function configureSpatiePermissions(): void
    {
        if (
            config('permission.models.permission') === null
            || config('permission.models.permission') === SpatiePermission::class
        ) {
            config()->set('permission.models.permission', AuthzPermission::class);
        }

        if (
            config('permission.models.role') === null
            || config('permission.models.role') === SpatieRole::class
        ) {
            config()->set('permission.models.role', AuthzRole::class);
        }

        $defaultTables = [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ];

        foreach ($defaultTables as $key => $defaultTable) {
            $configuredTable = config("permission.table_names.{$key}");

            if ($configuredTable !== null && $configuredTable !== $defaultTable) {
                continue;
            }

            config()->set("permission.table_names.{$key}", authz_table($key));
        }
    }

    private function registerOctaneListeners(): void
    {
        if (! class_exists(RequestReceived::class)) {
            return;
        }

        $this->app['events']->listen(
            RequestReceived::class,
            function (): void {
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                if ($this->app->has(Authz::class)) {
                    app(Authz::class)->clearCache();
                }
            }
        );
    }

    private function registerAuthDriver(): void
    {
        /** @var AuthManager $auth */
        $auth = $this->app['auth'];

        $auth->extend('session', function (Application $app, string $name, array $config) use ($auth) {
            $provider = $auth->createUserProvider($config['provider'] ?? null);

            $guard = new SessionGuard(
                $name,
                $provider,
                $app['session.store'],
                $app['request'] ?? null
            );

            $guard->setCookieJar($app['cookie']);
            $guard->setDispatcher($app['events']);
            $guard->setRequest($app->refresh('request', $guard, 'setRequest'));

            if (isset($config['remember'])) {
                $guard->setRememberDuration($config['remember']);
            }

            return $guard;
        });
    }

    private function registerBladeDirectives(): void
    {
        $this->app->afterResolving('blade.compiler', function (BladeCompiler $blade): void {
            $blade->directive('impersonating', function (?string $guard = null): string {
                return "<?php if (\\AIArmada\\Authz\\is_impersonating({$guard})) : ?>";
            });

            $blade->directive('endImpersonating', function (): string {
                return '<?php endif; ?>';
            });

            $blade->directive('canImpersonate', function (?string $guard = null): string {
                return "<?php if (\\AIArmada\\Authz\\can_impersonate({$guard})) : ?>";
            });

            $blade->directive('endCanImpersonate', function (): string {
                return '<?php endif; ?>';
            });

            $blade->directive('canBeImpersonated', function (string $expression): string {
                $args = preg_split("/,(\s+)?/", $expression);
                $guard = $args[1] ?? 'null';

                return "<?php if (\\AIArmada\\Authz\\can_be_impersonated({$args[0]}, {$guard})) : ?>";
            });

            $blade->directive('endCanBeImpersonated', function (): string {
                return '<?php endif; ?>';
            });
        });
    }

    private function registerImpersonationEventListeners(): void
    {
        Event::listen(Login::class, function (): void {
            app(ImpersonateManager::class)->clear();
        });

        Event::listen(Logout::class, function (): void {
            app(ImpersonateManager::class)->clear();
        });
    }
}

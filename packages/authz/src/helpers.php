<?php

declare(strict_types=1);

namespace AIArmada\Authz;

use AIArmada\Authz\Services\ImpersonateManager;
use AIArmada\Authz\Support\UserRoleChecker;
use Illuminate\Contracts\Auth\Authenticatable;

if (! function_exists('AIArmada\Authz\is_impersonating')) {
    function is_impersonating(?string $guard = null): bool
    {
        if (! app()->bound(ImpersonateManager::class)) {
            return false;
        }

        return app(ImpersonateManager::class)->isImpersonating();
    }
}

if (! function_exists('AIArmada\Authz\can_impersonate')) {
    function can_impersonate(?string $guard = null): bool
    {
        if (! app()->bound(ImpersonateManager::class)) {
            return false;
        }

        $guard ??= app(ImpersonateManager::class)->getCurrentAuthGuardName();

        if ($guard === null) {
            return false;
        }

        $user = auth()->guard($guard)->user();

        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'canImpersonate')) {
            return (bool) $user->canImpersonate();
        }

        $superAdminRole = (string) config('authz.super_admin_role', '');

        return $superAdminRole !== ''
            && UserRoleChecker::hasRole($user, $superAdminRole);
    }
}

if (! function_exists('AIArmada\Authz\can_be_impersonated')) {
    function can_be_impersonated(Authenticatable $user, ?string $guard = null): bool
    {
        if (! app()->bound(ImpersonateManager::class)) {
            return false;
        }

        $guard ??= app(ImpersonateManager::class)->getCurrentAuthGuardName();

        if ($guard === null) {
            return false;
        }

        $currentUser = auth()->guard($guard)->user();

        if ($currentUser === null) {
            return false;
        }

        if ($currentUser->getAuthIdentifier() === $user->getAuthIdentifier()) {
            return false;
        }

        return ! method_exists($user, 'canBeImpersonated')
            || (bool) $user->canBeImpersonated();
    }
}

if (! function_exists('AIArmada\Authz\get_impersonator')) {
    function get_impersonator(): ?Authenticatable
    {
        if (! app()->bound(ImpersonateManager::class)) {
            return null;
        }

        return app(ImpersonateManager::class)->getImpersonator();
    }
}

if (! function_exists('AIArmada\Authz\authz_table')) {
    function authz_table(string $key): string
    {
        $tables = (array) config('authz.database.tables', []);
        $prefix = (string) config('authz.database.table_prefix', '');

        $table = $tables[$key] ?? $key;

        return $prefix . $table;
    }
}

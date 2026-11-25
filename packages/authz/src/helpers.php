<?php

declare(strict_types=1);

namespace AIArmada\Authz;

use AIArmada\Authz\Features\Impersonation\Api\ImpersonationApi;
use AIArmada\Authz\Support\UserRoleChecker;
use Illuminate\Contracts\Auth\Authenticatable;

if (! function_exists('AIArmada\Authz\is_impersonating')) {
    function is_impersonating(): bool
    {
        if (! app()->bound(ImpersonationApi::class)) {
            return false;
        }

        return app(ImpersonationApi::class)->isImpersonating();
    }
}

if (! function_exists('AIArmada\Authz\can_impersonate')) {
    function can_impersonate(?string $guard = null): bool
    {
        if (! app()->bound(ImpersonationApi::class)) {
            return false;
        }

        $guard ??= app(ImpersonationApi::class)->getCurrentAuthGuardName();

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
            && UserRoleChecker::hasGlobalRole($user, $superAdminRole);
    }
}

if (! function_exists('AIArmada\Authz\can_be_impersonated')) {
    function can_be_impersonated(Authenticatable $user, ?string $guard = null): bool
    {
        if (! app()->bound(ImpersonationApi::class)) {
            return false;
        }

        $guard ??= app(ImpersonationApi::class)->getCurrentAuthGuardName();

        if ($guard === null) {
            return false;
        }

        $currentUser = auth()->guard($guard)->user();

        if ($currentUser === null) {
            return false;
        }

        if ((string) $currentUser->getAuthIdentifier() === (string) $user->getAuthIdentifier()) {
            return false;
        }

        if (method_exists($user, 'canBeImpersonated') && ! $user->canBeImpersonated()) {
            return false;
        }

        return app(ImpersonationApi::class)->canAccessTarget($user);
    }
}

if (! function_exists('AIArmada\Authz\get_impersonator')) {
    function get_impersonator(): ?Authenticatable
    {
        if (! app()->bound(ImpersonationApi::class)) {
            return null;
        }

        return app(ImpersonationApi::class)->getImpersonator();
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

<?php

declare(strict_types=1);

namespace AIArmada\Authz\Support;

use AIArmada\CommerceSupport\Support\GlobalRoleChecker;

/**
 * Compatibility facade for the shared global-role checker.
 */
final class UserRoleChecker
{
    public static function hasRole(mixed $user, string $role): bool
    {
        return is_object($user)
            && method_exists($user, 'hasRole')
            && (bool) $user->hasRole($role);
    }

    public static function hasGlobalRole(mixed $user, string $role): bool
    {
        return GlobalRoleChecker::hasGlobalRole($user, $role);
    }

    public static function flushGlobalRoleCache(): void
    {
        GlobalRoleChecker::flushGlobalRoleCache();
    }

    public static function registerMutationListener(): void
    {
        GlobalRoleChecker::registerMutationListener();
    }
}

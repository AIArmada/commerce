<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use AIArmada\FilamentAuthz\Facades\Authz;
use Filament\Facades\Filament;

/**
 * Add this trait to Filament Pages to enforce permission checks.
 *
 * Features:
 * - Uses discovered permissions (not generated names)
 * - Caches permission lookups
 * - Super admin bypass built-in
 * - Falls back gracefully if permission not found
 */
trait HasPageAuthz
{
    protected static ?string $authzPermissionKey = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()?->user();

        if ($user === null) {
            return false;
        }

        $superAdminRole = config('filament-authz.super_admin_role');

        if ($superAdminRole && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return true;
        }

        $permission = static::getAuthzPermission();

        if ($permission === null) {
            return parent::canAccess();
        }

        return method_exists($user, 'can') && $user->can($permission);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && parent::shouldRegisterNavigation();
    }

    /**
     * Get the permission for this page from discovered entities.
     * Caches the result for performance.
     */
    public static function getAuthzPermission(): ?string
    {
        if (static::$authzPermissionKey === null) {
            static::$authzPermissionKey = Authz::getPagePermission(static::class) ?? '';
        }

        return static::$authzPermissionKey !== '' ? static::$authzPermissionKey : null;
    }

    /**
     * Override to use a custom permission key.
     */
    public static function authzPermission(): ?string
    {
        return null;
    }
}

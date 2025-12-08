<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use UnitEnum;

/**
 * Trait for Filament resources to auto-register their permissions.
 *
 * Add this trait to any Filament Resource to enable automatic permission discovery
 * by the FilamentAuthz plugin.
 *
 * Usage:
 * ```php
 * class VoucherResource extends Resource implements RegistersPermissions
 * {
 *     use HasAutoPermissions;
 *
 *     protected static ?string $model = Voucher::class;
 * }
 * ```
 */
trait HasAutoPermissions
{
    /**
     * Get the permission key for this resource.
     *
     * Derives from the model class name if not overridden.
     */
    public static function getPermissionKey(): string
    {
        if (property_exists(static::class, 'permissionKey') && static::$permissionKey !== null) {
            return static::$permissionKey;
        }

        // Derive from model name: App\Models\Voucher -> voucher
        $model = static::getModel();
        $shortName = class_basename($model);

        return (string) str($shortName)->snake();
    }

    /**
     * Get the abilities/actions this resource supports.
     *
     * @return array<string>
     */
    public static function getPermissionAbilities(): array
    {
        if (property_exists(static::class, 'permissionAbilities')) {
            return static::$permissionAbilities;
        }

        // Default CRUD abilities
        return ['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny'];
    }

    /**
     * Get the permission group for this resource.
     *
     * Uses the navigation group by default.
     */
    public static function getPermissionGroup(): ?string
    {
        if (property_exists(static::class, 'permissionGroup') && static::$permissionGroup !== null) {
            return static::$permissionGroup;
        }

        // Use navigation group if available
        if (method_exists(static::class, 'getNavigationGroup')) {
            $group = static::getNavigationGroup();

            if ($group instanceof UnitEnum) {
                return $group->name;
            }

            return is_string($group) ? $group : null;
        }

        return null;
    }

    /**
     * Whether to register a wildcard permission (resource.*).
     */
    public static function shouldRegisterWildcard(): bool
    {
        if (property_exists(static::class, 'registerWildcardPermission')) {
            return (bool) static::$registerWildcardPermission;
        }

        return true; // Default to registering wildcard
    }

    /**
     * Get full permission names for this resource.
     *
     * @return array<string>
     */
    public static function getPermissionNames(): array
    {
        $key = static::getPermissionKey();
        $permissions = [];

        foreach (static::getPermissionAbilities() as $ability) {
            $permissions[] = "{$key}.{$ability}";
        }

        if (static::shouldRegisterWildcard()) {
            $permissions[] = "{$key}.*";
        }

        return $permissions;
    }

    /**
     * Check if the current user can perform an ability on this resource.
     */
    public static function canPerform(string $ability): bool
    {
        $key = static::getPermissionKey();
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        // Check specific permission or wildcard
        return $user->can("{$key}.{$ability}") || $user->can("{$key}.*");
    }
}

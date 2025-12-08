<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Contracts;

/**
 * Interface for resources that want to register their permissions automatically.
 *
 * Implement this interface in your Filament resources to have their permissions
 * automatically discovered and registered by the FilamentAuthz plugin.
 */
interface RegistersPermissions
{
    /**
     * Get the permission key for this resource.
     *
     * This is typically the snake_case version of the resource model name.
     * Example: 'voucher', 'campaign', 'gift_card'
     */
    public static function getPermissionKey(): string;

    /**
     * Get the abilities/actions this resource supports.
     *
     * @return array<string>
     */
    public static function getPermissionAbilities(): array;

    /**
     * Get the permission group for this resource.
     *
     * Used for grouping permissions in the UI.
     */
    public static function getPermissionGroup(): ?string;

    /**
     * Whether to register a wildcard permission (resource.*).
     */
    public static function shouldRegisterWildcard(): bool;
}

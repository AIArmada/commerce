<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

/**
 * Interface for resolving the current tenant ID for multi-tenancy support.
 *
 * Implementations should return the current tenant's identifier based on
 * the application's tenancy strategy (e.g., subdomain, path, header, etc.)
 *
 * @example
 * ```php
 * class AppTenantResolver implements CartTenantResolverInterface
 * {
 *     public function resolve(): ?string
 *     {
 *         // Using Spatie multitenancy
 *         return Tenant::current()?->id;
 *
 *         // Using Stancl/tenancy
 *         return tenant()?->id;
 *
 *         // Using Filament panels
 *         return Filament::getTenant()?->id;
 *
 *         // Custom header-based
 *         return request()->header('X-Tenant-ID');
 *     }
 * }
 * ```
 */
interface CartTenantResolverInterface
{
    /**
     * Resolve the current tenant ID.
     *
     * @return string|null The tenant ID, or null if no tenant context exists
     */
    public function resolve(): ?string;
}

# Multi-Tenancy

The Cart package supports multi-tenant applications through configurable tenant isolation. When enabled, all cart operations are scoped to the current tenant, ensuring complete data isolation between tenants.

## Configuration

Enable multi-tenancy in your environment or config file:

```php
// config/cart.php
'tenancy' => [
    'enabled' => env('CART_TENANCY_ENABLED', false),
    'resolver' => env('CART_TENANT_RESOLVER'),
    'column' => env('CART_TENANT_COLUMN', 'tenant_id'),
],
```

### Environment Variables

```env
CART_TENANCY_ENABLED=true
CART_TENANT_RESOLVER=App\Services\CartTenantResolver
CART_TENANT_COLUMN=tenant_id
```

## Implementing a Tenant Resolver

Create a class that implements `CartTenantResolverInterface`:

```php
<?php

namespace App\Services;

use AIArmada\Cart\Contracts\CartTenantResolverInterface;

class CartTenantResolver implements CartTenantResolverInterface
{
    /**
     * Resolve the current tenant ID.
     *
     * @return string|null The tenant ID, or null if no tenant context
     */
    public function resolve(): ?string
    {
        // Example: Get tenant from authenticated user
        if (auth()->check()) {
            return auth()->user()->tenant_id;
        }

        // Example: Get tenant from subdomain
        $host = request()->getHost();
        $subdomain = explode('.', $host)[0];
        return Tenant::where('subdomain', $subdomain)->value('id');

        // Example: Get tenant from header (for APIs)
        return request()->header('X-Tenant-ID');
    }
}
```

## Database Migration

When tenancy is enabled, run migrations to add the `tenant_id` column:

```bash
php artisan migrate
```

The migration is conditional and only runs when `cart.tenancy.enabled` is `true`.

## Storage Drivers

All three storage drivers support multi-tenancy:

### Database Storage

The `tenant_id` column is added to the carts table and included in all queries. The unique constraint becomes `(tenant_id, identifier, instance)`.

### Session Storage

Session keys are prefixed with the tenant ID:
- Without tenant: `cart.{identifier}.{instance}.items`
- With tenant: `cart.tenant.{tenant_id}.{identifier}.{instance}.items`

### Cache Storage

Cache keys are prefixed with the tenant ID:
- Without tenant: `cart.{identifier}.{instance}.items`
- With tenant: `cart.tenant.{tenant_id}.{identifier}.{instance}.items`

## Admin Operations

For admin panels or background jobs that need to operate on a specific tenant's carts, use the `forTenant()` method:

```php
use AIArmada\Cart\Facades\Cart;

// Get a cart manager scoped to a specific tenant
$tenantCart = Cart::forTenant('tenant-uuid-123');

// All operations are now scoped to that tenant
$cart = $tenantCart->getCartInstance('default', 'user-456');
$items = $cart->getContent();

// Get cart by ID within tenant scope
$cart = $tenantCart->getById('cart-uuid');
```

## Checking Current Tenant

```php
use AIArmada\Cart\Facades\Cart;

// Get the current tenant ID
$tenantId = Cart::getTenantId();

if ($tenantId === null) {
    // Operating without tenant scope (single-tenant mode)
}
```

## Fail-Fast Behavior

When tenancy is enabled, the package validates the resolver during boot:

1. Resolver class must be configured
2. Resolver class must exist
3. Resolver must implement `CartTenantResolverInterface`

If any validation fails, a `RuntimeException` is thrown immediately, preventing silent failures.

## Cart Migration with Tenancy

Cart migrations (guest to user) are automatically scoped to the current tenant:

```php
use AIArmada\Cart\Services\CartMigrationService;

$migrationService = new CartMigrationService();

// Migration happens within current tenant context
$migrationService->migrateGuestCartToUser($userId, 'default', $guestSessionId);
```

## Backwards Compatibility

Multi-tenancy is fully backwards compatible:

- Disabled by default (`CART_TENANCY_ENABLED=false`)
- Existing single-tenant installations work without changes
- Migration only runs when tenancy is enabled
- All storage drivers work with or without tenant scope

## Example: Filament Integration

When using with Filament in a multi-tenant setup:

```php
<?php

namespace App\Services;

use AIArmada\Cart\Contracts\CartTenantResolverInterface;
use Filament\Facades\Filament;

class FilamentCartTenantResolver implements CartTenantResolverInterface
{
    public function resolve(): ?string
    {
        return Filament::getTenant()?->id;
    }
}
```

## Best Practices

1. **Always configure resolver when enabling tenancy** - The fail-fast behavior ensures you don't accidentally run without tenant isolation.

2. **Use `forTenant()` for admin operations** - Never bypass tenant resolution for admin panels; explicitly scope operations.

3. **Test tenant isolation** - Write tests that verify carts from one tenant are not visible to another.

4. **Consider cache invalidation** - When switching tenants, ensure session/cache storage doesn't leak between tenants.

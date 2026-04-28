---
title: Multi-Tenancy
---

# Multi-Tenancy

The Cart package uses `commerce-support` owner scoping for cart rows, storage queries, condition definitions, and operational commands.

## Enabling Owner Scoping

```php
// config/cart.php
'owner' => [
    'enabled' => true,
    'include_global' => false,
],
```

## Contract summary

When owner mode is enabled:

1. cart rows are isolated by owner tuple
2. missing owner context fails closed
3. global rows require explicit global context
4. commands and listeners must pass or iterate owners explicitly
5. malformed owner tuples are treated as invalid data, not as safe globals

## Setting the Owner

### Via cart manager

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\CommerceSupport\Support\OwnerContext;

// Create a scoped cart manager for this owner
$tenantCart = Cart::forOwner($tenant);

// Or get a scoped cart instance
$cart = $tenantCart->instance('default');

// For non-request operations, use explicit owner context
OwnerContext::withOwner($tenant, function () {
    Cart::add('SKU-001', 'Product', 999, 1);
});
```

### Via Storage

```php
$storage = Cart::storage()->withOwner($tenant);
$items = $storage->getItems('user-123', 'default');
```

## Schema

The carts table stores the public owner boundary using `owner_type` and `owner_id`.
The storage layer also maintains `owner_scope` as an internal uniqueness key.

```php
Schema::table('carts', function (Blueprint $table) {
    $table->string('owner_type')->nullable()->index();
    $table->string('owner_id')->nullable()->index();
    $table->string('owner_scope')->default('global')->index();
});
```

`owner_scope` is an internal implementation detail. Do not authorize against it and do not expose it as the tenancy contract.

The current physical columns remain `owner_type` / `owner_id`, but the package now aligns with `commerce-support`'s configurable owner-column contract in code paths that work with raw rows and tuple parsing.

## Query behavior

### With Owner Set

```sql
SELECT * FROM carts 
WHERE identifier = 'user-123'
  AND instance = 'default'
  AND owner_type = 'App\Models\Tenant'
  AND owner_id = '456'
```

### Explicit global

When the call site enters explicit global context, cart queries return only global rows:

```sql
SELECT * FROM carts 
WHERE identifier = 'user-123'
  AND instance = 'default'
    AND owner_type IS NULL
    AND owner_id IS NULL
```

## Global vs tenant rows

Records can be:

- **Tenant-Scoped**: `owner_type` and `owner_id` set
- **Global**: `owner_type` and `owner_id` are null

### Including global rows

```php
'owner' => [
    'enabled' => true,
    'include_global' => true, // Include global records in queries
],
```

With `include_global: true`, tenant queries also return global records:

```sql
SELECT * FROM carts 
WHERE identifier = 'user-123'
  AND instance = 'default'
  AND (
    (owner_type = 'App\Models\Tenant' AND owner_id = '456')
        OR (owner_type IS NULL AND owner_id IS NULL)
  )
```

## Condition model scoping

The `Condition` model also supports owner scoping:

```php
use AIArmada\Cart\Models\Condition;

// Get tenant-specific conditions
$conditions = Condition::forOwner($tenant)->active()->get();

// Get global-only conditions
$globalConditions = Condition::forOwner(null)->active()->get();

// Both tenant and global
$allConditions = Condition::forOwner($tenant, includeGlobal: true)->get();
```

## HTTP middleware integration

Create middleware to set the owner context:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

class SetCartOwner
{
    public function handle($request, Closure $next)
    {
        if ($tenant = $request->route('tenant')) {
            OwnerContext::setForRequest($tenant);
        }
        
        return $next($request);
    }
}
```

Use `OwnerContext::setForRequest()` only in HTTP middleware/integration points.
For jobs, commands, listeners, and other non-request surfaces, use `OwnerContext::withOwner(...)`.

## Commands

`cart:clear-abandoned` is fail-closed in owner mode.

- without resolved owner context: command fails
- with explicit global context: command operates on global rows only
- with `--all-owners`: command iterates owner tuples intentionally
- with malformed tuples: command warns and skips by default
- with `--strict-owner-tuples`: command aborts on malformed tuples

This command now uses the shared `commerce-support` owner tuple parser rather than re-implementing tuple validation locally.

## Testing multi-tenancy

Configure owner context in your service provider:

```php
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Set owner based on authenticated user's tenant
        Cart::macro('forCurrentTenant', function () {
            $tenant = auth()->user()?->tenant;
            return $tenant ? Cart::forOwner($tenant) : Cart::getFacadeRoot();
        });
    }
}
```

```php
use AIArmada\Cart\Facades\Cart;

it('isolates carts between tenants', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    // Add item for tenant 1
    Cart::forOwner($tenant1)->add('SKU-001', 'Product', 999, 1);
    
    // Add item for tenant 2
    Cart::forOwner($tenant2)->add('SKU-002', 'Different', 1999, 1);
    
    // Tenant 1 only sees their cart
    expect(Cart::forOwner($tenant1)->getItems())->toHaveCount(1);
    expect(Cart::forOwner($tenant1)->get('SKU-001'))->not->toBeNull();
    expect(Cart::forOwner($tenant1)->get('SKU-002'))->toBeNull();
    
    // Tenant 2 only sees their cart
    expect(Cart::forOwner($tenant2)->getItems())->toHaveCount(1);
    expect(Cart::forOwner($tenant2)->get('SKU-001'))->toBeNull();
    expect(Cart::forOwner($tenant2)->get('SKU-002'))->not->toBeNull();
});
```

## Storage and raw queries

Storage-level query builder paths use the package-level `CartOwnerScope`, which now delegates to the shared `commerce-support` owner tuple utilities and `OwnerQuery`.

That means raw storage queries, abandoned-cart cleanup, and event payload reconstruction follow the same tuple rules as the model layer.

## Models

`CartModel` and `Condition` both consume `commerce-support` traits:

```php
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;

class CartModel extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    
    protected static string $ownerScopeConfigKey = 'cart.owner';
}
```

## Operational events

`CartDestroyed` now carries explicit snake_case owner tuple fields:

- `owner_type`
- `owner_id`

Consumers should treat those as the canonical owner payload contract.

## Best practices

1. Prefer `OwnerContext::withOwner(...)` outside HTTP middleware.
2. Treat malformed owner tuples as invalid data.
3. Use `--strict-owner-tuples` for hard-stop cleanup sweeps.
4. Keep global mutations explicit.

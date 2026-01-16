---
title: Multitenancy
---

# Multitenancy

The pricing package fully supports multitenancy through owner scoping, allowing different tenants (owners) to have their own isolated pricing data.

## Enabling Multitenancy

Enable owner scoping in your configuration:

```php
// config/pricing.php
'features' => [
    'owner' => [
        'enabled' => env('PRICING_OWNER_ENABLED', true),
        'include_global' => false,
    ],
],
```

Or via environment variable:

```bash
PRICING_OWNER_ENABLED=true
```

## How It Works

When multitenancy is enabled:

1. **All queries are automatically scoped** to the current owner context
2. **Writes are validated** to ensure data belongs to the correct owner
3. **Cross-tenant operations are prevented** by authorization checks
4. **Owner is automatically assigned** when creating new records

## Owner Context

The package uses `OwnerContext` from `commerce-support` to resolve the current owner:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

// Set owner context (typically in middleware)
OwnerContext::set($tenant);

// Resolve current owner
$owner = OwnerContext::resolve();
```

## Querying with Owner Scope

### Automatic Scoping

When owner mode is enabled, queries are automatically scoped:

```php
// Automatically scoped to current owner
$priceLists = PriceList::all();
$prices = Price::where('amount', '>', 1000)->get();
```

### Explicit Scoping

Use the `forOwner` scope for explicit control:

```php
// Scope to specific owner
$lists = PriceList::forOwner($tenant)->get();

// Include global (ownerless) records
$lists = PriceList::forOwner($tenant, includeGlobal: true)->get();

// Global records only
$globalLists = PriceList::whereNull('owner_id')->get();
```

## PricingOwnerScope Helper

The `PricingOwnerScope` helper provides utility methods:

```php
use AIArmada\Pricing\Support\PricingOwnerScope;

// Check if owner scoping is enabled
if (PricingOwnerScope::isEnabled()) {
    // Owner scoping is active
}

// Check if global records should be included
$includeGlobal = PricingOwnerScope::includeGlobal();

// Resolve current owner
$owner = PricingOwnerScope::resolveOwner();

// Apply scoping to any query
$query = PricingOwnerScope::applyToOwnedQuery(
    PriceList::query()
);
```

## Write Protection

The package enforces owner boundaries on all write operations:

### Creating Records

```php
// Owner is automatically assigned from context
$priceList = PriceList::create([
    'name' => 'Retail',
    'slug' => 'retail',
]);
// $priceList->owner_type = 'App\Models\Tenant'
// $priceList->owner_id = 'current-tenant-uuid'
```

### Updating Records

```php
// This will throw AuthorizationException if price list
// belongs to a different owner
$priceList->update(['name' => 'Updated Name']);
```

### Deleting Records

```php
// Protected against cross-tenant deletion
$priceList->delete(); // Validates owner first
```

## Validation on Foreign Keys

When saving prices or tiers, the package validates that related records belong to the same owner:

```php
// This validates:
// 1. Price list exists in owner scope
// 2. Priceable entity exists in owner scope (if it uses HasOwner)
$price = Price::create([
    'price_list_id' => $priceList->id,
    'priceable_type' => Product::class,
    'priceable_id' => $product->id,
    'amount' => 5000,
]);
```

## Global Records

Global records (where `owner_type` and `owner_id` are `null`) can be shared across all tenants:

```php
// Create a global price list (requires null owner context)
OwnerContext::clear();

$globalList = PriceList::create([
    'name' => 'Default Retail',
    'slug' => 'default-retail',
    'is_default' => true,
]);

// Access global records with include_global
PriceList::forOwner($tenant, includeGlobal: true)->get();
```

## Price Calculator with Multitenancy

The price calculator automatically respects owner scoping:

```php
$calculator = app(PriceCalculatorInterface::class);

// Context automatically uses current owner
// All price lookups are owner-scoped
$result = $calculator->calculate($product, 1, [
    'customer_id' => $customer->id,
]);
```

## Best Practices

1. **Always set owner context** in middleware before processing requests
2. **Use `forOwner()` scope** when querying across different contexts
3. **Test cross-tenant protection** with regression tests
4. **Be careful with global records** - they're accessible to all tenants
5. **Validate foreign IDs** even in Filament forms (defense-in-depth)

## Example: Middleware Setup

```php
namespace App\Http\Middleware;

use AIArmada\CommerceSupport\Support\OwnerContext;

class SetOwnerContext
{
    public function handle($request, $next)
    {
        $tenant = $request->user()?->tenant;
        
        if ($tenant) {
            OwnerContext::set($tenant);
        }
        
        return $next($request);
    }
}
```

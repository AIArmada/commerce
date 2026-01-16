---
title: Multitenancy
---

# Multitenancy

The Filament Pricing package fully supports multitenancy, automatically scoping all resources and actions to the current owner context.

## How It Works

When `pricing.features.owner.enabled` is `true`:

1. **Resources** scope their queries to the current owner
2. **Relation managers** validate foreign keys against owner scope
3. **Selects/searches** only show owner-accessible records
4. **Widgets** display owner-scoped statistics

## Resource Scoping

### PriceListResource

```php
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();

    if (! (bool) config('pricing.features.owner.enabled', false)) {
        return $query;
    }

    $owner = self::resolveOwner();

    return $query->forOwner(
        $owner,
        (bool) config('pricing.features.owner.include_global', false),
    );
}

private static function resolveOwner(): ?Model
{
    if (! (bool) config('pricing.features.owner.enabled', false)) {
        return null;
    }

    return OwnerContext::resolve();
}
```

### PromotionResource

Similar pattern using `promotions.features.owner.enabled` config.

## Relation Manager Scoping

Both `PricesRelationManager` and `TiersRelationManager` scope their selects:

```php
private function resolveOwner(): ?Model
{
    return OwnerContext::resolve();
}

// In form field search
->getSearchResultsUsing(function (string $search) {
    $owner = $this->resolveOwner();

    $query = OwnerQuery::applyToEloquentBuilder(
        Product::query(),
        $owner,
        (bool) config('products.features.owner.include_global', false)
    );

    return $query
        ->where('name', 'like', "%{$search}%")
        ->limit(50)
        ->pluck('name', 'id')
        ->toArray();
})
```

## Price Simulator Scoping

The simulator scopes all queries:

```php
private function resolveOwner(): ?Model
{
    return OwnerContext::resolve();
}

private function scopeQueryForOwner(
    string $modelClass, 
    Builder $query, 
    ?Model $owner
): Builder {
    $model = new $modelClass;

    if (method_exists($model, 'scopeForOwner')) {
        return $model->scopeForOwner($query, $owner);
    }

    return $query;
}
```

This is applied to:
- Product searches
- Variant searches
- Customer searches (if package installed)

## Widget Scoping

The `PricingStatsWidget` applies owner scoping:

```php
$activePriceLists = PricingOwnerScope::applyToOwnedQuery(
    PriceList::query()
)->active()->count();

// For promotions
if (PromotionsOwnerScope::isEnabled()) {
    $promotionQuery = $promotionQuery->forOwner();
}
```

## Setting Owner Context

Owner context should be set in your Filament panel provider or middleware:

### In Panel Provider

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

public function panel(Panel $panel): Panel
{
    return $panel
        ->tenant(Tenant::class)
        ->middleware([
            // ...existing middleware
        ])
        ->tenantMiddleware([
            SetOwnerContext::class,
        ]);
}
```

### Middleware Example

```php
namespace App\Http\Middleware;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Closure;
use Filament\Facades\Filament;

class SetOwnerContext
{
    public function handle($request, Closure $next)
    {
        $tenant = Filament::getTenant();
        
        if ($tenant) {
            OwnerContext::set($tenant);
        }
        
        return $next($request);
    }
}
```

## Testing Multitenancy

Verify scoping works correctly:

```php
// Test that resources show only owner's data
it('scopes price lists to current owner', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    $list1 = PriceList::factory()->for($tenant1, 'owner')->create();
    $list2 = PriceList::factory()->for($tenant2, 'owner')->create();
    
    OwnerContext::set($tenant1);
    
    livewire(ListPriceLists::class)
        ->assertCanSeeTableRecords([$list1])
        ->assertCanNotSeeTableRecords([$list2]);
});
```

## Configuration

Enable multitenancy via config or environment:

```php
// config/pricing.php
'features' => [
    'owner' => [
        'enabled' => env('PRICING_OWNER_ENABLED', false),
        'include_global' => false,
    ],
],
```

```bash
# .env
PRICING_OWNER_ENABLED=true
```

## Global Records

When `include_global` is `true`, queries include records where `owner_type` and `owner_id` are both `null`.

This is useful for:
- Default price lists shared across all tenants
- Global promotions applicable to everyone

To create global records, clear the owner context first:

```php
OwnerContext::clear();

$globalList = PriceList::create([
    'name' => 'Global Default',
    'is_default' => true,
    // owner_type and owner_id remain null
]);
```

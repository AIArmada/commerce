---
title: Troubleshooting
---

# Troubleshooting

## Common Issues

### Resources Not Appearing

**Symptom**: Price Lists or Promotions don't show in navigation.

**Checks**:

1. Verify plugin is registered:
```php
// In your Panel provider
->plugins([
    FilamentPricingPlugin::make(),
])
```

2. Clear caches:
```bash
php artisan filament:clear-cached-components
php artisan cache:clear
```

3. For PromotionResource, ensure promotions package is installed:
```bash
composer show aiarmada/promotions
```

### Price Simulator Not Available

**Symptom**: Price Simulator page doesn't appear.

**Cause**: Products package not installed.

**Solution**:
```bash
composer require aiarmada/products
```

### Customer Select Not Showing in Simulator

**Symptom**: Customer field is hidden in Price Simulator.

**Cause**: Customers package not installed.

**Solution**:
```bash
composer require aiarmada/customers
```

### Empty Product/Variant Selects

**Symptom**: Searchable selects return no results.

**Checks**:

1. **Owner scoping**: If multitenancy enabled, ensure owner context is set
```php
// Check owner context
dd(OwnerContext::resolve());
```

2. **Data exists**: Verify products exist in database
```php
Product::count();
```

3. **Owner assignment**: Verify products belong to current owner
```php
Product::forOwner()->count();
```

### Settings Not Saving

**Symptom**: Changes in Pricing Settings don't persist.

**Checks**:

1. Run settings migrations:
```bash
php artisan migrate
```

2. Check settings table:
```sql
SELECT * FROM settings WHERE group = 'pricing';
```

3. Verify settings class registration in Spatie settings config.

### Relation Managers Not Showing

**Symptom**: Prices/Tiers tabs missing on Price List view/edit.

**Cause**: Products package not installed.

**Check**:
```php
// In PriceListResource::getRelations()
// Only returns managers if products package exists
if (! class_exists('\\AIArmada\\Products\\Models\\Product')) {
    return [];
}
```

**Solution**:
```bash
composer require aiarmada/products
```

### Duplicate Action Fails

**Symptom**: Duplicate promotion action throws error.

**Checks**:

1. User has create permission:
```php
static::canCreate(); // Must return true
```

2. Unique constraint on code:
```php
// Code is set to null on duplicate
$new->code = null;
```

### Widget Shows Zero Stats

**Symptom**: PricingStatsWidget shows all zeros.

**Checks**:

1. Data exists:
```php
PriceList::active()->count();
```

2. Owner scoping correct:
```php
PricingOwnerScope::applyToOwnedQuery(PriceList::query())->count();
```

3. Promotions stats (if expected):
```php
// Requires promotions package
class_exists(Promotion::class);
```

---

## Authorization Issues

### Cannot Create/Edit Records

**Symptom**: Getting authorization errors when saving.

**Checks**:

1. Filament policies configured correctly
2. Owner context is set for multitenancy
3. Record belongs to current owner

### Cross-Tenant Access Blocked

**Symptom**: `AuthorizationException` when accessing records.

**Cause**: Attempting to access record belonging to different owner.

**Solution**: This is expected behavior. Ensure correct owner context:
```php
OwnerContext::set($correctTenant);
```

---

## Performance Issues

### Slow Product Searches

**Symptom**: Searchable selects are slow.

**Solutions**:

1. Add database indexes:
```sql
CREATE INDEX idx_products_name ON products (name);
CREATE INDEX idx_variants_sku ON variants (sku);
```

2. Limit search results (already set to 50)

3. Optimize search queries with specific columns

### Widget Polling Overhead

**Symptom**: Dashboard feels slow.

**Solution**: Increase polling interval:
```php
protected ?string $pollingInterval = '120s'; // Extend to 2 minutes
```

Or disable polling:
```php
protected ?string $pollingInterval = null;
```

---

## Debugging Tips

### Check Plugin Registration

```php
// In tinker
$panel = Filament::getPanel();
$plugins = $panel->getPlugins();
dd(array_keys($plugins));
// Should include 'filament-pricing'
```

### Verify Registered Resources

```php
$panel = Filament::getPanel();
$resources = $panel->getResources();
dd($resources);
```

### Check Owner Context in Requests

Add temporary debugging:
```php
// In a resource
public static function getEloquentQuery(): Builder
{
    logger('Owner Context', [
        'owner' => OwnerContext::resolve(),
        'enabled' => config('pricing.features.owner.enabled'),
    ]);
    
    // ... rest of method
}
```

### Test Calculation Manually

```php
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;

$calc = app(PriceCalculatorInterface::class);
$product = Product::first();

$result = $calc->calculate($product, 1);
dd($result->toArray());
```

---

## Getting Help

1. Check activity logs for price changes:
```php
$priceList->activities()->get();
```

2. Verify database state:
```sql
SELECT * FROM price_lists WHERE is_active = true;
SELECT * FROM prices WHERE price_list_id = 'uuid';
```

3. Test with multitenancy disabled:
```bash
# Temporarily
PRICING_OWNER_ENABLED=false
```

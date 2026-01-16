---
title: Troubleshooting
---

# Troubleshooting

## Common Issues

### Price Calculator Returns Base Price

**Symptom**: The calculator always returns the base price, ignoring price lists and tiers.

**Possible Causes**:

1. **No active price list**: Ensure at least one price list has `is_active = true` and current date is within `starts_at` / `ends_at` range.

```php
// Check active price lists
$active = PriceList::active()->get();
dd($active->count()); // Should be > 0
```

2. **No matching price**: The priceable item has no price entry in the active price list.

```php
// Check prices for item
$prices = Price::where('priceable_type', Product::class)
    ->where('priceable_id', $product->id)
    ->get();
dd($prices);
```

3. **Owner scoping mismatch**: In multitenancy mode, the price list/price might belong to a different owner.

```php
// Check owner context
$owner = OwnerContext::resolve();
dd($owner);

// Check price list owner
dd($priceList->owner_type, $priceList->owner_id);
```

4. **Quantity not met**: The `min_quantity` on the price is higher than requested quantity.

```php
// Check min_quantity
$prices = Price::where('priceable_id', $product->id)
    ->orderBy('min_quantity')
    ->get();
```

### Tier Pricing Not Applied

**Symptom**: Tier pricing is ignored even with qualifying quantities.

**Checks**:

1. Verify tier exists for the item:
```php
$tiers = PriceTier::where('tierable_type', Product::class)
    ->where('tierable_id', $product->id)
    ->ordered()
    ->get();
```

2. Check quantity falls within tier range:
```php
$tier = PriceTier::forQuantity(25)->first();
```

3. Verify tier's price list association:
```php
// Global tiers (no price_list_id) or matching price_list_id
$tier->price_list_id; // null = applies to all
```

### Customer-Specific Price Not Working

**Symptom**: Customer-specific prices are not being applied.

**Checks**:

1. Ensure `customer_id` is passed in context:
```php
$result = $calculator->calculate($product, 1, [
    'customer_id' => $customer->id, // Must be string UUID
]);
```

2. Check price list is assigned to customer:
```php
$list = PriceList::where('customer_id', $customer->id)
    ->active()
    ->first();
```

3. Verify price exists in customer's price list:
```php
$price = Price::where('price_list_id', $list->id)
    ->where('priceable_id', $product->id)
    ->first();
```

### AuthorizationException on Create/Update

**Symptom**: Getting `AuthorizationException` when creating or updating records.

**Causes**:

1. **No owner context set**: In multitenancy mode, owner context is required.

```php
// Set owner context
OwnerContext::set($tenant);
```

2. **Mismatched owner**: Trying to update record belonging to different owner.

```php
// Check record owner
dd($priceList->owner_type, $priceList->owner_id);

// Check current context
dd(OwnerContext::resolve());
```

3. **Invalid foreign key**: Price list or priceable not in owner scope.

```php
// Verify price list is accessible
$exists = PriceList::forOwner()
    ->whereKey($priceListId)
    ->exists();
```

### Settings Not Saving

**Symptom**: Changes to pricing settings don't persist.

**Checks**:

1. Run settings migrations:
```bash
php artisan migrate
```

2. Verify settings table exists:
```sql
SELECT * FROM settings WHERE group = 'pricing';
```

3. Check settings class is registered:
```php
$settings = app(PricingSettings::class);
dd($settings->defaultCurrency);
```

---

## Debugging Price Calculations

### Enable Breakdown

The `breakdown` array in `PriceResultData` shows the calculation path:

```php
$result = $calculator->calculate($product, 10);

foreach ($result->breakdown as $step) {
    dump($step);
}
```

### Simulate Different Dates

Test time-based pricing:

```php
$result = $calculator->calculate($product, 1, [
    'effective_at' => '2024-12-25 00:00:00',
]);
```

### Check Active Conditions

```php
// Price list active check
$priceList = PriceList::find($id);
dd([
    'is_active' => $priceList->is_active,
    'starts_at' => $priceList->starts_at,
    'ends_at' => $priceList->ends_at,
    'isActive()' => $priceList->isActive(),
]);

// Price active check
$price = Price::find($id);
dd([
    'starts_at' => $price->starts_at,
    'ends_at' => $price->ends_at,
    'isActive()' => $price->isActive(),
]);
```

---

## Performance Tips

### Index Optimization

Ensure these indexes exist (created by migrations):

```sql
-- price_lists
CREATE INDEX ON price_lists (is_active, priority);
CREATE INDEX ON price_lists (starts_at, ends_at);
CREATE INDEX ON price_lists (customer_id);
CREATE INDEX ON price_lists (segment_id);

-- prices  
CREATE INDEX ON prices (priceable_type, priceable_id, price_list_id);
CREATE INDEX ON prices (starts_at, ends_at);

-- price_tiers
CREATE INDEX ON price_tiers (min_quantity, max_quantity);
```

### Eager Loading

When displaying multiple products with prices:

```php
$products = Product::with(['prices.priceList'])->get();
```

### Cache Price Lists

For high-traffic applications:

```php
$activeLists = Cache::remember('active_price_lists', 3600, function () {
    return PriceList::active()->get();
});
```

---

## Getting Help

If you encounter issues not covered here:

1. Check the activity log for changes:
```php
$priceList->activities()->get();
```

2. Review database state directly:
```sql
SELECT * FROM price_lists WHERE is_active = true;
SELECT * FROM prices WHERE priceable_id = 'uuid';
```

3. Test with owner scoping disabled:
```php
// Temporarily in .env
PRICING_OWNER_ENABLED=false
```

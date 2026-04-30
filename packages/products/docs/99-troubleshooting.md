---
title: Troubleshooting
---

# Troubleshooting

## Owner-scoped queries fail with missing owner context

If `products.features.owner.enabled` is `true`, owner-aware reads require either:

- a resolved current owner, or
- explicit global context via `OwnerContext::withOwner(null, ...)`

Verify your `OwnerResolverInterface` binding first.

## Global record writes are rejected

This is expected. Persisted global rows are read-only unless the call site enters explicit global context.

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

OwnerContext::withOwner(null, function () use ($category): void {
    $category->update(['name' => 'Updated Global Category']);
});
```

## Prices look 100x off

The package defaults to minor units.

- `2999` means RM 29.99 when `defaults.store_money_in_cents` is `true`
- use `getFormattedPrice()` and related helpers for display

## Variant SKU output is unexpected

`Variant::generateSku()` uses `products.features.variants.sku_pattern`. The default pattern is:

```php
'{parent_sku}-{option_codes}'
```

If the parent product has no SKU, the variant falls back to a `PROD-...` prefix derived from the product UUID.

## Automatic collections are empty

Use `Collection::getMatchingProducts()` for automatic collections. Common causes of empty results are:

- conditions referencing the wrong field names
- owner scoping excluding records from another tenant
- product status or visibility filters excluding candidates

## Custom attribute values are not saving

Save the owning model first, then call `setCustomAttribute()` or `setCustomAttributes()`.

## JSON migrations fail on older database engines

Publish the config and switch:

```php
'database' => [
    'json_column_type' => 'text',
],
```

## Quick inspection snippets

### Count records by owner tuple

```php
use AIArmada\Products\Models\Product;

Product::query()
    ->withoutOwnerScope()
    ->selectRaw('owner_type, owner_id, count(*) as total')
    ->groupBy('owner_type', 'owner_id')
    ->get();
```

### Inspect automatic collection matches

```php
$collection->getMatchingProducts()->pluck('id');
```

### Inspect custom attributes

```php
$product->getCustomAttributesArray();
```

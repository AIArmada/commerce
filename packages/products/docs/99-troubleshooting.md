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

- `2999` means RM 29.99 because product prices are always integer minor units. If an older environment stored major units, backfill those values once by multiplying by 100 before using the package.
- use `getFormattedPrice()` and related helpers for display

## Re-parenting options, option values, or variants fails

`Option.product_id`, `OptionValue.option_id`, and `Variant.product_id` are immutable after creation, even when owner enforcement is disabled. The guard runs before any write, so the original parent keeps its children and variant pivots. Create a new record under the desired parent instead — there is deliberately no reparenting escape hatch.

Creation-time parent checks follow the same rule: a missing parent fails, and a parent that is not reachable in the current owner context fails unless `include_global` allows a global parent. A value whose option points at a missing product is rejected too, so persisted values always resolve through their option to an existing product.

Children must also share the parent's owner tuple. A child carrying a different owner than its product fails with `InvalidArgumentException` ("Cross-tenant write blocked"), and a tenant-owned child of a global product fails the same way because global parents only accept global children. Creating children of a global product requires explicit global context (`OwnerContext::withOwner(null, ...)`); outside it, creation fails with `InvalidArgumentException` when `auto_assign_on_create` is enabled, otherwise `AuthorizationException`.

## Variant SKU output is unexpected

`Variant::generateSku()` uses `products.features.variants.sku_pattern`. The default pattern is:

```php
'{parent_sku}-{option_codes}'
```

If the parent product has no SKU, the variant falls back to a `PROD-...` prefix derived from the product UUID.

Matrix generation appends a bounded deterministic suffix derived from the variant's combination key when the default pattern is in use, so option codes that are ambiguous across boundaries (for example `AB`/`AB-CD` against `-C`/`C`) cannot collapse two combinations onto one SKU. When the product already uses the exact generated SKU for a different record (manual edits, legacy data), the generator appends a further deterministic hash suffix to the new row.

The complete default-generated SKU is bounded to the 255-character `sku` column. When the parent SKU and option codes would overflow, the generator trims the readable prefix while preserving the combination identity suffix and reserving room for the longest collision suffix, so a maximum-length or multibyte parent SKU cannot produce a value that PostgreSQL rejects.

Any other `sku_pattern` is treated as a custom pattern and used verbatim. It must produce a unique SKU per combination: two distinct combinations that map to the same SKU, an output that exceeds the 255-character `sku` column, or a candidate already held by a different record on the same product or a sibling product, fail the whole generation before any write. The generator never invents a disambiguated variant of a custom SKU — fix the pattern or clear the conflicting record. The owner-level SKU uniqueness index stays in place as the concurrency backstop.

## Automatic collections are empty

Use `Collection::getMatchingProducts()` for automatic collections. Common causes of empty results are:

- conditions referencing the wrong field names
- owner scoping excluding records from another tenant
- product status or visibility filters excluding candidates

## Custom attribute values are not saving

Save the owning model first, then call `setCustomAttribute()` or `setCustomAttributes()`.

## Attribute values are rejected with a cross-tenant or invalid reference error

`AttributeValue` writes validate the definition and target before saving:

- `AuthorizationException` — the referenced definition or attributable product/variant is missing or outside the current owner scope. Create the definition for the same owner, or enable `products.features.owner.include_global` to permit global definitions and targets.
- `InvalidArgumentException` — the morph type is not `Product`/`Variant`, or the write tries to change `attribute_id`, the attributable tuple, or owner columns after creation. Reparenting is not supported; create a new value.

When owner enforcement is disabled, missing definitions and targets still fail with `InvalidArgumentException`. Reads of a global definition from a tenant context require `include_global` to be enabled when the model scope boots; the scope snapshots the policy at boot, so do not flip it at runtime.

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

## Schema and query caveats

- Owner/identity uniques (`slug`, `sku`, `code`, category `parent_id + slug`) are created in every environment via `ProductIdentityIndexes`. The `EnforcesOwnerUniqueIdentity` application check is a friendly pre-check only; the partial unique indexes are authoritative under concurrency and surface as `QueryException`.
- `attribute_values` locale uniques are split partials (`locale IS NOT NULL` vs `locale IS NULL`); always query with `->whereNull('locale')` or `->forLocale()` rather than relying on plain unique behavior.
- EAV `whereHas('attributeValues')` helpers (`whereCustomAttribute()`, `forAttribute()`) are for admin/catalog queries. Keep storefront hot paths on materialized columns or read models.
- `attribute_values.value` is raw `text`. Read typed data via the `typed_value` accessor (`Attribute::castValue()` / `serializeValue()` per `AttributeType`):

```php
use AIArmada\Products\Models\AttributeValue;

$value = AttributeValue::query()->findOrFail($id)->typed_value;
```

- `PricesRelationManager` only renders when `aiarmada/pricing` is installed (`class_exists(Price::class)` gate in `ProductResource::getRelations()` and `canViewForRecord()`). If the tab is missing, install/enable the pricing package first.

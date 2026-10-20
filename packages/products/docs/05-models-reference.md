---
title: Models Reference
---

# Models Reference

## Product

The main catalog model. `Product` is owner-aware, media-aware, slugged, and implements the package buyable, inventory, and pricing contracts.

Prices are integer minor units end to end. `getBuyablePrice()` and `getCalculatedPrice()` both return the canonical minor-unit integer consumed by downstream cart, pricing, inventory, and checkout integrations.

`currency` must be a supported ISO 4217 code (validated on save, stored uppercase); anything else throws `InvalidArgumentException` instead of failing later in formatting. `ProductCreated`/`ProductUpdated` fire exactly once per action call via `$dispatchesEvents` (which also maps `deleted` → `ProductDeleted`). Deleting a product removes its variants, options, option values, and variant pivots in a transaction with model events. Returning to draft via `UpdateProductStatus` clears `published_at` (and `archived_at` / `deactivated_at`).

> **warning**
> `products` does **not** use `spatie/laravel-model-states`. `Product::$status` is a plain backed
> enum cast via `casts()` → `'status' => ProductStatus::class`.

### Lifecycle status

`AIArmada\Products\Enums\ProductStatus` has exactly four cases and is set through
`AIArmada\Products\Actions\UpdateProductStatus::execute($product, ProductStatus $x)`, which keeps
the lifecycle timestamps in sync:

| Status | Value | `isVisible()` / `isPurchasable()` | Timestamps written |
|--------|-------|-----------------------------------|-------------------|
| `Draft` | `draft` | No | clears `published_at`, `archived_at`, `deactivated_at` (default attribute) |
| `Active` | `active` | Yes | sets `published_at` if null; clears `archived_at`, `deactivated_at` |
| `Disabled` | `disabled` | No | sets `deactivated_at`; clears `archived_at` |
| `Archived` | `archived` | No | sets `archived_at`; clears `deactivated_at` |

`Variant` has **no** `status` column and there is no `VariantStatus` enum. Its lifecycle is the
`is_enabled` boolean plus a `deactivated_at` toggle, kept in sync by a `saving` hook in
`Variant::booted()` — see `isEnabled()` and `scopeEnabled()`.

### Common relationships

- `variants()`
- `options()`
- `categories()`
- `collections()`
- `attributeValues()`
- `prices()` when the pricing package is installed

### Notable methods

- `getFormattedPrice()`
- `getFormattedComparePrice()`
- `getFormattedCost()`
- `getPriceAsMoney()`
- `isActive()`
- `isDraft()`
- `isVisible()`
- `isPurchasable()`
- `isBuyable()`
- `isOnSale()`
- `hasDiscount()`
- `getDiscountPercentage()`
- `getProfitMargin()`
- `activate()`
- `disable()`
- `archive()`
- `hasVariants()`
- `supportsVariants()`
- `isPhysical()` / `isDigital()` / `isSubscription()`

## Variant

Variants belong to a product and optional option values. The owner tuple is immutable after
creation (`HasOwner` blocks reassignment, demotion, and promotion), and an `updating` guard
rejects `product_id` changes even when owner enforcement is disabled. Owned variants can only
be updated from a matching owner context, and tenant contexts cannot update global variants.
Create a new variant when the parent product changes.

Generation identity comes from `combination_key` (`<product id>:<sha256 of the sorted
option-value IDs>`, built with `Variant::combinationKeyFor()`), which is unique per owner and
per global scope through partial indexes. `MatrixVariantGenerator` fills the key on insert;
direct creates may leave it `null`, and later generation adopts a keyless variant when its
option-value membership matches a combination exactly.

With the default SKU pattern, generated SKUs also carry a bounded deterministic suffix derived
from the combination key, so ambiguous option-code boundaries cannot collapse distinct
combinations, and the complete SKU is bounded to the 255-character `sku` column by trimming the
readable prefix while preserving the identity and collision suffixes. A custom `sku_pattern` is
used verbatim and fails generation atomically when two combinations collapse, the output exceeds
the column, or a candidate SKU is already held by a different record.

When the inventory package is installed but the lookup throws (for example the inventory tables
do not exist), `getStockQuantity()` silently falls back to the local `stock` attribute. It does
**not** log a warning.

### Common relationships

- `product()`
- `optionValues()`

### Notable methods

- `generateSku()`
- `combinationKeyFor()`
- `getFormattedPrice()`
- `getFormattedComparePrice()`
- `isEnabled()`
- `isPurchasable()`
- `isOnSale()`
- `getDiscountPercentage()`

## Category

Categories support parent/child hierarchies, owner-aware relations, media collections, and slug generation.

Parents are validated on save: the parent must exist, must not be the category itself or one of its descendants, and must belong to the same owner (a global parent is accepted only when `include_global` is enabled). Ancestor and tree traversal terminate on legacy cycles. `getProductCount()` and `getAllProducts()` count distinct products across the subtree in bulk queries.

### Common relationships

- `parent()`
- `children()`
- `products()`

## Collection

Collections can be manual or automatic. Automatic-collection conditions accept the named rules (`price_min`, `price_max`, `type`, `category`, `tag`, `is_featured`) plus direct product columns from a fixed allowlist (`cost`, `metadata`, and owner columns are excluded) with comparison operators only; anything else throws `InvalidArgumentException`.

### Common relationships

- `products()`

### Notable methods

- `isManual()`
- `isAutomatic()`
- `getMatchingProducts()`
- `matchingProductsQuery()` — chunk or paginate this for large rule results
- `rebuildProductList()` — chunked attach/detach, preserves pivot positions
- `isPublished()`
- `isScheduled()`

## Option and OptionValue

Options describe configurable dimensions such as size or color, and option values provide the actual selectable values. Swatches are validated on save (`swatch_color` must be hex, `swatch_image` a plain path/URL); legacy invalid values render no style. Deleting an option removes its values individually so variant pivots detach.

Parentage is fixed at creation: `Option.product_id` and `OptionValue.option_id` cannot change afterwards, even when owner enforcement is disabled. Re-parenting is rejected before any write, so the original parent keeps its children, the owner tuple, and variant pivots stay unchanged. Create a new option or value under the new parent instead.

On create the referenced parent must exist and be reachable in the current owner context (`OwnerWriteGuard`); existence is validated even when owner enforcement is disabled, and `include_global` additionally allows global parents. A value also resolves through its option to a product and fails when that lineage is broken, so rows whose parent graph is inconsistent are never persisted.

### Common relationships

- `Option::product()`
- `Option::values()`
- `OptionValue::option()`
- `OptionValue::variants()`

## Attribute models

The attribute system is split across:

- `Attribute`
- `AttributeGroup`
- `AttributeSet`
- `AttributeValue`

These models are owner-aware and use config-driven table resolution like the rest of the package.

### AttributeValue integrity

`AttributeValue` validates both ends on create: the attributable product/variant must resolve in the current owner context, and the attribute definition must be reachable for the owner that will hold the value (the attributable's owner, otherwise the current owner). `include_global` additionally permits global definitions and global targets; without it, a global definition is rejected instead of saving a value whose owner-scoped `attribute` relation resolves to `null`. Existence is validated even when owner enforcement is disabled.

`attribute_id` and the `attributable_type`/`attributable_id` tuple are immutable after creation, as is the owner tuple. Re-parenting or re-targeting a persisted value is rejected before any write; create a new value instead. Typed values and locale uniqueness stay editable through the `HasAttributes` helpers and `ApplyAttributeChanges`. Direct model writes, relation creates, and the canonical actions share the same guards.

## `IsCatalogEntity` concern

`AIArmada\Products\Concerns\IsCatalogEntity` is shared by catalog taxonomy models to provide:

- `scopeOrdered()` — orders by `position`
- `scopeVisible()` — `where('visibility', 'visible')`
- `resolveProductTable(string $key, string $default)` — `protected`; resolves
  `config('products.database.tables.{key}')` and falls back to
  `config('products.database.table_prefix', 'product_') . $default`

`ProductVisibility`, `CatalogStatus`, and `AttributeType` remain the canonical typed taxonomy enums. The former generic `Visibility` enum and the duplicate `IsAttributeEntity`/`IsOptionEntity` concerns were removed.

## `HasAttributes` trait

Models using `AIArmada\Products\Traits\HasAttributes` get these helpers:

- `getCustomAttribute()`
- `setCustomAttribute()`
- `setCustomAttributes()`
- `getCustomAttributesArray()`
- `hasCustomAttribute()`
- `removeCustomAttribute()`
- `clearCustomAttributes()`
- `getFilterableCustomAttributes()`
- `getVisibleCustomAttributes()`
- `getComparableCustomAttributes()`
- `whereCustomAttribute()`
- `whereCustomAttributes()`

Custom-attribute predicates use the EAV `attribute_values` relation and are intended for admin/catalog management queries. Keep storefront listing/search paths on materialized product fields or dedicated read models rather than filtering large catalogs through EAV joins.

## Identity enforcement

Identity is enforced in `EnforcesOwnerUniqueIdentity::bootEnforcesOwnerUniqueIdentity()` plus dev-only partial uniques in `Support\ProductIdentityIndexes`:

- `Product::uniqueIdentityColumns()` returns `['slug', 'sku']`; `Variant` returns `['sku']`; `Category` returns `['slug']` scoped further by `parent_id` via `modifyUniqueIdentityQuery()`.
- Owner-scoped rows match `(owner_type, owner_id, identity)`; global rows match `(identity)` with `owner_* IS NULL`. Category root/child rows use separate partials so `parent_id = null` stays a first-class identity value.
- Conflicts throw `InvalidArgumentException` from the `saving` pre-check on both create and update; under concurrency the partial unique indexes surface as `QueryException`. There is no `createOrFirst()` helper and no `parent_scope` column in `src/`; use `first()` + `create()` explicitly.

```php
use AIArmada\Products\Models\Product;

// `slug` is a NOT NULL string with no DB default and is NOT generated in
// Product::booted() — you must supply it (or slug it yourself from the name).
$existing = Product::query()->forOwner($team)->where('sku', 'TSHIRT-001')->first()
    ?? Product::query()->create([
        'name' => 'Tee',
        'slug' => 'tee',
        'sku' => 'TSHIRT-001',
    ]);
```

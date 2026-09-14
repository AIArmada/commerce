---
title: Models Reference
---

# Models Reference

## Product

The main catalog model. `Product` is owner-aware, media-aware, slugged, and implements the package buyable, inventory, and pricing contracts.

Prices are integer minor units end to end. `getBuyablePrice()` and `getCalculatedPrice()` both return the canonical minor-unit integer consumed by downstream cart, pricing, inventory, and checkout integrations.

`currency` must be a supported ISO 4217 code (validated on save, stored uppercase); anything else throws `InvalidArgumentException` instead of failing later in formatting. `ProductCreated`/`ProductUpdated` fire exactly once per action call via `$dispatchesEvents`. Deleting a product removes its variants, options, option values, and variant pivots in a transaction with model events. Returning to draft via `UpdateProductStatus` clears `published_at`.

### Common relationships

- `variants()`
- `options()`
- `categories()`
- `collections()`
- `attributeSet()`
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
- `isOnSale()`
- `getDiscountPercentage()`
- `activate()`
- `archive()`

## Variant

Variants belong to a product and optional option values. `product_id` and the owner tuple are immutable after creation; updates are owner-guarded like products.

When the inventory package is installed but a lookup fails, `getStockQuantity()` logs a warning and falls back to local stock instead of reporting zero.

### Common relationships

- `product()`
- `optionValues()`

### Notable methods

- `generateSku()`
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

## `IsCatalogEntity` concern

`AIArmada\Products\Concerns\IsCatalogEntity` is shared by catalog taxonomy models to provide:

- `scopeOrdered()` — orders by `position`
- `scopeVisible()` — filters by the catalog entity's `visible` value
- `resolveProductTable()` — resolves the table name from package config

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
- Conflicts throw `UniqueConstraintViolationException` on create and `InvalidArgumentException` on update. There is no `createOrFirst()` helper and no `parent_scope` column in `src/`; use `first()` + `create()` explicitly.

```php
use AIArmada\Products\Models\Product;

$existing = Product::query()->forOwner($team)->where('sku', 'TSHIRT-001')->first()
    ?? Product::query()->create(['name' => 'Tee', 'sku' => 'TSHIRT-001']);
```

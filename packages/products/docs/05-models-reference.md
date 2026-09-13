---
title: Models Reference
---

# Models Reference

## Product

The main catalog model. `Product` is owner-aware, media-aware, slugged, and implements the package buyable, inventory, and pricing contracts.

Prices are integer minor units end to end. `getBuyablePrice()` and `getCalculatedPrice()` both return the canonical minor-unit integer consumed by downstream cart, pricing, inventory, and checkout integrations.

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

Variants belong to a product and optional option values.

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

### Common relationships

- `parent()`
- `children()`
- `products()`

## Collection

Collections can be manual or automatic.

### Common relationships

- `products()`

### Notable methods

- `isManual()`
- `isAutomatic()`
- `getMatchingProducts()`
- `rebuildProductList()`
- `isPublished()`
- `isScheduled()`

## Option and OptionValue

Options describe configurable dimensions such as size or color, and option values provide the actual selectable values.

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

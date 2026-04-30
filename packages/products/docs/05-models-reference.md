---
title: Models Reference
---

# Models Reference

## Product

The main catalog model. `Product` is owner-aware, media-aware, slugged, and implements the package buyable, inventory, and pricing contracts.

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

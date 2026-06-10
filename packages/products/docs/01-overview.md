---
title: Overview
---

# Products Package

`aiarmada/products` provides the catalog foundation for Commerce applications: products, variants, categories, collections, and extensible custom attributes.

## Purpose

Use this package when you need the source-of-truth catalog domain: products, variants, category and collection taxonomy, attribute structures, and owner-aware product storage.

## What this package owns

- Product, variant, option, and option-value models
- Category, collection, attribute, attribute group, and attribute set models
- Catalog-domain enums, helpers, slugging, and money-aware product behavior
- Owner-aware catalog persistence through `commerce-support`
- Config-driven table names and catalog feature flags
- Action classes for product lifecycle (`CreateProduct`, `UpdateProduct`, `UpdateProductStatus`, `ApplyAttributeChanges`, `GenerateVariants`)
- A `VariantGeneratorInterface` contract and its `MatrixVariantGenerator` strategy
- `IsAttributeEntity` and `IsOptionEntity` concerns for attribute/option model reuse

## What this package does not own

- Filament catalog resources, bulk-edit pages, import/export pages, or widgets
- Pricing, promotions, tax, inventory, checkout, or order orchestration
- Application storefront UI or search/indexing infrastructure outside the package model layer

## Related packages

- `aiarmada/commerce-support` provides owner-scoping and shared money/helper primitives
- `aiarmada/filament-products` provides the Filament admin surface for this catalog domain
- `pricing`, `inventory`, `promotions`, and `orders` consume product data but do not replace this package as the catalog source of truth

## Main models or surfaces

- `Product`
- `Variant`
- `Category`
- `Collection`
- `Attribute`
- `AttributeGroup`
- `AttributeSet`
- Product, status, visibility, and attribute-type enums

## Main actions

- `CreateProduct` — creates a product and dispatches `ProductCreated`
- `UpdateProduct` — updates a product and dispatches `ProductUpdated`
- `UpdateProductStatus` — manages status transitions (Active, Draft, Disabled, Archived) with lifecycle timestamps
- `GenerateVariants` — generates variant combinations via a configurable `VariantGeneratorInterface`
- `ApplyAttributeChanges` — applies attribute values to products or variants

## Contracts

- `VariantGeneratorInterface` — implement to provide custom variant generation strategies

## Strategies

- `MatrixVariantGenerator` — the default variant generator; builds a cartesian-product matrix from product options

## Concerns

- `IsAttributeEntity` — provides `scopeOrdered()`, `scopeVisible()`, and table-name resolution for attribute models
- `IsOptionEntity` — provides `scopeOrdered()`, `scopeVisible()`, and table-name resolution for option models

## Highlights

- Owner-aware models powered by `commerce-support`
- Product, category, collection, option, variant, and attribute models
- Media support via Spatie MediaLibrary
- Slug generation for products, categories, and collections
- Money helpers that respect the package currency settings
- Automatic and manual collection workflows
- Config-driven table names with no hardcoded `protected $table`

## Core enums

### Product types

| Case | Value |
| --- | --- |
| `Simple` | `simple` |
| `Configurable` | `configurable` |
| `Bundle` | `bundle` |
| `Digital` | `digital` |
| `Subscription` | `subscription` |

### Product statuses

| Case | Value |
| --- | --- |
| `Draft` | `draft` |
| `Active` | `active` |
| `Disabled` | `disabled` |
| `Archived` | `archived` |

### Product visibility

| Case | Value |
| --- | --- |
| `Catalog` | `catalog` |
| `Search` | `search` |
| `CatalogSearch` | `catalog_search` |
| `Individual` | `individual` |
| `Hidden` | `hidden` |

### Attribute types

| Case | Value |
| --- | --- |
| `Text` | `text` |
| `Textarea` | `textarea` |
| `Number` | `number` |
| `Boolean` | `boolean` |
| `Select` | `select` |
| `Multiselect` | `multiselect` |
| `Date` | `date` |
| `Color` | `color` |
| `Media` | `media` |

## Owner semantics

When `products.features.owner.enabled` is on, tenant-owned reads and writes follow the hardened `commerce-support` rules:

- normal reads rely on the owner scope or `forOwner()`
- global-only reads use `globalOnly()`
- missing owner context fails fast for owner-scoped reads
- owned writes require the current owner context to match
- global record writes require explicit global context via `OwnerContext::withOwner(null, ...)`
- `include_global` defaults to `false`

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Models Reference](05-models-reference.md)
- [Troubleshooting](99-troubleshooting.md)

## Requirements

- PHP 8.4+
- Laravel 11+
- `aiarmada/commerce-support`
- Spatie MediaLibrary
- Spatie Sluggable

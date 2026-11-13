---
title: Overview
---

# Products Package

`aiarmada/products` provides the catalog foundation for Commerce applications: products, variants, categories, collections, and extensible custom attributes.

## Purpose

Use this package when you need the source-of-truth catalog domain: products, variants, category and collection taxonomy, attribute structures, and owner-aware product storage.

## What this package owns

- Product, variant, option, option-value, and composite-component models
- Category, collection, attribute, attribute group, and attribute set models
- Catalog-domain enums, helpers, slugging, and money-aware product behavior
- Owner-aware catalog persistence through `commerce-support`
- Config-driven table names and catalog feature flags
- Action classes for product lifecycle (`CreateProduct`, `UpdateProduct`, `UpdateProductStatus`, `ApplyAttributeChanges`, `GenerateVariants`)
- Composite package actions (`CreateCompositeProduct`, `ReplaceProductComponents`, `PublishCompositeProduct`) and domain services for package validation, pricing, availability, and canonical composition snapshots
- A `VariantGeneratorInterface` contract and its `MatrixVariantGenerator` strategy
- `IsCatalogEntity` concern for shared catalog taxonomy model behavior
- `HasComponents` concern for product models that participate in composite package composition

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
- `ProductComponent`
- `Category`
- `Collection`
- `Attribute`
- `AttributeGroup`
- `AttributeSet`
- Product, composite price-mode, status, visibility, and attribute-type enums

## Main actions

- `CreateProduct` — creates a product and dispatches `ProductCreated`
- `UpdateProduct` — updates a product and dispatches `ProductUpdated`
- `UpdateProductStatus` — manages status transitions (Active, Draft, Disabled, Archived) with lifecycle timestamps
- `GenerateVariants` — generates variant combinations via a configurable `VariantGeneratorInterface`
- `ApplyAttributeChanges` — applies attribute values to products or variants
- `CreateCompositeProduct` — creates a composite parent and initial components in one transaction
- `ReplaceProductComponents` — transactionally replaces the complete component list
- `PublishCompositeProduct` — validates the package and activates the parent

## Contracts

- `VariantGeneratorInterface` — implement to provide custom variant generation strategies

## Strategies

- `MatrixVariantGenerator` — the default variant generator; builds a cartesian-product matrix from product options

## Concerns

- `IsCatalogEntity` — provides `scopeOrdered()`, `scopeVisible()`, and table-name resolution for catalog taxonomy models
- `HasComponents` — adds package relationships, price, availability, and snapshot helpers to product models

## Composite packages

`ProductType::Composite` represents a merchant-configured fixed package of physical sellable SKUs. The parent SKU is the commercial identity and has no inventory of its own. Each `ProductComponent` references an exact physical product or, for configurable products, an exact variant, plus a positive per-package quantity.

- `CompositePriceMode::Fixed` uses the parent `price`.
- `CompositePriceMode::Sum` multiplies each component's canonical base price by its per-package quantity.
- Components must share the package owner, currency, and tax treatment.
- Nested composites, self references, duplicate components, non-physical components, untracked inventory, and missing exact variants are rejected in the domain.
- An active package must contain at least one valid component; referenced products and variants cannot be deleted.
- `CompositePackageService` builds the canonical snapshot consumed by cart, checkout, orders, shipping, and inventory. Snapshot entries expose `product_id`, `variant_id`, `inventoryable_type`, `inventoryable_id`, `quantity`, `sku`, `name`, `weight` in grams, `dimensions` in centimetres, `requires_shipping`, and `tracks_inventory`.

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
| `Composite` | `composite` |
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

- PHP 8.5+
- Laravel 13+
- `aiarmada/commerce-support`
- Spatie MediaLibrary
- Spatie Sluggable

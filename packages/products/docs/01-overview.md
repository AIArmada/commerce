---
title: Overview
---

# Products Package

`aiarmada/products` provides the catalog foundation for Commerce applications: products, variants, categories, collections, and extensible custom attributes.

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

## Requirements

- PHP 8.4+
- Laravel 11+
- `aiarmada/commerce-support`
- Spatie MediaLibrary
- Spatie Sluggable

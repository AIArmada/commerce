---
title: Overview
---

# Filament Products

`aiarmada/filament-products` adds Filament v5 resources, pages, and widgets for the `aiarmada/products` package.

## Purpose

Use this package when you need a Filament admin UI for catalog management: products, categories, collections, attributes, and the supporting bulk-edit/import surfaces.

## What this package owns

- The `FilamentProductsPlugin` panel plugin
- Catalog resources such as products, categories, collections, and attribute management
- Optional bulk-edit and import/export pages controlled by package config
- Product/catalog dashboard widgets for admin reporting
- Filament-side owner-safe query and submitted-ID validation for catalog administration

## What this package does not own

- The underlying product domain models, catalog policies, or owner semantics
- Storefront product presentation or customer-facing catalog experiences
- Pricing, promotions, inventory, or checkout rules beyond exposing catalog administration UI

## Related packages

- `aiarmada/products` is the source of truth for catalog models and domain behavior
- `aiarmada/commerce-support` provides owner context and shared multitenancy helpers
- `filament/spatie-laravel-media-library-plugin` and `filament/spatie-laravel-tags-plugin` support the admin UI integrations this package uses

## Main resources pages or widgets

- `FilamentProductsPlugin`
- `ProductResource`
- `CategoryResource`
- `CollectionResource` when enabled
- `AttributeResource`, `AttributeGroupResource`, and `AttributeSetResource`
- `BulkEditProducts` and `ImportExportProducts` pages when enabled
- Product stats, category distribution, product type distribution, and top-selling widgets

## What it includes

- `ProductResource`
- `CategoryResource`
- `CollectionResource` when the collections feature is enabled
- `AttributeResource` when the attributes feature is enabled
- `AttributeGroupResource`
- `AttributeSetResource`
- `BulkEditProducts` page when enabled
- `ImportExportProducts` page when enabled
- dashboard widgets for product stats, category distribution, product type distribution, and top-selling products

## Owner-aware by default

This package does not treat Filament option scoping as authorization. Resource queries, page actions, and submitted IDs are revalidated against the current owner context from `commerce-support`.

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Troubleshooting](99-troubleshooting.md)

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament v5
- `aiarmada/products`
- `aiarmada/commerce-support`

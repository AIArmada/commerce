---
title: Overview
---

# Filament Products

`aiarmada/filament-products` adds Filament v5 resources, pages, and widgets for the `aiarmada/products` package.

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

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament v5
- `aiarmada/products`
- `aiarmada/commerce-support`

---
title: Overview
---

# Products Package

A comprehensive Product Information Management (PIM) system for Laravel commerce applications. This package provides the foundation for managing products, variants, categories, collections, and a flexible EAV (Entity-Attribute-Value) attribute system.

## Features

- **Product Management**: Full lifecycle management with types (Simple, Configurable, Virtual, Downloadable, Bundle, Grouped)
- **Variant System**: Generate product variants from option combinations (size, color, etc.)
- **Category Hierarchy**: Nested categories with parent-child relationships
- **Collections**: Manual and rule-based product groupings
- **EAV Attributes**: Extensible product attributes without schema changes
- **Multi-tenancy**: Full owner scoping via `commerce-support`
- **Media Management**: Spatie MediaLibrary integration for images, videos, documents
- **SEO**: Automatic slug generation and meta fields

## Package Architecture

```
packages/products/
├── config/products.php          # Configuration
├── database/
│   ├── factories/               # Model factories
│   └── migrations/              # 14 migration files
├── resources/lang/              # Translations
└── src/
    ├── Contracts/               # Interfaces (Buyable, Inventoryable, Priceable)
    ├── Enums/                   # ProductType, ProductStatus, ProductVisibility, AttributeType
    ├── Events/                  # Domain events
    ├── Models/                  # 10 Eloquent models
    ├── Policies/                # Authorization policies
    ├── Services/                # VariantGeneratorService
    ├── Traits/                  # HasAttributes trait
    └── ProductsServiceProvider.php
```

## Core Concepts

### Product Types

| Type | Description |
|------|-------------|
| `Simple` | Single product without variants |
| `Configurable` | Product with variants (size, color combinations) |
| `Virtual` | Non-physical product (services, memberships) |
| `Downloadable` | Digital products with file delivery |
| `Bundle` | Collection of products sold together |
| `Grouped` | Products displayed together, purchased separately |

### Product Status

| Status | Description |
|--------|-------------|
| `Draft` | Not ready for display |
| `Active` | Available for purchase |
| `Archived` | Hidden from catalog, retained for history |

### Product Visibility

| Visibility | Description |
|------------|-------------|
| `Visible` | Shows in catalog and search |
| `Catalog` | Shows in catalog only |
| `Search` | Shows in search only |
| `Hidden` | Not visible anywhere |

## Requirements

- PHP 8.4+
- Laravel 11+
- `aiarmada/commerce-support` package
- Spatie MediaLibrary v11
- Spatie Sluggable v3
- Spatie Tags v4

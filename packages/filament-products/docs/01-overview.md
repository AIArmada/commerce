---
title: Overview
---

# Filament Products

A Filament v5 admin panel plugin for managing products, categories, collections, and attributes. This package provides a complete CRUD interface for the `aiarmada/products` package.

## Features

- **Product Management**: Full CRUD with rich form builder (pricing, inventory, SEO, media)
- **Variant Management**: Relation manager for configurable product variants
- **Category Management**: Hierarchical tree with drag-and-drop ordering
- **Collection Management**: Manual and automatic (rule-based) collections
- **Attribute System**: Full EAV management (Attributes, Groups, Sets)
- **Bulk Operations**: Import/Export via CSV, bulk editing
- **Dashboard Widgets**: Product stats, low stock alerts, category distribution
- **Multi-tenancy**: Full owner scoping integration

## Package Structure

```
packages/filament-products/
├── config/filament-products.php  # Plugin configuration
├── resources/
│   ├── lang/en/                  # Translations
│   └── views/                    # Blade views
└── src/
    ├── FilamentProductsPlugin.php
    ├── FilamentProductsServiceProvider.php
    ├── Pages/                    # Custom pages
    │   ├── BulkEditProducts.php
    │   └── ImportExportProducts.php
    ├── Resources/                # Filament resources
    │   ├── ProductResource/
    │   ├── CategoryResource/
    │   ├── CollectionResource/
    │   ├── AttributeResource/
    │   ├── AttributeGroupResource/
    │   └── AttributeSetResource/
    ├── Support/                  # Utilities
    │   └── OwnerScope.php
    └── Widgets/                  # Dashboard widgets
        ├── ProductStatsWidget.php
        ├── ProductTypeDistributionWidget.php
        ├── CategoryDistributionWidget.php
        └── RecentProductsWidget.php
```

## Resources Overview

| Resource | Model | Features |
|----------|-------|----------|
| ProductResource | Product | Full CRUD, variants, media, SEO, bulk actions |
| CategoryResource | Category | Tree view, parent-child, products relation |
| CollectionResource | Collection | Manual/automatic, condition builder |
| AttributeResource | Attribute | Types, validation, options |
| AttributeGroupResource | AttributeGroup | Group attributes together |
| AttributeSetResource | AttributeSet | Assign groups to sets |

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament v5
- `aiarmada/products` package
- `aiarmada/commerce-support` package

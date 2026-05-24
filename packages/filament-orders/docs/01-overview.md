---
title: Overview
---

# Filament Orders Package

## Purpose

The `aiarmada/filament-orders` package is the Filament admin adapter for `aiarmada/orders`.

## What this package owns

- Filament order resource, relation managers, and admin actions
- Order dashboard widgets, timelines, and recent-order reporting surfaces
- Invoice download UI and order-focused admin customization hooks

## What this package does not own

- Order state machine rules, payment/refund bookkeeping, or invoice generation logic; those stay in `aiarmada/orders`
- Checkout orchestration or shipping-carrier execution
- Tenant resolution itself; it consumes the owner context from the host app and `commerce-support`

## Related packages

- [`aiarmada/orders`](../../orders/docs/01-overview.md) — core order domain package
- [`aiarmada/checkout`](../../checkout/docs/01-overview.md) — upstream orchestration that creates and updates orders
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and shared infrastructure

## Main models services or surfaces

- **Resource** — `OrderResource` with CRUD pages and relation managers
- **Widgets** — order stats, order timeline, status distribution, and recent orders
- **Support** — cache management and order-focused customization surfaces

## Owner scoping and security notes

- The plugin should mirror the owner-scoping behavior defined by `aiarmada/orders`
- Resource filtering is not authorization; admin actions and downloads still rely on the core orders package to enforce owner-safe reads and writes

The Filament Orders package provides a complete admin interface for managing orders using Filament v5. It integrates seamlessly with the core Orders package.

## Features

- **Full Order Resource**: CRUD operations with rich table and form views
- **Dashboard Widgets**: Stats, charts, timelines, and recent orders
- **Invoice Downloads**: One-click PDF invoice generation
- **Order Timeline**: Visual history of all order events
- **Real-time Updates**: Automatic polling for live data
- **Multi-tenancy**: Full owner scoping support

## Screenshots

### Order List
- Searchable and filterable table
- Status badges with icons and colors
- Quick actions for common operations

### Order View
- Full order details with infolist
- Header actions for state transitions
- Relation managers for items, payments, refunds, and notes

## Package Structure

```
packages/filament-orders/
├── config/
│   └── filament-orders.php  # Configuration
└── src/
    ├── FilamentOrdersPlugin.php        # Filament plugin
    ├── FilamentOrdersServiceProvider.php
    ├── Resources/
    │   └── OrderResource/
    │       ├── OrderResource.php       # Main resource
    │       ├── Pages/                  # CRUD pages
    │       └── RelationManagers/       # Relation managers
    ├── Support/
    │   └── FilamentOrdersCache.php     # Cache management
    └── Widgets/
        ├── OrderStatsWidget.php              # Stats overview
        ├── OrderTimelineWidget.php           # Event timeline
        ├── OrderStatusDistributionWidget.php # Status chart
        └── RecentOrdersWidget.php            # Recent orders table
```

## Requirements

- PHP 8.4+
- Laravel 13+
- Filament 5.0+
- `aiarmada/orders` package

## Quick Start

### 1. Install the package

```bash
composer require aiarmada/filament-orders
```

### 2. Register the plugin

```php
// app/Providers/Filament/AdminPanelProvider.php
use AIArmada\FilamentOrders\FilamentOrdersPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            FilamentOrdersPlugin::make(),
        ]);
}
```

### 3. Publish configuration (optional)

```bash
php artisan vendor:publish --tag=filament-orders-config
```

That's it! Navigate to `/admin/orders` to access the order management interface.

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Customization](05-customization.md)
- [Troubleshooting](99-troubleshooting.md)
- [Core orders overview](../../orders/docs/01-overview.md)

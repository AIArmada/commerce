---
title: Overview
---

# Filament Orders Package

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
- Laravel 12+
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

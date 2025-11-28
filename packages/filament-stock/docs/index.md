# Filament Stock Admin

Filament v5 admin panel plugin for managing stock transactions and reservations using the AIArmada Stock package.

## Overview

This plugin provides:

- **Stock Transactions Resource** - Full CRUD for stock movements
- **Stock Reservations Resource** - Read-only view of active/expired reservations
- **Dashboard Widgets** - Stats overview, timeline, and low stock alerts
- **Custom Actions** - Quick add stock, extend/release reservations

## Quick Start

Register the plugin in your Filament Panel:

```php
use AIArmada\FilamentStock\FilamentStockPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentStockPlugin::make(),
        ]);
}
```

## Navigation

The plugin adds two resources under the "Stock" navigation group:

- **Stock Transactions** (`heroicon-o-cube`) - Manage stock in/out movements
- **Stock Reservations** (`heroicon-o-clock`) - View cart reservations with expiry status

## Resources

### Stock Transactions

Full CRUD resource for stock movements:

- **List View** - Filterable table with type, reason, date filters
- **Create View** - Form to add new stock transactions
- **View View** - Detailed infolist with stockable information
- **Edit View** - Modify existing transactions

Table features:
- Color-coded type badges (success for in, danger for out)
- Searchable by stockable ID
- Sortable by all columns
- Filters: type, reason, date range

### Stock Reservations

Read-only resource for viewing stock reservations:

- **List View** - Table with status badges (Active/Expired)
- **View View** - Detailed infolist with expiry information

Navigation badge shows count of active reservations.

## Widgets

### StockStatsWidget

Overview statistics widget showing:
- Total transactions
- Inbound (30 days)
- Outbound (30 days)
- Net change (30 days)
- Active reservations

### StockTransactionTimelineWidget

Visual timeline of stock movements with:
- Color-coded entries (green for in, red for out)
- Transaction details (quantity, reason, user)
- Summary statistics
- Supports filtering by stockable record

Use on a stockable model's view page:

```php
use AIArmada\FilamentStock\Widgets\StockTransactionTimelineWidget;

protected function getFooterWidgets(): array
{
    return [
        StockTransactionTimelineWidget::class,
    ];
}
```

### LowStockAlertsWidget

Table widget showing items with stock at or below the configured threshold:
- Current stock level
- Last movement date
- Status badges (Critical/Low)
- Quick actions to add stock or view history
- Auto-refreshes every 30 seconds

## Actions

### QuickAddStockAction

Header action for quickly adding stock:

```php
use AIArmada\FilamentStock\Actions\QuickAddStockAction;

protected function getHeaderActions(): array
{
    return [
        QuickAddStockAction::make(),
    ];
}
```

Features:
- Stockable type selector (from registry)
- Quantity input with min/max validation
- Reason preset selector
- Optional notes field

### ExtendReservationAction

Extend a reservation's expiry time:

```php
use AIArmada\FilamentStock\Actions\ExtendReservationAction;

// In ViewRecord page
protected function getHeaderActions(): array
{
    return [
        ExtendReservationAction::make(),
    ];
}
```

### ReleaseReservationAction

Release a reservation and free the stock:

```php
use AIArmada\FilamentStock\Actions\ReleaseReservationAction;

// In ViewRecord page
protected function getHeaderActions(): array
{
    return [
        ReleaseReservationAction::make(),
    ];
}
```

## Next Steps

- [Configuration](configuration.md) - Customize plugin behavior
- [Widgets](widgets.md) - Learn about available widgets
- [Actions](actions.md) - Explore custom actions

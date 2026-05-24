---
title: Overview
---

# Filament Inventory

## Purpose

The `aiarmada/filament-inventory` package is the Filament admin adapter for `aiarmada/inventory`. It exposes inventory operations through resources, widgets, and action workflows.

## What this package owns

- Filament resources for locations, stock levels, movements, allocations, and optional batch or serial management
- Inventory dashboard widgets, charts, and KPI surfaces
- Admin actions for receive, ship, transfer, adjust, cycle count, reorder approval, and allocation release workflows
- Filament-side owner-aware query helpers and dashboard aggregation surfaces

## What this package does not own

- Inventory persistence, stock calculation, costing, forecasting, or allocation rules; those stay in `aiarmada/inventory`
- Product catalog, checkout, or order domain behavior
- Tenant resolution itself; it consumes the owner context provided by the host app and `commerce-support`

## Related packages

- [`aiarmada/inventory`](../../inventory/docs/01-overview.md) — core inventory models, actions, and services
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner-context primitives used by admin queries
- [`aiarmada/filament-authz`](../../filament-authz/docs/01-overview.md) — optional admin authorization layer when installed

## Main models services or surfaces

- **Resources** — `InventoryLocationResource`, `InventoryLevelResource`, `InventoryMovementResource`, `InventoryAllocationResource`, plus optional `InventoryBatchResource` and `InventorySerialResource`
- **Widgets** — stats, low inventory alerts, expiring batches, reorder suggestions, backorders, valuation, KPIs, movement trends, and ABC analysis
- **Actions and services** — receive, ship, transfer, adjust, cycle count, reorder approval, allocation release, and `InventoryStatsAggregator`

## Owner scoping and security notes

- The package uses `InventoryOwnerScope` to keep resources, widgets, and action lookups aligned with the current owner context
- Relationship options and action forms are owner-scoped for usability, but actions also revalidate submitted location and record IDs server-side before mutating inventory
- Global or cross-owner operations should only happen through explicit core-package flows, not by bypassing Filament filtering

## Features

### Resources

- **Locations** — Manage warehouses, stores, and fulfillment centers with priority-based allocation
- **Stock Levels** — View and manage inventory per product per location with reorder alerts
- **Movements** — Complete audit trail of all inventory transactions (receipts, shipments, transfers, adjustments)
- **Allocations** — Monitor active cart allocations with expiration tracking
- **Batches** — Track lot/batch numbers with expiry date management (optional)
- **Serial Numbers** — Individual unit tracking with warranty and condition tracking (optional)

### Dashboard Widgets

| Widget | Purpose |
|--------|---------|
| **Stats Overview** | Active locations, total SKUs, on-hand, reserved, available |
| **KPI Widget** | Turnover ratio, days on hand, fill rate, accuracy |
| **Low Inventory Alerts** | Items below reorder point |
| **Expiring Batches** | Batches approaching expiry |
| **Reorder Suggestions** | AI-generated reorder recommendations |
| **Backorders** | Open backorder tracking |
| **Valuation** | Total inventory value by costing method |
| **Movement Trends** | Daily receipts/shipments/transfers chart |
| **ABC Analysis** | Pareto classification of inventory value |

### Operational Actions

- **Receive Stock** — Record incoming inventory with PO and supplier info
- **Ship Stock** — Record outgoing inventory with order and tracking info
- **Transfer Stock** — Move inventory between locations
- **Adjust Stock** — Make inventory adjustments with reason codes
- **Cycle Count** — Verify physical counts and auto-adjust variances
- **Release Allocation** — Manually release cart allocations

## Architecture

```
filament-inventory/
├── Actions/           # Reusable Filament actions
├── Resources/         # Filament resources with Pages/Schemas/Tables
├── Services/          # Stats aggregation and caching
├── Support/           # Multitenancy helpers
└── Widgets/           # Dashboard widgets
```

## Requirements

- PHP 8.4+
- Laravel 13+
- Filament 5.0+
- `aiarmada/inventory` (core inventory package)

## Quick Start

```php
use AIArmada\FilamentInventory\FilamentInventoryPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentInventoryPlugin::make(),
        ]);
}
```

## Multitenancy

The package fully supports owner-scoped multitenancy through the `InventoryOwnerScope` helper. When owner mode is enabled in the core inventory package, all resources automatically filter data to the current tenant context.

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Widgets](05-widgets.md)
- [Actions](06-actions.md)
- [Core inventory overview](../../inventory/docs/01-overview.md)

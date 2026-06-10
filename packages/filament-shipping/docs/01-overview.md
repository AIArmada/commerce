---
title: Overview
---

# Filament Shipping Overview

## Purpose

The `aiarmada/filament-shipping` package is the Filament admin adapter for `aiarmada/shipping`.

## What this package owns

- Filament resources for shipments, shipping zones, and return authorizations
- Shipping dashboard, fulfilment queue, manifest page, and shipping-focused widgets
- Filament action workflows for ship, cancel, print label, sync tracking, and return review operations

## What this package does not own

- Shipping manager logic, driver execution, rate shopping, or shipment persistence; those stay in `aiarmada/shipping`
- Carrier-specific integrations such as J&T-specific API handling
- Tenant resolution itself; it consumes the owner context from the host app and `commerce-support`

## Related packages

- [`aiarmada/shipping`](../../shipping/docs/01-overview.md) — core shipping abstraction and shipment domain package
- [`aiarmada/orders`](../../orders/docs/01-overview.md) — fulfilment queue and order integration
- [`aiarmada/jnt`](../../jnt/docs/01-overview.md) and [`aiarmada/filament-jnt`](../../filament-jnt/docs/01-overview.md) — carrier-specific execution and admin surfaces

## Main models services or surfaces

- **Resources** — shipment, shipping zone, and return authorization administration
- **Pages** — shipping dashboard, fulfilment queue, and manifest page
- **Widgets** — shipping dashboard, pending shipments, carrier performance, and pending actions
- **Actions** — ship, cancel shipment, print label, sync tracking, approve/reject returns, and bulk actions

## Owner scoping and security notes

- The plugin should mirror the owner-scoping behavior defined by `aiarmada/shipping`
- Resource filtering is not authorization; shipping actions still rely on the core package or carrier adapter to validate owner-safe reads and writes

The `aiarmada/filament-shipping` package provides a complete Filament v5 admin panel integration for the shipping package. It includes resources, pages, actions, and widgets for managing shipments, returns, and shipping zones.

## Features

### Resources
- **Shipment Resource**: Full CRUD for shipments with status management
- **Shipping Zone Resource**: Geographic zone configuration with rates
- **Return Authorization Resource**: RMA management with approval workflow

### Pages
- **Shipping Dashboard**: Overview stats and charts
- **Fulfillment Queue**: Orders ready for shipping (requires Orders package)
- **Manifest Page**: Daily shipping manifests for carrier pickup

### Actions
- **Ship Action**: Create shipment with carrier
- **Cancel Shipment Action**: Cancel pending shipments
- **Print Label Action**: Generate and download shipping labels
- **Sync Tracking Action**: Update tracking status from carrier
- **Approve/Reject Return Actions**: Process return requests
- **Bulk Actions**: Ship, cancel, print, and sync multiple shipments

### Widgets
- **Shipping Dashboard Widget**: Stats overview (pending, in transit, delivered, exceptions)
- **Pending Shipments Widget**: Table of pending shipments
- **Carrier Performance Widget**: Bar chart of carrier metrics
- **Pending Actions Widget**: Actionable items requiring attention

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament 5.0+
- `aiarmada/shipping` package

## Package Structure

```
packages/filament-shipping/
├── config/
│   └── filament-shipping.php    # Plugin configuration
├── resources/
│   └── views/                   # Blade templates
├── src/
│   ├── Actions/                 # Filament actions (ShipAction, CancelShipmentAction, etc.)
│   ├── Pages/                   # Custom Filament pages
│   ├── Resources/               # Filament resources
│   │   ├── ShipmentResource/
│   │   │   ├── Schemas/         # ShipmentForm, ShipmentInfolist
│   │   │   └── Tables/          # ShipmentsTable
│   │   ├── ShippingZoneResource/
│   │   │   ├── Schemas/         # ShippingZoneForm
│   │   │   └── Tables/          # ShippingZonesTable
│   │   └── ReturnAuthorizationResource/
│   │       ├── Schemas/         # ReturnAuthorizationForm
│   │       └── Tables/          # ReturnAuthorizationsTable
│   ├── Services/                # Bridge services (CartBridge)
│   ├── Support/                 # ShippingStatsAggregator
│   ├── Widgets/                 # Dashboard widgets
│   ├── FilamentShippingPlugin.php
│   └── FilamentShippingServiceProvider.php
└── docs/
```

## Screenshots

### Shipment List
View all shipments with status badges, carrier info, and quick actions.

### Shipping Dashboard
Real-time stats showing pending shipments, deliveries, and exceptions.

### Fulfillment Queue
Process orders ready for shipping with carrier selection and tracking input.

## Multi-Tenancy Support

All resources and widgets respect the owner scoping configuration from the shipping package. When enabled, users only see data belonging to their tenant.

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Troubleshooting](99-troubleshooting.md)
- [Core shipping overview](../../shipping/docs/01-overview.md)

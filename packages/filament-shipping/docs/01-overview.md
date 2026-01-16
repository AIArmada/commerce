---
title: Overview
---

# Filament Shipping Overview

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
│   ├── Actions/                 # Filament actions
│   ├── Pages/                   # Custom Filament pages
│   ├── Resources/               # Filament resources
│   │   ├── ShipmentResource/
│   │   ├── ShippingZoneResource/
│   │   └── ReturnAuthorizationResource/
│   ├── Services/                # Bridge services
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

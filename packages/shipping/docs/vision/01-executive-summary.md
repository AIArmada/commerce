# Shipping Package Vision - Executive Summary

> **Document Version:** 1.0.0  
> **Created:** December 7, 2025  
> **Last Updated:** December 7, 2025  
> **Package:** `aiarmada/shipping` + `aiarmada/filament-shipping`  
> **Depends On:** `aiarmada/cart`, `aiarmada/commerce-support`  
> **Integrates With:** `aiarmada/jnt`, `aiarmada/inventory`, `aiarmada/vouchers` (optional)  
> **Status:** Vision Document - Planning Phase

---

## Overview

This document series outlines the strategic vision for creating **AIArmada Shipping** - an **abstraction layer for unified shipping management** across multiple carriers. Unlike individual carrier packages (e.g., `aiarmada/jnt`), this package provides a **carrier-agnostic interface** for rate shopping, shipment management, label generation, tracking, and returns orchestration.

**Core Philosophy:** The `aiarmada/shipping` package acts as the **shipping brain** while carrier-specific packages (JNT, PosLaju, DHL, etc.) act as **adapters/drivers**.

## Document Structure

| Document | Contents | Status |
|----------|----------|--------|
| [01-executive-summary.md](01-executive-summary.md) | This document - overview and navigation | 📋 |
| [02-multi-carrier-architecture.md](02-multi-carrier-architecture.md) | Driver Pattern, Carrier Registry, Adapter Interface | 📋 |
| [03-rate-shopping-engine.md](03-rate-shopping-engine.md) | Multi-Carrier Quotes, Selection Rules, Cost Optimization | 📋 |
| [04-shipment-lifecycle.md](04-shipment-lifecycle.md) | Shipment Model, Status Workflow, Label Generation | 📋 |
| [05-tracking-aggregation.md](05-tracking-aggregation.md) | Unified Tracking, Status Normalization, Webhooks | 📋 |
| [06-returns-management.md](06-returns-management.md) | RMA System, Return Labels, Refund Orchestration | 📋 |
| [07-shipping-zones.md](07-shipping-zones.md) | Zone Configuration, Rate Tables, Geo-Rules | 📋 |
| [08-cart-integration.md](08-cart-integration.md) | ShippingCalculator, Checkout Flow, Address Validation | 📋 |
| [09-database-schema.md](09-database-schema.md) | Models, Migrations, Relationships | 📋 |
| [10-filament-enhancements.md](10-filament-enhancements.md) | Admin Dashboard, Shipment Management, Reports | 📋 |
| [11-implementation-roadmap.md](11-implementation-roadmap.md) | Phased Delivery, Timeline, Dependencies | 📋 |
| [PROGRESS.md](PROGRESS.md) | Implementation Progress Tracker | 📋 |

---

## Architectural Foundation

### Package Ecosystem Position

```
┌─────────────────────────────────────────────────────────────────────┐
│                    SHIPPING ECOSYSTEM HIERARCHY                      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  aiarmada/commerce-support (Foundation)                             │
│  └── OwnerResolverInterface (multi-tenant scoping)                  │
│            │                                                         │
│            ▼                                                         │
│  aiarmada/cart (Core Integration Point)                             │
│  ├── Cart model with shipping metadata                              │
│  ├── Conditions system (shipping conditions)                        │
│  └── Checkout flow integration                                      │
│            │                                                         │
│            ▼                                                         │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │  aiarmada/shipping (ABSTRACTION LAYER) ◄── THIS PACKAGE      │   │
│  │  ├── ShippingManager (factory for drivers)                   │   │
│  │  ├── ShippingDriverInterface (adapter contract)              │   │
│  │  ├── RateShoppingEngine (multi-carrier comparison)           │   │
│  │  ├── ShipmentService (lifecycle management)                  │   │
│  │  ├── TrackingAggregator (unified tracking)                   │   │
│  │  ├── ReturnsOrchestrator (RMA handling)                      │   │
│  │  └── ShippingZoneManager (geo-rules)                         │   │
│  └──────────────────────────────────────────────────────────────┘   │
│            │                                                         │
│            ▼                                                         │
│  ┌────────────────────────────────────────────────────┐             │
│  │          CARRIER DRIVER PACKAGES                    │             │
│  │                                                     │             │
│  │  aiarmada/jnt ───────────► JntShippingDriver       │             │
│  │  aiarmada/poslaju ───────► PosLajuShippingDriver   │             │
│  │  aiarmada/dhl ───────────► DhlShippingDriver       │             │
│  │  aiarmada/gdex ──────────► GdexShippingDriver      │             │
│  │  (future carriers...)                               │             │
│  └────────────────────────────────────────────────────┘             │
│            │                                                         │
│            ▼                                                         │
│  aiarmada/filament-shipping (Admin UI)                              │
│  ├── ShipmentResource, ShippingZoneResource                        │
│  ├── CarrierSettingsResource, RateTableResource                    │
│  ├── ShippingDashboardWidget, CarrierPerformanceWidget             │
│  └── Shipment bulk operations and label printing                    │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### Key Differentiator: Abstraction vs Implementation

| Package | Purpose | API Support Required? |
|---------|---------|----------------------|
| `aiarmada/jnt` | J&T Express Malaysia SDK | Yes (J&T API) |
| `aiarmada/poslaju` | Pos Laju Malaysia SDK | Yes (PosLaju API) |
| `aiarmada/shipping` | **Unified shipping abstraction** | No (uses drivers) |

The shipping package **delegates** to carrier-specific packages for actual API calls, but provides:
- Unified interface for rate comparison
- Consistent shipment model across carriers
- Normalized tracking statuses
- Carrier selection rules engine
- Returns/RMA workflow (even if carrier doesn't support it)

---

## Vision Pillars

### 1. Multi-Carrier Driver Architecture
Create a **pluggable driver system** where carriers register as adapters:

```php
// Config-based driver registration
'shipping' => [
    'default' => 'jnt',
    'drivers' => [
        'jnt' => JntShippingDriver::class,
        'poslaju' => PosLajuShippingDriver::class,
        'manual' => ManualShippingDriver::class,
    ],
],

// Usage
$shipping = app(ShippingManager::class);
$shipping->driver('jnt')->createShipment($data);
$shipping->driver('poslaju')->getQuote($cart);
```

### 2. Rate Shopping Engine
Compare rates across carriers for optimal shipping:

- ⬜ Multi-carrier quote aggregation
- ⬜ Selection rules (cheapest, fastest, preferred)
- ⬜ Service-level filtering (express, economy, same-day)
- ⬜ Cart-based rate calculation
- ⬜ Promotional rate overrides
- ⬜ Free shipping threshold rules
- ⬜ Zone-based rate tables

### 3. Unified Shipment Lifecycle
Standardized shipment model regardless of carrier:

- ⬜ Shipment model with polymorphic carrier reference
- ⬜ Status workflow (draft → pending → shipped → in_transit → delivered)
- ⬜ Label generation abstraction
- ⬜ Multi-package shipments
- ⬜ Shipment merging and splitting
- ⬜ Manifest generation

### 4. Tracking Aggregation
Unified tracking across all carriers:

- ⬜ Normalized TrackingStatus enum
- ⬜ Carrier-agnostic tracking events
- ⬜ Webhook receiver hub
- ⬜ Push notifications on status change
- ⬜ Customer tracking page

### 5. Returns Management
Complete RMA workflow:

- ⬜ Return authorization request
- ⬜ Return label generation
- ⬜ Return shipment tracking
- ⬜ Refund orchestration integration
- ⬜ Reason code analytics

### 6. Shipping Zones & Rules
Geographic configuration:

- ⬜ Zone model (country/state/postcode ranges)
- ⬜ Rate tables per zone
- ⬜ Carrier availability per zone
- ⬜ Delivery estimates per zone
- ⬜ Restricted zones (no shipping)

### 7. Cart Integration
Seamless checkout experience:

- ⬜ ShippingConditionProvider for cart
- ⬜ Address validation abstraction
- ⬜ Shipping method selection
- ⬜ Real-time rate updates
- ⬜ Shipping tax calculation

---

## Integration Points

### With Existing Packages

| Package | Integration |
|---------|-------------|
| `aiarmada/cart` | ShippingCondition, checkout flow, cart metadata |
| `aiarmada/jnt` | JntShippingDriver adapter, uses JntExpressService |
| `aiarmada/inventory` | Stock location for origin address, warehouse selection |
| `aiarmada/vouchers` | Free shipping vouchers, shipping discounts |
| `aiarmada/affiliates` | Shipping cost attribution for commission calculation |
| `aiarmada/chip` | Shipping cost payment processing |

### Driver Registration Pattern

Carrier packages will register themselves as shipping drivers:

```php
// In JntServiceProvider (aiarmada/jnt)
public function boot(): void
{
    // Only register if aiarmada/shipping is installed
    if (class_exists(ShippingManager::class)) {
        app(ShippingManager::class)->extend('jnt', function ($app) {
            return new JntShippingDriver(
                $app->make(JntExpressService::class)
            );
        });
    }
}
```

---

## Strategic Impact Matrix

| Vision Area | Complexity | Business Impact | Priority | Depends On |
|-------------|------------|-----------------|----------|------------|
| Driver Architecture | High | Critical | **P0** | - |
| Rate Shopping | Medium | Very High | **P0** | Drivers |
| Shipment Lifecycle | High | Very High | **P1** | Drivers |
| Cart Integration | Medium | High | **P1** | Rate Shopping |
| Tracking Aggregation | Medium | High | **P2** | Shipment Lifecycle |
| Shipping Zones | Medium | High | **P2** | Rate Shopping |
| Returns Management | High | Medium | **P3** | Shipment Lifecycle |

---

## Key Models (Proposed)

### Core Package (`aiarmada/shipping`)

**Models (Proposed):**
- `Shipment` - Central shipment record with carrier reference
- `ShipmentItem` - Items within a shipment (polymorphic to CartItem)
- `ShipmentEvent` - Lifecycle/tracking events
- `ShipmentLabel` - Generated labels storage
- `ShippingZone` - Geographic zones
- `ShippingRate` - Zone-carrier rate tables
- `ShippingMethod` - Available shipping methods
- `ReturnAuthorization` - RMA records
- `ReturnShipment` - Return shipment tracking

**Enums (Proposed):**
- `ShipmentStatus` (Draft, Pending, Shipped, InTransit, OutForDelivery, Delivered, Failed, Returned)
- `ShippingMethodType` (Standard, Express, SameDay, Economy, Pickup)
- `ReturnReason` (Damaged, WrongItem, NotAsDescribed, ChangedMind, etc.)

**Contracts (Proposed):**
- `ShippingDriverInterface` - All carrier drivers implement this
- `RateProviderInterface` - For rate quote sources
- `TrackingProviderInterface` - For tracking data sources
- `LabelGeneratorInterface` - For label generation

**Services (Proposed):**
- `ShippingManager` - Factory, driver resolution
- `RateShoppingEngine` - Multi-carrier comparison
- `ShipmentService` - CRUD, lifecycle
- `TrackingAggregator` - Unified tracking
- `ShippingZoneResolver` - Zone matching
- `ReturnsOrchestrator` - RMA workflow

---

## Roadmap Overview

```
Phase 0: Foundation (2 weeks)
├── Package scaffolding
├── Driver interface contracts
└── Basic ShippingManager

Phase 1: Rate Shopping (3 weeks)
├── Multi-carrier quote aggregation
├── Selection rules engine
└── Cart integration

Phase 2: Shipment Management (3 weeks)
├── Shipment model & lifecycle
├── Label generation abstraction
└── Order-to-shipment flow

Phase 3: Tracking (2 weeks)
├── Unified tracking model
├── Webhook aggregation
└── Customer tracking page

Phase 4: Zones & Rules (2 weeks)
├── Zone configuration
├── Rate tables
└── Carrier availability rules

Phase 5: Returns (2-3 weeks)
├── RMA workflow
├── Return labels
└── Refund integration

Phase 6: Filament Admin (2-3 weeks)
├── All resources
├── Dashboard widgets
└── Bulk operations
```

**Total Estimated: 14-18 weeks**

---

## Navigation

**Next:** [02-multi-carrier-architecture.md](02-multi-carrier-architecture.md) - Driver Pattern & Carrier Registry

---

*This vision represents the foundation for a unified shipping management layer that transforms the AIArmada Commerce ecosystem into an enterprise-grade multi-carrier shipping platform.*

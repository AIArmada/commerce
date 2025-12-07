# Shipping Vision: Implementation Roadmap

> **Document:** 11 of 11  
> **Package:** `aiarmada/shipping` + `aiarmada/filament-shipping`  
> **Status:** Vision Document  
> **Last Updated:** December 7, 2025

---

## Overview

This roadmap outlines the phased implementation approach for delivering the shipping package ecosystem, with clear dependencies and milestones.

---

## Phase 0: Foundation (2 weeks)

**Goal:** Package scaffolding and core contracts

### Tasks

- [ ] Create `aiarmada/shipping` package structure
  - [ ] `composer.json` with dependencies
  - [ ] `ShippingServiceProvider`
  - [ ] Base configuration file
- [ ] Define core contracts
  - [ ] `ShippingDriverInterface`
  - [ ] `RateProviderInterface`
  - [ ] `StatusMapperInterface`
- [ ] Implement `ShippingManager`
  - [ ] Driver resolution (Manager pattern)
  - [ ] Built-in `NullDriver` for testing
  - [ ] Built-in `ManualDriver`
- [ ] Set up package testing infrastructure

### Deliverables

- ✔ Package skeleton
- ✔ ShippingManager with driver resolution
- ✔ Core interfaces defined

---

## Phase 1: Rate Shopping (3 weeks)

**Goal:** Multi-carrier rate comparison and selection

### Tasks

- [ ] Create `RateShoppingEngine` service
- [ ] Implement selection strategies
  - [ ] `CheapestRateStrategy`
  - [ ] `FastestRateStrategy`
  - [ ] `PreferredCarrierStrategy`
- [ ] Rate caching layer
- [ ] `FreeShippingEvaluator`
- [ ] `FlatRateShippingDriver`
- [ ] `TableRateShippingDriver`
- [ ] Tests for all rate logic

### Deliverables

- ✔ Rate comparison across carriers
- ✔ Free shipping threshold logic
- ✔ Configurable rate tables

### Dependencies

- Phase 0 complete

---

## Phase 2: Shipment Management (3 weeks)

**Goal:** Shipment model and lifecycle management

### Tasks

- [ ] Database migrations
  - [ ] `shipments` table
  - [ ] `shipment_items` table
  - [ ] `shipment_events` table
  - [ ] `shipment_labels` table
- [ ] Models with relationships
  - [ ] `Shipment`
  - [ ] `ShipmentItem`
  - [ ] `ShipmentEvent`
  - [ ] `ShipmentLabel`
- [ ] `ShipmentStatus` enum with transitions
- [ ] `ShipmentService`
  - [ ] Create, ship, cancel operations
  - [ ] Status updates with events
  - [ ] Label generation abstraction
- [ ] Order-to-shipment flow
- [ ] Bulk operations service

### Deliverables

- ✔ Complete shipment lifecycle
- ✔ Label generation working
- ✔ Bulk operations

### Dependencies

- Phase 1 complete

---

## Phase 3: Cart Integration (2 weeks)

**Goal:** Seamless checkout experience

### Tasks

- [ ] `ShippingConditionProvider` for cart
- [ ] `InteractsWithShippingAddress` trait
- [ ] Shipping method selection flow
- [ ] Address validation abstraction
- [ ] Weight calculation helpers
- [ ] Free shipping promotions display
- [ ] Events for shipping selection

### Deliverables

- ✔ Shipping works in checkout
- ✔ Rate display component
- ✔ Address validation

### Dependencies

- Phase 1 complete (rates)
- `aiarmada/cart` package

---

## Phase 4: Tracking Aggregation (2 weeks)

**Goal:** Unified tracking across carriers

### Tasks

- [ ] `TrackingStatus` enum (normalized)
- [ ] `TrackingAggregator` service
- [ ] Webhook receiver hub
  - [ ] Universal endpoint
  - [ ] Carrier-specific handlers
- [ ] Tracking sync job
- [ ] Public tracking page
- [ ] Tracking sync command

### Deliverables

- ✔ Unified tracking view
- ✔ Webhook handling
- ✔ Customer tracking page

### Dependencies

- Phase 2 complete (shipments)

---

## Phase 5: Shipping Zones (2 weeks)

**Goal:** Geographic rate configuration

### Tasks

- [ ] Database migrations
  - [ ] `shipping_zones` table
  - [ ] `shipping_rates` table
  - [ ] `carrier_zone_availability` table
- [ ] Models
  - [ ] `ShippingZone`
  - [ ] `ShippingRate`
- [ ] `ShippingZoneResolver`
- [ ] Zone matching logic (country, state, postcode, radius)
- [ ] Rate calculation by zone
- [ ] Carrier restrictions per zone

### Deliverables

- ✔ Zone-based rates
- ✔ Carrier availability rules
- ✔ Postcode range support

### Dependencies

- Phase 1 complete (rates)

---

## Phase 6: Returns Management (2-3 weeks)

**Goal:** Complete RMA workflow

### Tasks

- [ ] Database migrations
  - [ ] `return_authorizations` table
  - [ ] `return_authorization_items` table
- [ ] Models
  - [ ] `ReturnAuthorization`
  - [ ] `ReturnAuthorizationItem`
- [ ] `ReturnReason` enum
- [ ] `ReturnService`
  - [ ] Request, approve, reject
  - [ ] Return label generation
  - [ ] Mark received
- [ ] Inventory restock integration
- [ ] Refund orchestration hooks

### Deliverables

- ✔ Complete RMA workflow
- ✔ Return label generation
- ✔ Inventory integration

### Dependencies

- Phase 2 complete (shipments)
- Phase 4 complete (tracking)

---

## Phase 7: JNT Driver Integration (1 week)

**Goal:** Connect `aiarmada/jnt` as shipping driver

### Tasks

- [ ] Create `JntShippingDriver` in jnt package
- [ ] Implement all interface methods
- [ ] `JntStatusMapper` for tracking normalization
- [ ] Self-registration in service provider
- [ ] Integration tests

### Deliverables

- ✔ JNT works through unified interface
- ✔ Status mapping complete

### Dependencies

- Phase 2 complete (shipments)
- Phase 4 complete (tracking)

---

## Phase 8: Filament Admin (2-3 weeks)

**Goal:** Complete admin interface

### Tasks

- [ ] Create `aiarmada/filament-shipping` package
- [ ] Resources
  - [ ] `ShipmentResource` with full features
  - [ ] `ShippingZoneResource`
  - [ ] `ShippingRateResource`
  - [ ] `ReturnAuthorizationResource`
- [ ] Widgets
  - [ ] `ShippingDashboardWidget`
  - [ ] `CarrierPerformanceWidget`
  - [ ] `PendingActionsWidget`
- [ ] Bulk actions
  - [ ] Bulk ship
  - [ ] Bulk print labels
  - [ ] Bulk cancel
- [ ] Pages
  - [ ] Shipping dashboard
  - [ ] Manifest generation
- [ ] Package bridges (cart, inventory)

### Deliverables

- ✔ Full admin interface
- ✔ Dashboard analytics
- ✔ Bulk operations

### Dependencies

- All core phases complete

---

## Timeline Summary

| Phase | Duration | Start After |
|-------|----------|-------------|
| Phase 0: Foundation | 2 weeks | - |
| Phase 1: Rate Shopping | 3 weeks | Phase 0 |
| Phase 2: Shipment Management | 3 weeks | Phase 0 |
| Phase 3: Cart Integration | 2 weeks | Phase 1 |
| Phase 4: Tracking | 2 weeks | Phase 2 |
| Phase 5: Zones | 2 weeks | Phase 1 |
| Phase 6: Returns | 2-3 weeks | Phase 2, 4 |
| Phase 7: JNT Driver | 1 week | Phase 2, 4 |
| Phase 8: Filament | 2-3 weeks | All phases |

**Total Estimated: 14-18 weeks**

---

## Parallel Development Opportunities

Phases that can be worked on simultaneously:

```
Phase 0 ──────────────────────────────────►
             │
             ├── Phase 1 (Rates) ─────────►
             │        │
             │        ├── Phase 3 (Cart) ─►
             │        │
             │        └── Phase 5 (Zones) ►
             │
             └── Phase 2 (Shipments) ─────►
                          │
                          ├── Phase 4 (Track) ►
                          │        │
                          │        └── Phase 6 (Returns) ►
                          │
                          └── Phase 7 (JNT) ────────────►
                                                       │
                                          Phase 8 (Filament) ►
```

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Test Coverage | > 80% |
| Driver Interface Compliance | 100% |
| Carrier Integration Time | < 1 week per carrier |
| Checkout Rate Selection | < 500ms |
| Tracking Sync Performance | > 100 shipments/minute |

---

## Navigation

**Previous:** [10-filament-enhancements.md](10-filament-enhancements.md)  
**Back to Start:** [01-executive-summary.md](01-executive-summary.md)

---

*This roadmap ensures a methodical, dependency-aware approach to building a world-class shipping management layer for the AIArmada Commerce ecosystem.*

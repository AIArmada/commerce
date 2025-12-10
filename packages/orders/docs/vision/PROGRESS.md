# Orders Vision Progress

> **Package:** `aiarmada/orders` + `aiarmada/filament-orders`  
> **Last Updated:** December 2025  
> **Status:** Vision Complete, Implementation Pending

---

## Package Hierarchy

```
┌─────────────────────────────────────────────────────────────────┐
│                     ORDERS PACKAGE POSITION                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │                   aiarmada/cart                          │   │
│   │                  (Checkout Source)                       │   │
│   └─────────────────────────────────────────────────────────┘   │
│                              │                                   │
│                              ▼                                   │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │                  aiarmada/orders ◄── THIS PACKAGE        │   │
│   │              (Transaction Lifecycle)                     │   │
│   └─────────────────────────────────────────────────────────┘   │
│                              │                                   │
│       ┌──────────────────────┼──────────────────────┐           │
│       ▼                      ▼                      ▼           │
│   ┌────────────┐      ┌────────────┐      ┌────────────┐        │
│   │  cashier   │      │ inventory  │      │  shipping  │        │
│   │ (Payment)  │      │ (Deduct)   │      │ (Fulfill)  │        │
│   └────────────┘      └────────────┘      └────────────┘        │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Implementation Status

| Phase | Status | Progress |
|-------|--------|----------|
| Phase 1: Core Models | 🔴 Not Started | 0% |
| Phase 2: State Machine | 🔴 Not Started | 0% |
| Phase 3: Payment Integration | 🔴 Not Started | 0% |
| Phase 4: Fulfillment Integration | 🔴 Not Started | 0% |
| Phase 5: Filament Admin | 🔴 Not Started | 0% |

---

## Phase 1: Core Models

### Order Model
- [ ] `Order` model with unique order number generation
- [ ] `OrderStatus` enum with full state machine
- [ ] `OrderItem` model with price/tax snapshots
- [ ] `OrderAddress` model (billing + shipping)
- [ ] `OrderNote` model (internal + customer visible)
- [ ] `OrderHistory` model (timeline events)

### Base Infrastructure
- [ ] `OrdersServiceProvider`
- [ ] Configuration file (`config/orders.php`)
- [ ] Database migrations
- [ ] Factories and seeders

---

## Phase 2: State Machine (Spatie Model States)

**Implementation:** [`spatie/laravel-model-states`](https://spatie.be/docs/laravel-model-states/v2/introduction)

### State Classes
- [ ] `OrderStatus` abstract base state class
- [ ] `PendingPayment` state - Awaiting payment confirmation
- [ ] `Processing` state - Payment received, preparing order
- [ ] `OnHold` state - Manual review required
- [ ] `Fraud` state - Fraud check failed
- [ ] `Shipped` state - Handed to carrier
- [ ] `Delivered` state - Confirmed delivery
- [ ] `Completed` state - Order finalized (Final)
- [ ] `Canceled` state - Customer/admin canceled (Final)
- [ ] `Refunded` state - Full refund processed
- [ ] `Returned` state - Items returned
- [ ] `Failed` state - Payment failed (Final)

### Transition Classes
- [ ] `PaymentConfirmedTransition` - PendingPayment → Processing
- [ ] `ShipmentCreatedTransition` - Processing → Shipped
- [ ] `DeliveryConfirmedTransition` - Shipped → Delivered
- [ ] `OrderCanceledTransition` - * → Canceled
- [ ] `ReturnInitiatedTransition` - Delivered → Returned
- [ ] `RefundProcessedTransition` - Returned → Refunded

### State Features
- [ ] `color()` - Filament badge color per state
- [ ] `icon()` - Heroicon per state
- [ ] `label()` - Translatable label per state
- [ ] `canCancel()` - Whether customer can cancel
- [ ] `canEdit()` - Whether order can be edited
- [ ] Query scopes (`whereState()`, `whereNotState()`)

---

## Phase 3: Payment Integration

### Payment Records
- [ ] `OrderPayment` model (gateway, transaction_id, amount)
- [ ] Multi-payment support (partial payments)
- [ ] Payment status tracking

### Refund Processing
- [ ] `OrderRefund` model
- [ ] Partial refund support
- [ ] Refund reason tracking
- [ ] Gateway refund API calls (via Cashier)

### Invoice Generation
- [ ] PDF invoice template
- [ ] Invoice numbering system
- [ ] Invoice storage and retrieval

---

## Phase 4: Fulfillment Integration

### Shipping Integration
- [ ] Cart → Order → Shipment flow
- [ ] Multi-shipment support (split orders)
- [ ] Tracking number integration

### Inventory Integration
- [ ] Stock deduction on order confirmation
- [ ] Stock reservation release on cancellation
- [ ] Backorder handling

---

## Phase 5: Filament Admin

### Resources
- [ ] `OrderResource` with comprehensive views
- [ ] Order timeline component
- [ ] Payment/refund management
- [ ] Shipment tracking

### Pages
- [ ] Order dashboard with analytics
- [ ] Order fulfillment queue
- [ ] Returns/refunds management

### Widgets
- [ ] Order stats (today, pending, revenue)
- [ ] Recent orders
- [ ] Order status distribution

---

## Vision Documents

| Document | Status |
|----------|--------|
| [01-executive-summary.md](01-executive-summary.md) | ✅ Complete |
| [02-state-machine.md](02-state-machine.md) | ✅ Complete |
| [03-order-structure.md](03-order-structure.md) | ✅ Complete |
| [04-payment-integration.md](04-payment-integration.md) | ✅ Complete |
| [05-fulfillment-flow.md](05-fulfillment-flow.md) | ✅ Complete |
| [06-integration.md](06-integration.md) | ✅ Complete |
| [07-database-schema.md](07-database-schema.md) | ✅ Complete |
| [08-implementation-roadmap.md](08-implementation-roadmap.md) | ✅ Complete |

---

## Dependencies

### Required
| Package | Purpose |
|---------|---------|
| `aiarmada/commerce-support` | Shared interfaces |
| `spatie/laravel-model-states` | State machine implementation |
| `spatie/laravel-pdf` | Invoice generation |
| `akaunting/laravel-money` | Amount handling |

### Optional (Auto-Integration)
| Package | Integration |
|---------|-------------|
| `aiarmada/cart` | Cart-to-Order conversion |
| `aiarmada/cashier` | Payment recording |
| `aiarmada/inventory` | Stock deduction |
| `aiarmada/shipping` | Fulfillment creation |
| `aiarmada/customers` | Customer association |
| `aiarmada/affiliates` | Commission attribution |
| `aiarmada/vouchers` | Discount snapshots |
| `aiarmada/tax` | Tax snapshots |

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Test Coverage | 90%+ |
| PHPStan Level | 6 |
| State Machine Coverage | 100% |
| Audit Trail | Complete |
| PDF Invoice | Spatie PDF |

---

## Legend

| Symbol | Meaning |
|--------|---------|
| 🔴 | Not Started |
| 🟡 | In Progress |
| 🟢 | Completed |
| ⏳ | Pending |

---

## Notes

### December 2025
- Initial vision documentation created
- Package positioned as transaction lifecycle manager
- **State machine architecture defined using `spatie/laravel-model-states`**
- Created comprehensive state machine vision document (02-state-machine.md)
- 5-phase implementation roadmap established
- 11 states and 6 transition classes defined

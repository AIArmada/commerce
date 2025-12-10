# Executive Summary

> **Document:** 01 of 08  
> **Package:** `aiarmada/orders` + `aiarmada/filament-orders`  
> **Status:** Vision  
> **Last Updated:** December 2025

---

## Vision Statement

Establish the **Orders Package** as the definitive transaction lifecycle manager of the AIArmada Commerce ecosystem—orchestrating the journey from checkout completion through fulfillment, maintaining complete audit trails, and serving as the authoritative record of all commerce transactions.

---

## Strategic Position in Commerce Ecosystem

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     AIARMADA COMMERCE ECOSYSTEM                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│                         ┌───────────────────┐                            │
│                         │     PRODUCTS      │                            │
│                         │   (The Catalog)   │                            │
│                         └─────────┬─────────┘                            │
│                                   │                                      │
│                                   ▼                                      │
│                         ┌───────────────────┐                            │
│                         │       CART        │                            │
│                         │   (Shopping)      │                            │
│                         └─────────┬─────────┘                            │
│                                   │                                      │
│                                   ▼                                      │
│                         ┌───────────────────┐                            │
│                         │      ORDERS       │ ◄── THIS PACKAGE           │
│                         │  (Transactions)   │                            │
│                         └─────────┬─────────┘                            │
│                                   │                                      │
│           ┌───────────────────────┼───────────────────────┐              │
│           ▼                       ▼                       ▼              │
│   ┌───────────────┐       ┌───────────────┐       ┌───────────────┐      │
│   │    CASHIER    │       │   INVENTORY   │       │   SHIPPING    │      │
│   │  (Payments)   │       │  (Deduction)  │       │ (Fulfillment) │      │
│   └───────────────┘       └───────────────┘       └───────────────┘      │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Core Design Principles

### 1. **Immutability First**
Order records are append-only. Changes create new records (adjustments, refunds) rather than modifying the original order. This ensures complete audit trails and historical accuracy.

### 2. **State Machine Driven**
Orders progress through well-defined states with explicit transitions, guards, and hooks. No order can skip states or enter invalid conditions.

### 3. **Payment Agnostic**
Orders are not coupled to any specific payment gateway. Payment information is stored as normalized references, allowing orders to exist without payment (COD, invoice, free orders).

### 4. **Fulfillment Decoupled**
Order fulfillment is delegated to the Shipping package. Orders track fulfillment status but don't contain shipping logic.

### 5. **Multi-Tenant Aware**
Orders are scoped by owner/tenant, enabling multi-store architectures.

---

## Package Responsibilities

| Responsibility | Description |
|----------------|-------------|
| **Order Creation** | Convert Cart to Order at checkout completion |
| **Order Items** | Line items with price snapshots, quantities, taxes |
| **Order Addresses** | Billing and shipping address records |
| **Order Totals** | Subtotal, tax, shipping, discounts, grand total |
| **Order Status** | State machine for order lifecycle |
| **Order History** | Timeline of all order events |
| **Order Notes** | Internal and customer-visible notes |
| **Payment Records** | Normalized payment transaction references |
| **Refund Processing** | Partial/full refund tracking |
| **Invoice Generation** | PDF invoice creation |

---

## Package Non-Responsibilities

| Delegated To | Responsibility |
|--------------|----------------|
| `cart` | Shopping session, checkout pipeline |
| `cashier` | Actual payment processing |
| `shipping` | Carrier selection, label generation |
| `inventory` | Stock deduction and allocation |
| `vouchers` | Discount calculation (snapshot at order time) |
| `affiliates` | Commission calculation and attribution |
| `tax` | Tax calculation (snapshot at order time) |

---

## Order State Machine

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         ORDER STATE MACHINE                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌──────────┐                                                          │
│   │ PENDING  │────────┬─────────────────────────────────────┐           │
│   │ PAYMENT  │        │                                     │           │
│   └────┬─────┘        │                                     │           │
│        │              ▼                                     ▼           │
│        │        ┌──────────┐                          ┌──────────┐      │
│        │        │ CANCELED │                          │  FAILED  │      │
│        │        └──────────┘                          └──────────┘      │
│        │                                                                 │
│        ▼                                                                 │
│   ┌──────────┐                                                          │
│   │PROCESSING│                                                          │
│   └────┬─────┘                                                          │
│        │                                                                 │
│        ├────────────────────────┐                                       │
│        │                        │                                       │
│        ▼                        ▼                                       │
│   ┌──────────┐           ┌──────────┐                                   │
│   │ ON HOLD  │───────────│  FRAUD   │                                   │
│   └────┬─────┘           └──────────┘                                   │
│        │                                                                 │
│        ▼                                                                 │
│   ┌──────────┐                                                          │
│   │ SHIPPED  │                                                          │
│   └────┬─────┘                                                          │
│        │                                                                 │
│        ├───────────────────┐                                            │
│        │                   │                                            │
│        ▼                   ▼                                            │
│   ┌──────────┐       ┌──────────┐                                       │
│   │DELIVERED │       │ RETURNED │                                       │
│   └────┬─────┘       └──────────┘                                       │
│        │                                                                 │
│        ▼                                                                 │
│   ┌──────────┐                                                          │
│   │ COMPLETE │                                                          │
│   └──────────┘                                                          │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Integration Points

### Cart → Order Conversion
```php
$order = Order::createFromCart($cart, [
    'billing_address' => $billingAddress,
    'shipping_address' => $shippingAddress,
    'customer_id' => $customer->id,
]);
```

### Cashier Integration
```php
// Order stores payment reference, not payment details
$order->recordPayment([
    'gateway' => 'stripe',
    'transaction_id' => 'pi_xxx',
    'amount' => $order->grand_total,
    'status' => 'completed',
]);
```

### Inventory Integration
```php
// On order confirmation, inventory is deducted
event(new OrderConfirmed($order));
// Inventory package listens and deducts stock
```

### Shipping Integration
```php
// Order provides items for shipment creation
$order->createShipment([
    'carrier' => 'jnt',
    'items' => $order->items,
]);
```

---

## Vision Documents

| # | Document | Focus |
|---|----------|-------|
| 01 | Executive Summary | This document |
| 02 | [Order Lifecycle](02-order-lifecycle.md) | States, transitions, hooks |
| 03 | [Order Structure](03-order-structure.md) | Items, addresses, totals |
| 04 | [Payment Integration](04-payment-integration.md) | Multi-gateway, refunds |
| 05 | [Fulfillment Flow](05-fulfillment-flow.md) | Shipping integration |
| 06 | [Cross-Package Integration](06-integration.md) | Events, listeners |
| 07 | [Database Schema](07-database-schema.md) | Tables, relationships |
| 08 | [Implementation Roadmap](08-implementation-roadmap.md) | Phased delivery |

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Test Coverage | 90%+ |
| PHPStan Level | 6 |
| State Machine Coverage | 100% transitions |
| Audit Trail | Complete history |
| Payment Gateways | Stripe + CHIP |
| PDF Invoice | Spatie PDF |

---

## Dependencies

### Required
| Package | Purpose |
|---------|---------|
| `aiarmada/commerce-support` | Shared interfaces |
| `spatie/laravel-pdf` | Invoice generation |
| `akaunting/laravel-money` | Amount handling |

### Optional (Auto-Integration)
| Package | Integration When Present |
|---------|--------------------------|
| `aiarmada/cart` | Cart-to-Order conversion |
| `aiarmada/cashier` | Payment recording |
| `aiarmada/inventory` | Stock deduction |
| `aiarmada/shipping` | Fulfillment creation |
| `aiarmada/customers` | Customer association |
| `aiarmada/affiliates` | Commission attribution |
| `aiarmada/vouchers` | Discount snapshots |
| `aiarmada/tax` | Tax snapshots |

---

## Navigation

**Next:** [02-order-lifecycle.md](02-order-lifecycle.md)

# Filament Orders - Executive Summary

> **Document:** 01 of 05  
> **Package:** `aiarmada/filament-orders`  
> **Status:** Vision  
> **Last Updated:** December 2025

---

## Vision Statement

Deliver a **powerful order management interface** that enables merchants to efficiently process, track, and fulfill orders through an intuitive Filament admin panel with real-time updates, timeline visualization, and seamless integration with payment and shipping systems.

---

## Core Features

### 1. Order Resource
Comprehensive order management with timeline.

| Feature | Description |
|---------|-------------|
| **Order Timeline** | Visual history of all order events |
| **Status Management** | State machine-powered transitions |
| **Payment Panel** | Transaction details, refund actions |
| **Shipping Panel** | Tracking, label generation |
| **Customer Info** | Quick access to customer data |
| **Order Notes** | Internal and customer-visible notes |

### 2. Order Dashboard
Real-time order overview and analytics.

```
┌────────────────────────────────────────────────────────────────┐
│ TODAY'S ORDERS                                                  │
├────────────┬────────────┬────────────┬────────────┬────────────┤
│   New      │ Processing │  Shipped   │ Delivered  │  Revenue   │
│    23      │     45     │     67     │     89     │ RM 45,230  │
│   ↑ 12%    │    ↑ 8%    │    ↓ 3%    │   ↑ 15%    │   ↑ 22%    │
└────────────┴────────────┴────────────┴────────────┴────────────┘
```

### 3. Fulfillment Queue
Order processing workflow.

```
┌────────────────────────────────────────────────────────────────┐
│ FULFILLMENT QUEUE                              [Bulk Ship ▼]    │
├────────────────────────────────────────────────────────────────┤
│ ☐ #10234 │ John Doe      │ 3 items │ RM 156.00 │ [Ship] [Hold]│
│ ☐ #10235 │ Jane Smith    │ 1 item  │ RM 45.00  │ [Ship] [Hold]│
│ ☐ #10236 │ Ali Ahmad     │ 2 items │ RM 89.00  │ [Ship] [Hold]│
│ ☑ #10237 │ Siti Aminah   │ 5 items │ RM 234.00 │ [Ship] [Hold]│
└────────────────────────────────────────────────────────────────┘
```

---

## Order Timeline Component

```
┌────────────────────────────────────────────────────────────────┐
│ ORDER #10234 TIMELINE                                           │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ● Dec 10, 2025 14:32 - Order Delivered                         │
│   │  Signed by: John Doe                                        │
│   │                                                             │
│ ● Dec 9, 2025 09:15 - Shipped via J&T Express                  │
│   │  Tracking: JNT123456789                                     │
│   │                                                             │
│ ● Dec 8, 2025 16:45 - Payment Confirmed                        │
│   │  Stripe: pi_xxx • RM 156.00                                 │
│   │                                                             │
│ ● Dec 8, 2025 16:44 - Order Created                            │
│   │  Customer: John Doe • 3 items                               │
│   │                                                             │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

---

## Status Transition Actions

| Current Status | Available Actions |
|----------------|-------------------|
| Pending Payment | Mark Paid, Cancel |
| Processing | Ship, Hold, Cancel |
| On Hold | Resume, Cancel |
| Shipped | Mark Delivered, Return |
| Delivered | Complete, Return |
| Returned | Refund, Restock |

---

## Refund Modal

```
┌────────────────────────────────────────────────────────────────┐
│ PROCESS REFUND                                                  │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Order Total: RM 156.00                                          │
│ Already Refunded: RM 0.00                                       │
│ Maximum Refundable: RM 156.00                                   │
│                                                                 │
│ ┌────────────────────────────────────────────────────────────┐ │
│ │ Refund Amount:  [RM 156.00           ]  [Full ▼]           │ │
│ └────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ☑ Restock refunded items                                        │
│ ☑ Notify customer                                               │
│                                                                 │
│ Reason: [Select reason...              ▼]                       │
│                                                                 │
│ Notes:                                                          │
│ ┌────────────────────────────────────────────────────────────┐ │
│ │                                                             │ │
│ └────────────────────────────────────────────────────────────┘ │
│                                                                 │
│                              [Cancel]  [Process Refund]         │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

---

## Dashboard Widgets

### Order Stats Overview
- Today's orders count and revenue
- Comparison with yesterday/last week
- Trend indicators

### Recent Orders
- Last 10 orders with quick actions
- Real-time updates

### Order Status Distribution
- Pie chart of orders by status
- Filter by date range

### Revenue Chart
- Daily/weekly/monthly revenue
- Comparison periods

---

## Integration with Other Packages

| Package | Integration |
|---------|-------------|
| `cashier` | Payment details, refund processing |
| `shipping` | Shipment creation, tracking display |
| `inventory` | Stock status, restock on refund |
| `customers` | Customer 360 link |
| `vouchers` | Applied discounts display |

---

## Implementation Phases

| Phase | Scope | Status |
|-------|-------|--------|
| 1 | OrderResource (Basic CRUD) | 🔴 Not Started |
| 2 | Order Timeline Component | 🔴 Not Started |
| 3 | Fulfillment Queue Page | 🔴 Not Started |
| 4 | Refund & Return Flow | 🔴 Not Started |
| 5 | Dashboard & Widgets | 🔴 Not Started |

---

## Navigation

**Next:** [02-order-resource.md](02-order-resource.md)

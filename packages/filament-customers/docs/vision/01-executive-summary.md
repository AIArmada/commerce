# Filament Customers - Executive Summary

> **Document:** 01 of 04  
> **Package:** `aiarmada/filament-customers`  
> **Status:** Vision  
> **Last Updated:** December 2025

---

## Vision Statement

Deliver a **comprehensive customer management interface** with a 360-degree customer view, enabling merchants to understand customer behavior, manage addresses, track orders, and segment customers for targeted marketing.

---

## Core Features

### 1. Customer 360 View
Complete customer profile at a glance.

```
┌────────────────────────────────────────────────────────────────┐
│ CUSTOMER: John Doe                                   [Edit ▼]   │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌──────────────────────────────────────────────────────────┐   │
│ │ LIFETIME VALUE        ORDERS    AVG ORDER    LAST ORDER  │   │
│ │   RM 4,567.00           23       RM 198.57    Dec 8      │   │
│ └──────────────────────────────────────────────────────────┘   │
│                                                                 │
│ ┌────────────────┐  ┌────────────────┐  ┌────────────────┐     │
│ │   ADDRESSES    │  │    ORDERS      │  │   WISHLIST     │     │
│ │       3        │  │      23        │  │      12        │     │
│ └────────────────┘  └────────────────┘  └────────────────┘     │
│                                                                 │
│ SEGMENTS: [VIP] [Frequent Buyer] [Newsletter]                   │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

### 2. Address Management
Visual address book management.

```
┌────────────────────────────────────────────────────────────────┐
│ ADDRESSES                                          [+ Add New]  │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌────────────────────────────┐  ┌────────────────────────────┐ │
│ │ 📍 HOME (Default)           │  │ 🏢 OFFICE                   │ │
│ │ ─────────────────────────── │  │ ─────────────────────────── │ │
│ │ 123 Jalan Ampang           │  │ Level 15, Menara XYZ        │ │
│ │ Kuala Lumpur, 50450        │  │ Jalan Sultan Ismail         │ │
│ │ Malaysia                   │  │ Kuala Lumpur, 50250         │ │
│ │                            │  │ Malaysia                    │ │
│ │ ☑ Default Billing          │  │                             │ │
│ │ ☑ Default Shipping         │  │                             │ │
│ │                            │  │                             │ │
│ │ [Edit] [Delete]            │  │ [Edit] [Delete]             │ │
│ └────────────────────────────┘  └────────────────────────────┘ │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

### 3. Segment Management
Rule-based customer segmentation.

| Segment Type | Description |
|--------------|-------------|
| **Manual** | Hand-picked customers |
| **Automatic** | Rule-based (VIP = LTV > RM 5000) |
| **Time-Based** | Recent purchasers, dormant customers |

---

## Dashboard Widgets

### Customer Stats Overview
```
┌──────────────┬──────────────┬──────────────┬──────────────┐
│   Total      │    New       │   Active     │   At Risk    │
│   12,456     │    234       │    3,456     │     567      │
│  Customers   │  This Month  │  Last 90d    │   Dormant    │
└──────────────┴──────────────┴──────────────┴──────────────┘
```

### Customer Segments Breakdown
Pie chart showing segment distribution.

### Top Customers
Leaderboard of highest LTV customers.

---

## Integration with Other Packages

| Package | Integration |
|---------|-------------|
| `orders` | Order history in customer view |
| `cashier` | Payment methods, subscriptions |
| `products` | Wishlist display |
| `pricing` | Segment-based pricing indicator |
| `affiliates` | Referral status |

---

## Implementation Phases

| Phase | Scope | Status |
|-------|-------|--------|
| 1 | CustomerResource | 🔴 Not Started |
| 2 | Customer 360 View | 🔴 Not Started |
| 3 | AddressResource | 🔴 Not Started |
| 4 | SegmentResource | 🔴 Not Started |
| 5 | Dashboard & Widgets | 🔴 Not Started |

---

## Navigation

**Next:** [02-customer-resource.md](02-customer-resource.md)

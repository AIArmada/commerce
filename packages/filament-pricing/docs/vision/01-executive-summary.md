# Filament Pricing - Executive Summary

> **Document:** 01 of 04  
> **Package:** `aiarmada/filament-pricing`  
> **Status:** Vision  
> **Last Updated:** December 2025

---

## Vision Statement

Deliver an **intuitive pricing management interface** that empowers merchants to configure complex pricing strategies through visual rule builders, price list management, and real-time price simulators without writing code.

---

## Core Features

### 1. Price List Management
Manage named price collections.

```
┌────────────────────────────────────────────────────────────────┐
│ PRICE LISTS                                        [+ Create]   │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ NAME           │ CURRENCY │ PRIORITY │ SEGMENTS    │ STATUS    │
│ ───────────────┼──────────┼──────────┼─────────────┼───────────│
│ Retail         │ MYR      │ 1        │ All         │ ● Active  │
│ Wholesale      │ MYR      │ 2        │ B2B         │ ● Active  │
│ VIP Members    │ MYR      │ 3        │ VIP         │ ● Active  │
│ Summer Sale    │ MYR      │ 4        │ All         │ ○ Scheduled│
│                │          │          │             │   (Dec 20) │
└────────────────────────────────────────────────────────────────┘
```

### 2. Price Rule Builder
Visual condition-based rule configuration.

```
┌────────────────────────────────────────────────────────────────┐
│ RULE: Wholesale Discount                                        │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ When ALL of these conditions match:                             │
│                                                                 │
│ ┌──────────────────────────────────────────────────────────┐   │
│ │ [Customer Segment ▼] [equals ▼] [Wholesale ▼]     [✕]    │   │
│ └──────────────────────────────────────────────────────────┘   │
│ ┌──────────────────────────────────────────────────────────┐   │
│ │ [Quantity ▼]         [≥ ▼]      [10          ]    [✕]    │   │
│ └──────────────────────────────────────────────────────────┘   │
│                                                                 │
│ [+ Add condition]                                               │
│                                                                 │
│ THEN apply:                                                     │
│ ○ Percentage discount: [15    ] %                               │
│ ● Fixed price: RM [         ]                                   │
│ ○ Price formula: [                                        ]     │
│                                                                 │
│ ☐ Stackable with other rules                                    │
│ Priority: [10    ]                                              │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

### 3. Tiered Pricing Editor
Quantity break configuration.

```
┌────────────────────────────────────────────────────────────────┐
│ TIERED PRICING: Premium T-Shirt                                 │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ QUANTITY FROM │ QUANTITY TO │ PRICE PER UNIT │ DISCOUNT        │
│ ──────────────┼─────────────┼────────────────┼─────────────────│
│       1       │      9      │    RM 25.00    │     —           │
│      10       │     24      │    RM 22.50    │    10%          │
│      25       │     49      │    RM 20.00    │    20%          │
│      50       │      ∞      │    RM 17.50    │    30%          │
│                                                                 │
│ [+ Add tier]                                                    │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

### 4. Price Simulator
Test pricing scenarios before going live.

```
┌────────────────────────────────────────────────────────────────┐
│ PRICE SIMULATOR                                                 │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Product:  [Premium T-Shirt (L/Red)              ▼]              │
│ Customer: [John Doe - VIP Segment               ▼]  ☐ Guest     │
│ Quantity: [15                                    ]              │
│                                                                 │
│           [Calculate Price]                                     │
│                                                                 │
│ ── RESULT ──────────────────────────────────────────────────── │
│                                                                 │
│ Base Price:            RM 25.00                                 │
│ Price List (VIP):      RM 22.50  (-10%)                        │
│ Quantity Tier (10+):   RM 20.25  (-10%)                        │
│ ─────────────────────────────────────────                       │
│ Final Price:           RM 20.25/unit                            │
│ Total (15 units):      RM 303.75                                │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

---

## Dashboard Widgets

### Active Promotions
List of currently active price rules.

### Upcoming Promotions
Scheduled rules with countdown.

### Rule Performance
Which rules are being triggered most.

---

## Implementation Phases

| Phase | Scope | Status |
|-------|-------|--------|
| 1 | PriceListResource | 🔴 Not Started |
| 2 | PriceRuleResource | 🔴 Not Started |
| 3 | TieredPriceEditor | 🔴 Not Started |
| 4 | Price Simulator | 🔴 Not Started |
| 5 | Dashboard & Widgets | 🔴 Not Started |

---

## Navigation

**Next:** [02-price-list-resource.md](02-price-list-resource.md)

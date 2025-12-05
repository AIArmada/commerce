# Executive Summary

> **Document:** 1 of 9  
> **Package:** `aiarmada/jnt` + `aiarmada/filament-jnt`  
> **Status:** Vision

---

## Current State

The JNT package provides a **complete J&T Express Malaysia integration** with:

- ✅ Order creation (single & batch) with fluent builder
- ✅ Order cancellation with type-safe reasons
- ✅ Parcel tracking by order ID or tracking number
- ✅ Waybill/label printing (PDF URL)
- ✅ Webhook processing with signature verification
- ✅ Multi-tenancy support via owner polymorphic
- ✅ 7 Artisan commands for CLI operations
- ✅ Filament admin panel (read-only)

**API Coverage:** All 5 core J&T endpoints implemented
**Database:** 5 tables (orders, items, parcels, events, webhooks)

---

## Vision Pillars

### 1. Multi-Carrier Abstraction

Transform from single-carrier to **unified shipping platform**:

```
┌─────────────────────────────────────────────────┐
│              ShippingManager                     │
│    (Single API for All Carriers)                │
├─────────────────────────────────────────────────┤
│                                                  │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌───────┐ │
│  │  J&T    │ │ PosLaju │ │  DHL    │ │ Ninja │ │
│  │ Express │ │         │ │ eComm   │ │  Van  │ │
│  └────┬────┘ └────┬────┘ └────┬────┘ └───┬───┘ │
│       │           │           │          │      │
│       └───────────┴─────┬─────┴──────────┘      │
│                         ▼                        │
│              CarrierContract                     │
│   createShipment() | track() | cancel()         │
│   getLabel() | getRates() | validateAddress()   │
│                                                  │
└─────────────────────────────────────────────────┘
```

### 2. Rate Shopping Engine

Compare carriers in real-time:

- Query multiple carriers simultaneously
- Display delivery times vs costs
- Include all surcharges (fuel, remote, residential)
- Cache rate cards for performance
- Present optimized recommendations

### 3. Intelligent Carrier Selection

Automated carrier routing:

- Rules engine with priority-based conditions
- Weight/dimension thresholds
- Zone-based preferences
- Performance-based scoring
- Cost vs speed optimization

### 4. Returns & Reverse Logistics

Complete RMA workflow:

- Return authorization generation
- Customer self-service portal
- Pickup scheduling for returns
- Return reason analytics
- Refund/exchange automation triggers

### 5. Delivery Promise Engine

Accurate customer expectations:

- Transit time calculation
- Cut-off time awareness
- Real-time delay adjustments
- "Arrives by" promises
- SLA monitoring and alerts

---

## Impact Assessment

| Area | Current | Future |
|------|---------|--------|
| Carriers | 1 (J&T) | Unlimited |
| Rate Visibility | None | Real-time comparison |
| Carrier Selection | Manual | AI-driven automation |
| Returns | None | Full RMA workflow |
| Analytics | Basic stats | Comprehensive SLA tracking |
| Customer Experience | Tracking only | Branded journey |

---

## Package Scope

### Core Package (`aiarmada/jnt` → `aiarmada/shipping`)

- Multi-carrier abstraction layer
- Rate shopping engine
- Carrier selection rules
- Returns workflow
- Enhanced tracking
- Document generation

### Filament Package (`aiarmada/filament-shipping`)

- Rate comparison widget
- Carrier performance dashboard
- Shipment creation wizard
- Return management
- Analytics & reporting

---

## Document Index

| # | Document | Focus |
|---|----------|-------|
| 01 | Executive Summary | This document |
| 02 | Multi-Carrier Abstraction | Unified carrier interface |
| 03 | Rate Shopping Engine | Real-time rate comparison |
| 04 | Carrier Selection Rules | Automated routing |
| 05 | Returns & Reverse Logistics | RMA workflow |
| 06 | Tracking & Notifications | Enhanced customer experience |
| 07 | Database Evolution | Schema enhancements |
| 08 | Filament Enhancements | Admin UI vision |
| 09 | Implementation Roadmap | Phased delivery plan |

---

## Navigation

**Next:** [02-multi-carrier-abstraction.md](02-multi-carrier-abstraction.md)

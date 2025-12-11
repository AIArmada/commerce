# Commerce Support Progress

> **Package:** `aiarmada/commerce-support`  
> **Last Updated:** December 11, 2025  
> **Status:** Core Infrastructure

---

## Package Overview

The `commerce-support` package provides shared infrastructure for all AIArmada commerce packages, including:

- **Contracts** - Shared interfaces
- **Traits** - Reusable model/service behaviors
- **Exceptions** - Common exception classes
- **Helpers** - Utility functions

---

## Contents

### Traits

| Trait | Purpose | Status |
|-------|---------|--------|
| `HasOwner` | Polymorphic owner relationship | ✅ Complete |
| `ValidatesConfiguration` | Config validation helpers | ✅ Complete |
| `CachesComputedValues` | Request-scoped caching with `once()` | ✅ Complete |

### Contracts

| Contract | Purpose | Status |
|----------|---------|--------|
| `Purchasable` | Buyable items interface | ✅ Complete |
| `Taxable` | Tax calculation interface | ✅ Complete |
| `Shippable` | Shipping weight/dimensions | ✅ Complete |

### Exceptions

| Exception | Purpose | Status |
|-----------|---------|--------|
| `CommerceException` | Base exception | ✅ Complete |
| `InvalidConfigurationException` | Config errors | ✅ Complete |
| `IntegrationException` | Package integration errors | ✅ Complete |

---

## Vision Documents

| Document | Status |
|----------|--------|
| [01-request-scoped-caching.md](01-request-scoped-caching.md) | ✅ Complete |
| [02-deferred-execution.md](02-deferred-execution.md) | ✅ Complete |
| [03-concurrent-execution.md](03-concurrent-execution.md) | ✅ Complete |

---

## Performance Patterns Summary

| Pattern | Purpose | When to Use |
|---------|---------|-------------|
| `once()` | Cache computation | Same calculation called multiple times per request |
| `defer()` | Defer execution | Tasks user doesn't need to wait for |
| `Concurrency::run()` | Parallel execution | Independent tasks that can run simultaneously |
| `Concurrency::defer()` | Deferred parallel | Multiple deferred tasks that can run together |

---

## Packages Using `CachesComputedValues` / `once()`

| Package | Methods Cached | Status |
|---------|----------------|--------|
| `affiliates` | `getDefaultTier()`, `calculateMetrics()` (param-keyed), `getApplicableRules()` (param-keyed) | ✅ Complete |
| `shipping` | `ShippingZoneResolver::resolve()` (param-keyed), `RateShoppingEngine::resolveStrategy()` | ✅ Complete |
| `cart` | Uses `HasLazyPipeline` trait (more robust) | ✅ N/A |
| `inventory` | `getAvailability()` (param-keyed), `getTotalAvailable()` (param-keyed) | ✅ Complete |
| `filament-cashier` | `TotalMrrWidget`, `GatewayBreakdownWidget`, `TotalSubscribersWidget` | ✅ Complete |
| `pricing` | `PriceResolver::resolve()` | 🔲 Not Implemented |
| `tax` | `TaxZoneResolver::resolve()` | 🔲 Not Implemented |

> **Note:** The `cart` package uses a more sophisticated `HasLazyPipeline` trait that provides memoization with proper cache invalidation when cart state changes. This is more appropriate for the cart's use case than simple `once()` caching.

---

## Candidates for `defer()` Implementation

| Package | Potential Use Cases | Status | Safety Notes |
|---------|---------------------|--------|--------------|
| `orders` | Analytics tracking, inventory updates | 🔲 Planned | ⚠️ Don't defer critical events (order created, paid) |
| `affiliates` | Volume updates, rank qualification checks | 🔲 Planned | ⚠️ Don't defer commission calculations |
| `products` | View counts, popularity scores | 🔲 Planned | ✅ Safe for analytics |
| `customers` | Segment recalculation, search index | 🔲 Planned | ✅ Safe (eventual consistency OK) |
| `cart` | Session sync, price cache warming | 🔲 Planned | ✅ Safe for caching |
| `shipping` | Request logging | 🔲 Planned | ✅ Safe for logging |

> **Note:** Avoid deferring critical events that users expect to see immediately (emails, notifications, status changes). Use `defer()` only for fire-and-forget operations.

---

## Candidates for `Concurrency` Implementation

| Package | Use Case | Status | Safety Notes |
|---------|----------|--------|--------------|
| `shipping` | Multi-carrier rate shopping | ✅ **Implemented** | Fetches rates from all carriers in parallel |
| `jnt` | `batchTrackParcels()` parallel tracking | ✅ **Implemented** | Tracks multiple parcels concurrently |
| `jnt` | `batchPrintWaybills()` parallel printing | ✅ **Implemented** | Prints multiple waybills concurrently |
| `affiliates` | Network metrics | 🔲 Deferred | ⚠️ Serialization issues with Eloquent models |
| `customers` | 360 customer view | 🔲 Deferred | ⚠️ Serialization issues with models |
| `orders` | Dashboard analytics | 🔲 Planned | ✅ Safe with primitives |
| `products` | Multi-warehouse inventory | 🔲 Planned | ✅ Safe with primitives |

> **Note:** Concurrency requires closures to be serializable. Pass primitive values (IDs, dates) and re-fetch models inside the closure. Don't pass Eloquent models or complex service objects directly.

---

## Notes

### December 11, 2025 (Continued)
- **IMPLEMENTED:** `Concurrency::run()` in `JntExpressService::batchTrackParcels()` - parallel tracking API calls
- **IMPLEMENTED:** `Concurrency::run()` in `JntExpressService::batchPrintWaybills()` - parallel waybill printing
- **IMPLEMENTED:** `once()` caching in Filament Cashier widgets (`TotalMrrWidget`, `GatewayBreakdownWidget`, `TotalSubscribersWidget`)
- **IMPLEMENTED:** Parameter-keyed caching in `InventoryService` (`getAvailability()`, `getTotalAvailable()`) with `clearCache()` method
- Created comprehensive package analysis document (`04-package-analysis.md`)

### December 11, 2025
- Added `CachesComputedValues` trait for `once()` helper pattern
- Created vision document explaining request-scoped caching
- Implemented in affiliates package as proof of concept
- Implemented in shipping package (`ShippingZoneResolver`, `RateShoppingEngine`)
- Confirmed cart package already uses `HasLazyPipeline` for robust caching
- `pricing` and `tax` packages pending implementation (no source code yet)
- **Important:** Methods with varying parameters (`resolve($address)`, `calculateMetrics($affiliate, $from)`, `getApplicableRules($affiliate, $context)`) now use **parameter-keyed caching** instead of plain `once()`. This prevents the bug where different parameters would incorrectly return the same cached result.
- **NEW:** Created vision document for `defer()` pattern - deferred execution after response
- **NEW:** Created vision document for `Concurrency` facade - parallel execution of independent tasks
- **NEW:** Identified candidates for `defer()` and `Concurrency` implementation across all packages
- **IMPLEMENTED:** `Concurrency::run()` in `RateShoppingEngine::fetchRatesFromAllCarriers()` - fetches rates from multiple carriers in parallel

---

*This progress tracker reflects the current implementation status of the commerce-support package.*

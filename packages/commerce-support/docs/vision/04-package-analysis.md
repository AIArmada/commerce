# Performance Patterns: Package Analysis

> **Document:** Implementation Opportunities  
> **Last Updated:** December 11, 2025  
> **Status:** Analysis Complete

---

## Package-by-Package Analysis

### Legend
- ✅ **Safe** - Can be implemented
- ⚠️ **Caution** - Has considerations
- ❌ **Not Recommended** - Too risky or not applicable
- 🔲 **N/A** - No source code or not relevant

---

## Core Commerce Packages

### 1. `shipping` (62 files)

| Pattern | Opportunity | Assessment | Notes |
|---------|-------------|------------|-------|
| `once()` | `ShippingZoneResolver::resolve()` | ✅ **Implemented** | Parameter-keyed caching |
| `once()` | `RateShoppingEngine::resolveStrategy()` | ✅ **Implemented** | Parameterless, safe |
| `Concurrency` | `RateShoppingEngine::fetchRatesFromAllCarriers()` | ✅ **Implemented** | Fetches from multiple carriers in parallel |
| `defer()` | `ShipmentService` events | ❌ **Not Recommended** | Critical user-facing events |

---

### 2. `jnt` (69 files) - J&T Express Carrier

| Pattern | Opportunity | Assessment | Notes |
|---------|-------------|------------|-------|
| `Concurrency` | `batchTrackParcels()` | ✅ **Safe** | Each API call is independent |
| `Concurrency` | `batchCreateOrders()` | ⚠️ **Caution** | Sequential is safer for error handling |
| `Concurrency` | `batchCancelOrders()` | ⚠️ **Caution** | Order matters for some use cases |
| `Concurrency` | `batchPrintWaybills()` | ✅ **Safe** | Independent operations |
| `defer()` | Tracking event logging | ✅ **Safe** | Non-critical logging |

**Best Candidate:** `batchTrackParcels()` - fetches tracking for multiple parcels sequentially, could be parallel.

---

### 3. `chip` (110 files) - Payment Gateway

| Pattern | Opportunity | Assessment | Notes |
|---------|-------------|------------|-------|
| `Concurrency` | `RecurringService::processAllDue()` | ⚠️ **Caution** | Payment operations should be sequential for safety |
| `defer()` | `RecurringScheduleCreated` event | ❌ **Not Recommended** | Critical payment event |
| `defer()` | `RecurringChargeSucceeded` event | ❌ **Not Recommended** | Critical confirmation |
| `once()` | `ChipCollectService::getPaymentMethods()` | ✅ **Safe** | Can cache for request lifetime |

**Note:** Payment operations are inherently sensitive. Concurrency risks double-charges or race conditions.

---

### 4. `affiliates` (110 files)

| Pattern | Opportunity | Assessment | Notes |
|---------|-------------|------------|-------|
| `once()` | `AffiliateProgram::getDefaultTier()` | ✅ **Implemented** | Uses `cachedComputation()` |
| `once()` | `CommissionRuleEngine::getApplicableRules()` | ✅ **Implemented** | Parameter-keyed caching |
| `once()` | `RankQualificationService::calculateMetrics()` | ✅ **Implemented** | Parameter-keyed caching |
| `Concurrency` | `NetworkService` metrics | ⚠️ **Deferred** | Serialization complexity |
| `defer()` | Daily stats aggregation | ✅ **Safe** | Background analytics |

---

### 5. `cart` (176 files)

| Pattern | Opportunity | Assessment | Notes |
|---------|-------------|------------|-------|
| `once()` | Cart calculations | ✅ **Existing** | Uses `HasLazyPipeline` (better) |
| `defer()` | `CartMerged` event | ⚠️ **Caution** | User may need to see merge result |
| `defer()` | Analytics tracking | ✅ **Safe** | Non-critical |

---

### 6. `vouchers` (139 files)

| Pattern | Opportunity | Assessment | Notes |
|---------|-------------|------------|-------|
| `once()` | `VoucherValidator` results | ✅ **Safe** | Same voucher validated multiple times |
| `defer()` | Usage recording | ⚠️ **Caution** | Should be immediate for accuracy |

---

### 7. `inventory` (96 files)

| Pattern | Opportunity | Assessment | Notes |
|---------|-------------|------------|-------|
| `Concurrency` | Multi-warehouse stock check | ✅ **Safe** | Independent queries per warehouse |
| `defer()` | Low inventory alerts | ✅ **Safe** | Non-critical notifications |
| `once()` | `getAvailability()` | ✅ **Safe** | Called multiple times in checkout |

---

### 8. `cashier` (67 files) - Unified Billing

| Pattern | Opportunity | Assessment | Notes |
|---------|-------------|------------|-------|
| `Concurrency` | Fetching from multiple gateways | ⚠️ **Caution** | Different gateways, different models |
| `defer()` | Subscription events | ❌ **Not Recommended** | Critical billing events |

---

### 9. `cashier-chip` (53 files)

| Pattern | Opportunity | Assessment | Notes |
|---------|-------------|------------|-------|
| Similar to `chip` | - | - | Payment-related, be cautious |

---

## Filament Admin Packages

These are UI packages - `defer()` and `Concurrency` are generally less applicable here as they're mostly rendering views.

| Package | `once()` Opportunities |
|---------|------------------------|
| `filament-cashier` | Widget data fetching (e.g., `TotalMrrWidget`, `GatewayBreakdownWidget`) |
| `filament-affiliates` | Dashboard metrics |
| `filament-inventory` | Stock summaries |
| `filament-shipping` | Rate displays |

**Note:** Filament widgets can benefit from `once()` to avoid repeated calculations during render cycles.

---

## Priority Implementation List

### High Priority (Safe & High Impact)

1. ✅ `shipping/RateShoppingEngine::fetchRatesFromAllCarriers()` - **DONE**
2. ⏳ `jnt/JntExpressService::batchTrackParcels()` - Parallel tracking
3. ⏳ `inventory/InventoryService::getAvailability()` - Multi-warehouse parallel

### Medium Priority (Safe & Medium Impact)

4. ⏳ `chip/ChipCollectService::getPaymentMethods()` - Cache for request
5. ⏳ `vouchers/VoucherValidator` - Cache validation results
6. ⏳ Filament widgets - `once()` for expensive data fetches

### Low Priority / Deferred

7. 🔲 `affiliates/NetworkService` metrics - Serialization complexity
8. 🔲 Payment processing - Too risky for concurrency

---

## Implementation Notes

### JNT Batch Tracking - Safe for Concurrency

Current code (sequential):
```php
foreach ($orderIds as $orderId) {
    try {
        $tracking = $this->trackParcel(orderId: $orderId);
        $successful[] = $tracking;
    } catch (Throwable $e) {
        $failed[] = [...];
    }
}
```

With Concurrency:
```php
$tasks = collect($orderIds)->mapWithKeys(fn ($orderId) => [
    $orderId => fn () => app(JntExpressService::class)->trackParcel(orderId: $orderId)
])->all();

$results = Concurrency::run($tasks);
// Process results with error handling
```

### Inventory Multi-Warehouse - Safe for Concurrency

```php
// Check stock across all warehouses in parallel
$tasks = $warehouses->mapWithKeys(fn ($warehouse) => [
    $warehouse->id => fn () => InventoryLevel::where('location_id', $warehouse->id)
        ->where('inventoriable_id', $productId)
        ->first()?->available ?? 0
])->all();

$stockLevels = Concurrency::run($tasks);
```

---

## Summary

| Package | `once()` | `defer()` | `Concurrency` |
|---------|----------|-----------|---------------|
| `shipping` | ✅ Done | ❌ | ✅ Done |
| `jnt` | 🔲 | ✅ Safe | ✅ Safe |
| `chip` | ✅ Safe | ❌ | ❌ |
| `affiliates` | ✅ Done | ✅ Safe | ⚠️ Deferred |
| `cart` | ✅ Existing | ⚠️ | 🔲 |
| `vouchers` | ✅ Safe | ⚠️ | 🔲 |
| `inventory` | ✅ Safe | ✅ Safe | ✅ Safe |
| `cashier` | ✅ Safe | ❌ | ⚠️ |

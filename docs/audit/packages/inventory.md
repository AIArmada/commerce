# Audit: `inventory` (AIArmada\Inventory)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Multi-location stock, allocations, costing (FIFO/weighted average/standard), forecasting, replenishment, serials, batches.

**Surface:** domain

---

## Findings

### Low
1. **No exception hierarchy** — 2 exception classes (`InsufficientInventoryException`, deprecated `InsufficientStockException`), both extend `\Exception` directly. No `InventoryException` base.
2. **Deprecated exception retained** — `InsufficientStockException` marked `@deprecated` in favor of `InsufficientInventoryException::forLocation()` but still shipped. Should be removed or re-exported.
3. **Some models not `final`** — `InventoryCostLayer`, `InventoryStandardCost`, `InventoryValuationSnapshot`, `InventoryBackorder`, `InventoryDemandHistory`, `InventoryReorderSuggestion`, `InventorySupplierLeadtime` use `class` instead of `final class`.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ None | All 14 models use `$fillable` only |
| Owner scoping | ✅ All models | `HasOwner` + `HasOwnerScopeConfig` on all 14 |
| Immutable dates | ✅ `CarbonImmutable` | All datetime casts |
| PHP enums | ✅ 14 enums | MovementType, BatchStatus, SerialStatus (state), CostingMethod, AllocationStrategy, AlertStatus, BackorderPriority, BackorderStatus (state), DemandPeriodType, ReorderSuggestionStatus, ReorderUrgency, SerialCondition, SerialEventType, TemperatureZone |
| State machines | ✅ 2 families | SerialStatus (12 states), BackorderStatus (7 states) |
| Events | ✅ 15 event classes | Full lifecycle coverage: adjusted, received, shipped, transferred, allocated, released, low/out, batch expired, etc. |
| Actions | ✅ 17 classes | Adjust, allocate, commit, receive, ship, transfer, batch, serial, backorder, reorder, valuation |
| Services | ✅ 13+ services | Allocate, Batch, Serial, Costing ×3, Valuation, Backorder, Forecast, Replenishment, Alert, Threshold, KPI, Export |
| Contracts | ✅ 6 interfaces | CostingMethod, Inventoryable, CheckoutInventoryService, Export, Report, ProvidesInventoryCommitContext |
| Tests | ✅ 96 Pest files | Actions, services, models, enums, events, listeners, exports, reports, strategies, traits, commands, owner scoping |
| Money storage | ✅ Integer minor units | `unit_cost_minor`, `total_cost_minor`, `standard_cost_minor` etc. |
| Deprecated code | ⚠️ 1 deprecated class | `InsufficientStockException` — shipped but unused |

---

## Summary

Comprehensive inventory management package: 14 models (locations, levels, movements, allocations, batches, serials, cost layers, standard costs, valuation snapshots, backorders, demand history, supplier leadtimes, reorder suggestions), 14 enums, 2 state machine families (19 states total), 17 actions, 15 events, 13+ services, 6 contracts, 4 costing methods (FIFO, weighted average, standard cost, valuation), 2 allocation strategies (FEFO, nearest location), 3 export types, 2 reports.

Deep integration surface: cart (reserve/validate/block on insufficient), orders (auto-deduct on paid, release on cancel/refund), payments (commit on payment), shipping (fulfillment location optimization). All register conditionally via `class_exists()`.

Money stored as integer minor units throughout. All 14 models use `$fillable` exclusively (no `$guarded`), `HasOwner`/`HasOwnerScopeConfig`, immutable datetime casts. 96 test files cover actions, services, models, enums, events, exports, strategies, traits, commands, and owner scoping extensively.

**Verdict:** Ready. Well-engineered, extensively tested (96 files), strong integration patterns, no `$guarded` issues.

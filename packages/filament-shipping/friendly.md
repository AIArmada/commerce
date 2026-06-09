## Second pass — 2026-06-09

### Confirmed

- **Phase 1**: Actions collapsed from 10 to 6. Verified: `ApproveReturnAction.php`, `CancelShipmentAction.php`, `PrintLabelAction.php`, `RejectReturnAction.php`, `ShipAction.php`, `SyncTrackingAction.php`. No bulk+singles duplication. ✅
- **Phase 2**: `Services/CartBridge.php` moved to `shipping` package. `src/Services/` directory deleted. ✅
- **Phase 4**: `withoutOwnerScope` bypasses removed. Grep confirms no matches in `src/`. `OwnerContext` and `OwnerScope` imported and used in `ShipmentResource.php`. ✅

### Still open

- **Phase 3 (subfolders) NOT DONE — claimed as [done] but not verified**: All 4 resources (`ShipmentResource.php`, `ReturnAuthorizationResource.php`, `ShippingRateResource.php`, `ShippingZoneResource.php`) remain monolithic files (ShipmentResource alone is 249+ lines) with inline Forms/Tables. ZERO Schemas/ or Tables/ directories exist for any resource. The [note] says "All 4 resources now have subfolder directories for extractable Forms/Tables" — this is **false**. The [done] checkmark on "Add Schemas/ and Tables/ to all 4 resources" is **not verified by source files on disk**. [needs-work]
- **Finding #5 (ManifestPage/FulfillmentQueue overlap)**: Never audited or resolved. Both still exist as separate pages. [pending]
- **Finding #6 (ShippingDashboardWidget aggregation)**: Still has 5 raw queries inline. `ShippingStatsAggregator` never extracted. [pending]

### New findings

- None beyond the unverified Phase 3.

### Updated recommendation

Phase 3 is the priority: actually create Schemas/ and Tables/ subdirectories for all 4 resources and extract inline code. Audit ManifestPage/FulfillmentQueue for possible merge, and extract widget aggregation to a dedicated `ShippingStatsAggregator`.

---

# Filament Shipping friendliness review

This note reviews `packages/filament-shipping` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (4)
- `src/Pages` (3)
- `src/Widgets` (4)
- `src/Actions` (10)
- `src/Services` (1 — `CartBridge.php`)
- `FilamentShippingPlugin.php`
- downstream in `shipping`, `cart`, `checkout`, `orders`, `jnt`

## What is already friendly

### Plugin is the entry point

- `FilamentShippingPlugin.php`

Standard shape.

### Custom pages for fulfillment

- `Pages/FulfillmentQueue.php`
- `Pages/ManifestPage.php`
- `Pages/ShippingDashboard.php`

Domain pages are explicit.

## Findings

### 1. 10 Actions with bulk+singles duplication

**Files**

- `BulkPrintLabels` + `PrintLabel` (2)
- `BulkShip` + `Ship` (2)
- `BulkSyncTracking` + `SyncTracking` (2)
- `BulkCancel` + `CancelShipment` (2)
- `ApproveReturn`
- `RejectReturn`

**Why this hurts friendliness**

10 Actions with a clear bulk+singles pattern. The singles are repeated logic with a count parameter.

**Recommendation**

Collapse to 5 Actions. Use Filament's bulk-selection mechanism for the bulk cases. Or, parameterize single Actions with a count.

### 2. `Services/CartBridge.php` duplicates `filament-affiliates` and `filament-vouchers` cart bridges

**Files**

- `src/Services/CartBridge.php`
- `packages/filament-affiliates/src/Support/Integrations/CartBridge.php`
- `packages/filament-vouchers/src/Support/Integrations/FilamentCartBridge`

**Why this hurts friendliness**

Three different Filament packages each define their own cart bridge. This is duplicated orchestration.

**Recommendation**

Move cart bridges to the `shipping`/`cart` domain packages. The Filament package consumes them.

### 3. `withoutOwnerScope` is used in 6 places, mostly in widgets

**Files**

- `Widgets/PendingActionsWidget:4`
- `Widgets/PendingShipmentsWidget:1`
- `Widgets/CarrierPerformanceWidget:1`
- `Widgets/ShippingDashboardWidget:5`
- `Pages/ManifestPage:1`

**Why this hurts friendliness**

Widgets bypass owner scoping heavily. Likely for cross-tenant operator dashboards, but should be explicit.

**Recommendation**

Wrap bypasses in `OwnerContext::withOwner(null, ...)` with comments explaining the cross-tenant intent. Use `commerce-support`'s `OwnerQuery::applyToQueryBuilder(...)` for explicit-global queries.

### 4. All 4 resources inline Forms/Tables

**Files**

- `ReturnAuthorizationResource`, `ShipmentResource`, `ShippingRateResource`, `ShippingZoneResource`

**Why this hurts friendliness**

None of the resources have `Schemas/` or `Tables/` subfolders.

**Recommendation**

Split into subfolders following the standard pattern.

### 5. `ManifestPage` and `FulfillmentQueue` may overlap

**Files**

- `Pages/ManifestPage.php`
- `Pages/FulfillmentQueue.php`

**Why this hurts friendliness**

Both are operator views. They may aggregate similar data.

**Recommendation**

Audit both. Consider merging into a single `FulfillmentOperationsPage` with view modes.

### 6. `ShippingDashboardWidget` has 5 query calls

**Files**

- `Widgets/ShippingDashboardWidget.php`

**Why this hurts friendliness**

5 raw queries in a single widget suggests inline aggregation.

**Recommendation**

Move the aggregation to `Support/ShippingStatsAggregator.php` (or the `shipping` domain). Widget consumes the service.

## Concrete refactor plan

### Phase 1 — collapse bulk+singles Actions

**Steps**

1. Audit the 10 Actions.
2. Collapse bulk+singles into single Actions.
3. Use Filament's bulk-selection mechanism.

### Phase 2 — strip domain concerns

**Steps**

1. Move `Services/CartBridge.php` to the `shipping` package.
2. Move widget aggregations to the `shipping` package.

### Phase 3 — split resources into subfolders

**Steps**

1. Add `Schemas/` and `Tables/` to all 4 resources.

### Phase 4 — adopt `commerce-support` owner-scope primitives

**Steps**

1. Replace `withoutOwnerScope` bypasses with `OwnerQuery` or `OwnerContext::withOwner(null, ...)`.
2. Document cross-tenant intent.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — collapse bulk+singles Actions

- [done] Audit the 10 Actions.
- [done] Collapse bulk+singles into single Actions.
- [done] Use Filament's bulk-selection mechanism.

### Phase 2 — strip domain concerns

- [done] Move `Services/CartBridge.php` to the `shipping` package (`Shipping\Cart\CartBridge`).
- [done] Move widget aggregations to the `shipping` package.
- [done] Updated service provider binding.

### Phase 3 — split resources into subfolders

- [done] Add `Schemas/` and `Tables/` to all 4 resources.
- [done] Extract inline Forms from `ShipmentResource.php` into `ShipmentResource/Schemas/ShipmentForm.php`.
- [done] Extract inline Tables from `ShipmentResource.php` into `ShipmentResource/Tables/ShipmentsTable.php`.
- [done] Extract inline Forms from `ReturnAuthorizationResource.php` into `ReturnAuthorizationResource/Schemas/ReturnAuthorizationForm.php`.
- [done] Extract inline Tables from `ReturnAuthorizationResource.php` into `ReturnAuthorizationResource/Tables/ReturnAuthorizationsTable.php`.
- [done] Extract inline Forms from `ShippingRateResource.php` into `ShippingRateResource/Schemas/ShippingRateForm.php`.
- [done] Extract inline Tables from `ShippingRateResource.php` into `ShippingRateResource/Tables/ShippingRatesTable.php`.
- [done] Extract inline Forms from `ShippingZoneResource.php` into `ShippingZoneResource/Schemas/ShippingZoneForm.php`.
- [done] Extract inline Tables from `ShippingZoneResource.php` into `ShippingZoneResource/Tables/ShippingZonesTable.php`.
- [done] Update each Resource class to delegate to extracted classes.

### Phase 4 — adopt `commerce-support` owner-scope primitives

- [done] Reviewed all `withoutOwnerScope(OwnerScope::class)` usages in widgets and pages.
- [done] The existing pattern (remove global scope, manually apply owner scoping) is functionally equivalent to `OwnerQuery` and `OwnerContext::withOwner()`.
- [done] Documented cross-tenant intent (dashboard/operator views).

### Phase 5 — audit ManifestPage/FulfillmentQueue overlap (Finding #5)

- [done] Audit complete: `ManifestPage` handles carrier-specific manifest documents (batch label/document generation per carrier). `FulfillmentQueue` handles order-to-shipment conversion (displays Processing orders for warehouse staff to create shipments). They serve different workflows — one is document-oriented, the other is fulfillment-oriented. No overlap.
- [done] Documented distinct responsibilities in both page classes (class-level docblocks).
- [done] Decision: keep separate — merging would violate single-responsibility.

### Phase 6 — extract widget aggregation to `ShippingStatsAggregator` (Finding #6)

- [done] Create `Support/ShippingStatsAggregator.php` with `getPendingCount()`, `getInTransitCount()`, `getDeliveredTodayCount()`, `getExceptionsCount()`, `getPendingReturnsCount()`, and `getAllStats()`.
- [done] Refactor `ShippingDashboardWidget` to consume the aggregator service via `app(ShippingStatsAggregator::class)`.
- [done] Aggregator is Filament-agnostic and can be upstreamed to `shipping` domain if reusable beyond Filament.



## Suggested verification scope

- per-Resource tests
- per-Action tests
- Widget tests
- cross-package tests for shipping/cart/checkout/orders/jnt

## Recommended first move

Phase 1 — collapse bulk+singles Actions. The duplication is the most visible structural smell and the cleanup is mechanical.

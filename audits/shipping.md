# Shipping Audit

## Implementation outcome (migration track, 2026-09-07)

The proposed `shipping_rates` owner-column migration was corrected away. Internal rate reads are reached through owner-scoped `ShippingZone` queries/relations, and duplicating the owner tuple on rates would add synchronization burden without a current consumer need.

## Packages Reviewed
- `aiarmada/shipping` (`packages/shipping`): `src/` (Actions, Cart, Contracts, Data, Drivers, Enums, Events, Exceptions, Facades, Http/Controllers, Integrations, Models, Policies, Services, States, Strategies, Support), `config/shipping.php`, `database/migrations/` (8 files), `composer.json`, `src/ShippingServiceProvider.php`, `src/ShippingManager.php`, `CONTEXT.md`/`README.md`/`docs/`
- `aiarmada/filament-shipping` (`packages/filament-shipping`): `src/` (Actions, Pages, Resources, Support, Widgets), `config/filament-shipping.php`, `composer.json`, `CONTEXT.md`/`README.md`/`docs/`
- No `tests/` directory exists in either package (verified `ls packages/shipping/tests`, `ls packages/filament-shipping/tests` → no such file).

## Overall Assessment
- Quality: Best-structured of the six units. Clean Manager + Strategy + Action split, `spatie/laravel-data` DTOs, explicit driver contracts, Octane-aware comment in `ShippingManager::driver()` (zone driver not cached).
- Health: Good. No FK-constraint violations, uuid PKs everywhere, Filament nav compliant, money in cents.
- Risks: Dual status system (Enum + spatie State classes) is the biggest confusion source; hand-rolled owner scoping in `ShippingZoneResolver::applyOwnerScope()` bypasses the global `OwnerScope`; `ShippingRate` relies on through-zone owner scoping with no direct owner columns (fragile, not leaking — see A-4); zero tests.
- Refactor size: Medium. ~8–12 files. One narrow additive migration (`shipping_rates` owner columns).

## Migration Impact
**Migration Required: YES**
One additive, nullable-column migration only (A-4): `nullableUuidMorphs('owner')` on `shipping_rates`. No renames, no constraints, no data migration.

| Table | Change | Detail |
|---|---|---|
| `shipping_rates` | add `owner_type`/`owner_id` (nullable morphs) | backfill from parent zone's owner on deploy; plain indexes only, no FK constraints (rule-compliant) |
| all other shipping tables | none | uuid PKs kept; no FK constraints to add/remove (rule-compliant) |
| `shipment_operations.shipment_id` | none (code-only fix) | stays plain `uuid->index()`; add app-level existence validation instead of DB FK |
| `shipments.ulid` | none (recommended follow-up only) | keep unique `ulid` column; do not drop without consumer audit |

## Package Responsibilities
- Owns: multi-carrier abstraction (`ShippingDriverInterface`, `ShippingManager`), zone/rate domain (`ShippingZone`, `ShippingRate`), shipment lifecycle (`Shipment`, `ShipmentItem`, `ShipmentEvent`, `ShipmentLabel`, `ShipmentOperation`), returns (`ReturnAuthorization`), rate shopping (`RateShoppingEngine`, strategies), free-shipping policy, tracking aggregation, cart bridge (`Cart/CartBridge.php`, `ShippingCondition`), order-fulfillment hook (`Integrations/OrderFulfillmentHandler.php`).
- Filament adapter owns: shipment/zone/rate/return resources, fulfillment queue + manifest + dashboard pages, ship/cancel/label/tracking actions, stats widgets. Correctly thin (delegates to Actions/Services, no domain rules duplicated).

## Architecture Findings
### A-1 Dual shipment-status systems (Enum vs State machine)
- Severity: High
- Location: `packages/shipping/src/Enums/ShipmentStatus.php`, `packages/shipping/src/States/ShipmentStatus.php` + 12 state classes (`Draft`, `Pending`, `Shipped`, `InTransit`, `OutForDelivery`, `Delivered`, `Cancelled`, `DeliveryFailed`, `ExceptionStatus`, `OnHold`, `AwaitingPickup`, `ReturnToSender`), `packages/shipping/src/Services/ShipmentService.php::updateStatus()`, `Actions/UpdateShipmentStatus.php`
- Problem: Two parallel representations of the same lifecycle. `ShipmentService::updateStatus()` accepts `ShipmentStatusState | ShipmentStatusEnum | string`, so every caller can pass a different type and every reader must normalize. Same duplication exists for returns (`States/ReturnAuthorizationState/*` + `Enums/ReturnReason.php` is fine, but RMA has 9 state classes for a 6-state flow).
- Why It Matters: Status comparisons scatter (`$shipment->status->equals(Draft::class)` in `ShipmentService::markPending()` vs enum comparisons elsewhere); new statuses must be added twice; Filament tables/forms pick one representation and drift from the other.
- Recommended Fix: Keep spatie states as the single source of truth; delete `Enums/ShipmentStatus.php` and migrate all callers to state classes. Same for RMA states (collapse to one set). No deprecated alias (no shims).
- Breaking Change: YES (method signatures narrow to state classes)
- Affected Packages: `shipping`, `filament-shipping`, `checkout` (`Steps/CalculateShippingStep.php`, `Integrations/ShippingAdapter.php`), `jnt` (`JntShippingDriver.php`, `JntStatusMapper.php`), `orders` (`Contracts/FulfillmentHandler.php` consumers)
- Required Dependent Changes: Update all `ShipmentStatus::` enum usages to state classes; update Filament table badge mappings.
- Migration Required: NO

### A-2 `ShipmentService` is a pass-through facade with one escape hatch
- Severity: Low
- Location: `packages/shipping/src/Services/ShipmentService.php` (all methods delegate to Actions except `recalculateWeight()`)
- Problem: `recalculateWeight()` does `$shipment->items->sum(...)` + direct `$shipment->update()` instead of going through an Action, so weight recalculation skips events/validation that every other mutation gets.
- Why It Matters: Inconsistent write path; future hooks (activity log, order sync) added to Actions will miss weight changes.
- Recommended Fix: Extract `RecalculateShipmentWeight` Action and delegate; keep `ShipmentService` as the single entry point.
- Breaking Change: NO
- Affected Packages: `shipping`
- Required Dependent Changes: None (internal).
- Migration Required: NO

### A-3 Hand-rolled owner scoping bypasses global `OwnerScope`
- Severity: High
- Location: `packages/shipping/src/Services/ShippingZoneResolver.php::applyOwnerScope()`, `::resolve()`, `::resolveAll()`, `::performZoneResolution()`
- Problem: Builds raw `where('owner_id', ...)` clauses and returns the query unscoped when no explicit owner is passed and ambient context is absent, instead of using `OwnerScope` / `forOwner()` / `OwnerContext::resolve()`. When `shipping.features.owner.enabled=false` (default) but caller passes owner params, it silently filters by owner columns that are otherwise ignored — inconsistent semantics. Request-lifetime `$resolvedZones` cache is keyed by address+owner but never invalidated on zone/rate writes.
- Why It Matters: Tenant isolation depends on every caller remembering to pass `$ownerId/$ownerType`; Filament, checkout, and queue paths each do it differently. Stale cache can serve the wrong zone after admin edits.
- Recommended Fix: Replace `applyOwnerScope()` with `OwnerQuery`/model `forOwner()` scopes driven by `OwnerContext::resolve()`; add `clearCache()` call (or model observer) on `ShippingZone`/`ShippingRate` saved/deleted.
- Breaking Change: NO (behavioral fix; method signatures keep optional owner params as overrides)
- Affected Packages: `shipping`, `filament-shipping`, `checkout`
- Required Dependent Changes: `checkout/Integrations/ShippingAdapter.php` and `Steps/CalculateShippingStep.php` must run inside session owner context (they mostly do via `CheckoutService::withSessionOwnerContext()` — verify).
- Migration Required: NO

### A-4 `ShippingRate` has no direct owner columns (through-zone scoping only)
- Severity: Medium
- Location: `packages/shipping/src/Models/ShippingRate.php` (no `HasOwner`), `packages/filament-shipping/src/Resources/ShippingRateResource.php:41-58`, `packages/shipping/src/Services/ShippingZoneResolver.php::getApplicableRates()`
- Problem: `Shipment`, `ShippingZone`, `ReturnAuthorization` use `HasOwner`; `ShippingRate`, `ShipmentItem`, `ShipmentEvent`, `ShipmentLabel`, `ShipmentOperation`, `ReturnAuthorizationItem` do not. Re-check outcome: the audit's original "calls `forOwner()` on the rate query → runtime error" claim was overstated and is corrected here. Verified `ShippingRateResource::getEloquentQuery()` scopes via `whereHas('zone', fn ($q) => $q->forOwner(...))` — the inner query is over `ShippingZone`, which HAS the scope, so this works at runtime (the `@phpstan-ignore` is a generic-type inference suppression, not an admission of failure). Rates are therefore implicitly owner-scoped through their zone. The residual gap is fragility, not leakage: any future query that loads rates directly (`ShippingRate::query()`, `getApplicableRates()` post-zone-load) bypasses owner checks silently, and there is no schema-level owner tuple to filter on.
- Why It Matters: Implicit scoping holds only while every read path goes through the zone; one direct rates query leaks cross-tenant rates in multi-tenant mode.
- Recommended Fix: Add `HasOwner` + `HasOwnerScopeConfig` to `ShippingRate` with `nullableUuidMorphs('owner')` backfill (additive migration), defaulting `owner` from the parent zone on create; keep the `whereHas('zone')` Filament scope as defense in depth until backfilled.
- Breaking Change: NO (additive)
- Affected Packages: `shipping`, `filament-shipping`
- Required Dependent Changes: Filament `ShippingRateResource::getEloquentQuery()` keeps its scope; `ShippingZoneResolver::getApplicableRates()` filters rates by owner once columns exist.
- Migration Required: YES — narrowly scoped: add `nullableUuidMorphs('owner')` to `shipping_rates` only. No other table changes.

### A-5 N+1 application-level cascades
- Severity: Medium
- Location: `packages/shipping/src/Models/Shipment.php::booted()` (deletes items/events/labels/operations via `->each(fn => ->delete())`), `packages/shipping/src/Models/ShippingZone.php::booted()` (`$zone->rates()->delete()` — correct query delete), `packages/shipping/src/Models/ReturnAuthorization.php::booted()` (`$rma->returnShipment?->delete()` + `$rma->items()->delete()`)
- Problem: `Shipment` cascade hydrates every child model to fire events, while `ShippingZone` uses a single query delete — inconsistent, and shipment delete on a large shipment is N+1. RMA deleting its return shipment inverts the expected direction (deleting the authorization destroys the shipment record — audit-trail loss).
- Why It Matters: Slow deletes; surprising data loss on RMA delete.
- Recommended Fix: Use query deletes (`->delete()` on relations) for items/events/labels/operations; change RMA delete to null the link / block when a return shipment exists instead of deleting it.
- Breaking Change: YES (RMA delete no longer destroys shipments)
- Affected Packages: `shipping`, `filament-shipping`
- Required Dependent Changes: Filament delete confirmations copy change.
- Migration Required: NO

## Code Quality Findings
### C-1 `getDriversForDestination()` resolves every driver to test one address
- Severity: Medium
- Location: `packages/shipping/src/ShippingManager.php::getDriversForDestination()`, `packages/shipping/src/Services/RateShoppingEngine.php`
- Problem: Maps over all configured + custom drivers and instantiates each (except `zone`, deliberately uncached for Octane) just to filter by `servicesDestination()`.
- Why It Matters: Carrier drivers doing API setup on construction make this expensive per quote.
- Recommended Fix: Add `ShippingDriverInterface::servicesDestination()` pre-check via lightweight metadata or cache driver instances per request; memoize `hasDriver()` results.
- Breaking Change: NO
- Affected Packages: `shipping`
- Required Dependent Changes: None.
- Migration Required: NO

### C-2 Mutable `$resolvedZones` request cache on a container singleton
- Severity: Medium
- Location: `packages/shipping/src/Services/ShippingZoneResolver.php::$resolvedZones`
- Problem: Property-level cache is safe only if the service is request-scoped. If bound as singleton under Octane, one request's zones leak into the next (cache key includes owner but not zone-updated-at).
- Why It Matters: Long-lived workers serve stale/wrong zones.
- Recommended Fix: Bind `ShippingZoneResolver` as scoped, or store cache in request context / flush on Octane `RequestReceived` (same pattern `cashier` already uses for `Cashier::restoreOctaneDefaults()`).
- Breaking Change: NO
- Affected Packages: `shipping`
- Required Dependent Changes: `ShippingServiceProvider` binding change only.
- Migration Required: NO

### C-3 `setDefaultDriver()` mutates global config at runtime
- Severity: Low
- Location: `packages/shipping/src/ShippingManager.php::setDefaultDriver()`
- Problem: Writes to `config('shipping.drivers.default')` globally — under Octane this leaks across requests.
- Why It Matters: One tenant/request can change the driver for all subsequent requests.
- Recommended Fix: Remove it or scope it to the current manager instance (instance property overriding `getDefaultDriver()`).
- Breaking Change: YES (remove method)
- Affected Packages: `shipping`, `jnt`, `checkout`
- Required Dependent Changes: Grep for `setDefaultDriver` consumers (only internal + tests, if any) and replace with per-instance resolution.
- Migration Required: NO

## Laravel-Specific Findings
- L-1 (Low): `commerce_schema_create_if_missing()` + `down()` using `Schema::dropIfExists()` is consistent across shipping migrations — fine, but `down()` on `shipments` drops a table that `shipment_operations` references by bare `shipment_id`; acceptable only because there are no DB FKs (rule-compliant). Keep app-level guard.
- L-2 (Low): `BatchRateLimiter`, `RetryService`, `TrackingAggregator` duplicate retry/backoff logic that `commerce-support` already centralizes — consolidate on the shared primitive when touching these files.
- L-3 (Info, compliant): PHP `^8.4` in both composer files; `CarbonImmutable` used in `TrackingAggregator`, `ApproveReturnAuthorization`; no `SoftDeletes` (rule-compliant); `getTable()` from config in all models checked.

## Filament Adapter Findings
- Thin-adapter check: PASS. Resources delegate to Actions (`ShipAction`, `CancelShipmentAction`, `SyncTrackingAction`, `PrintLabelAction`, `ApproveReturnAction`, `RejectReturnAction`); pages use `ShippingStatsAggregator` + `forOwner()` queries; no pricing/zone math duplicated in UI.
- Domain leak: One — `FulfillmentQueue.php` and `ManifestPage.php` build fulfillment status logic inline (urgent/old thresholds from `filament-shipping.fulfillment.*`) instead of calling a domain service. Move thresholds into `shipping` config or a `FulfillmentPolicy` class.
- Duplication: None significant within the adapter.
- Dependency direction: Correct (`filament-shipping` → `shipping` + `commerce-support`; `shipping` has no Filament dependency).
- Navigation: COMPLIANT. Nested `navigation.group` + `navigation.sort` in `config/filament-shipping.php`; every Resource/Page uses `getNavigationGroup()` reading config; no `static $navigationGroup`; no flat `navigation_group` key (both greps verified).

## Database Findings
- D-1 (Compliant): All 8 migrations use `uuid('id')->primary()`; FK columns use `foreignUuid`/`nullableUuidMorphs` with NO `constrained()`/`cascadeOnDelete()` (repo-wide grep for `constrained(|cascadeOnDelete(|->references(|onDelete(` across shipping/tax/checkout/cashier-chip/chip returns empty). App-level cascades in `booted()` — correct per rules.
- D-2 (Medium): `shipment_operations.shipment_id` is bare `uuid->index()` with no app-level existence check in `ReconcileShipmentOperation`/`ShipShipment`/`CancelShipment` — orphan operations possible. Add `exists:shipments,id`-style validation in the Actions.
- D-3 (Low): `shipments` carries both uuid PK and unique `ulid` — two identities for one row. Keep (no migration), but standardize new code on the uuid PK and document `ulid` as external/carrier-facing only.
- D-4 (Low): `shipping_rates` missing owner columns (see A-4). `shipping_zones`/`shipments`/`return_authorizations` correctly have `nullableUuidMorphs('owner')`.
- D-5 (Info): `json_column_type` configurable (`shipping.database.json_column_type`, default `jsonb`) — compliant.

## Model / Domain Findings
- M-1: `Shipment::status` cast + `ShipmentService::markPending()` guard (`Draft` → `Pending` only) is correct lifecycle discipline; extend the same guard to all transitions (currently only `markPending` checks, `updateStatus` delegates to `UpdateShipmentStatus` — verify it centralizes the transition matrix).
- M-2: `FreeShippingEvaluator` + `ThresholdFreeShippingPolicy` + `FreeShippingPolicyRegistry` is three layers for one threshold check — collapse to policy + evaluator.
- M-3: Money handling is correct: `shipping_cost`, `insurance_cost`, `declared_value`, driver `default_rate`/`rate` all integer minor units; no `number_format` money rendering in `src/` (grep clean).

## Security Findings
- S-1 (Low): `Http/Controllers/LabelController.php` — re-checked: there is NO shipment route-model binding to exploit. The controller serves cached label data by opaque `token`, then enforces auth-user match (`labelData.user_id`) plus owner-tuple match (`matchesOwner()` vs `OwnerContext::resolve()`, 403 otherwise). No `ShipmentPolicy` involvement. Residual hardening only: confirm token entropy/TTL and cache eviction, not a scoping hole.
- S-2 (Low): Tracking sync (`TrackingAggregator`) uses `forOwner($owner, includeGlobal: $owner === null)` — passing `includeGlobal=true` when owner is null silently widens scope in console runs; prefer explicit `OwnerContext::withOwner(null, ...)` for the global pass.
- S-3 (Info): No webhook surface in shipping — no signature/idempotency exposure. Secrets are deploy-time env (`SHIPPING_API_*`, thresholds) — compliant.

## Performance Findings
- P-1: Shipment delete cascade N+1 (see A-5).
- P-2: `RateShoppingEngine` cache TTL 300s keyed via `OwnerScopeKey` — good; verify cache stampede protection when many checkouts quote simultaneously.
- P-3: `TrackingAggregator` chunking + `last_tracking_sync` index — good; keep `max_tracking_age` pruning.

## Testing Findings
- T-1 (Medium): Zero tests in both packages. No Pest/PHPUnit files, no factories. Demoted from High per severity rubric (testability gaps are Medium, not incorrect behavior). The highest-risk logic (zone matching `GeoZoneResolutionStrategy`, `RateShoppingEngine` cheapest/fastest/preferred, `FreeShippingEvaluator`, status transitions, owner scoping) is untested.
- Recommended: Add per-package Pest suite (parallel): zone-resolution dataset (country/state/postcode/default priority), rate-shopping strategy tests, `CreateShipment`/`ShipShipment`/`CancelShipment` transition tests, cross-tenant isolation test reusing `commerce-support` `OwnerScopingContractTests`, Filament `getEloquentQuery` scoping tests.

## Cross-Package Dependency Impact
| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `checkout` (`Integrations/ShippingAdapter.php`, `Steps/CalculateShippingStep.php`) | `ShippingManager`, `CalculateShippingRate`, `AddressData/PackageData/RateQuoteData` | Enum→state rename breaks step + adapter | Update to state classes; run inside session owner context |
| `jnt` (`Shipping/JntShippingDriver.php`, `Services/JntStatusMapper.php`) | `extend()` driver registration, `StatusMapperInterface` | Manager/driver contract changes affect J&T driver | Keep `ShippingDriverInterface` stable; coordinate `setDefaultDriver` removal |
| `orders` (`Contracts/FulfillmentHandler.php`, `filament-orders ViewOrder`) | `Integrations/OrderFulfillmentHandler.php` | RMA-delete semantics change | Stop relying on RMA delete destroying shipments |
| `cart` (`CartBridge`, `ShippingCondition`) | cart conditions | Minor | None unless condition contract changes |

## Recommended Refactor Plan
1. Replace `ShippingZoneResolver::applyOwnerScope()` with `OwnerScope`/`forOwner()` + cache invalidation (A-3, C-2: scope binding).
2. Add direct owner columns to `ShippingRate` (A-4: single additive migration).
3. Collapse dual status systems to spatie states (A-1).
4. Fix cascades: query deletes + stop RMA→shipment destruction (A-5).
5. Extract `RecalculateShipmentWeight` Action; remove/global-scope-fix `setDefaultDriver()` (A-2, C-3).
6. Move fulfillment thresholds into domain; add Pest suites + cross-tenant tests (T-1).

## Files Likely to Change
- `packages/shipping/src/Models/ShippingRate.php`
- `packages/shipping/src/Services/ShippingZoneResolver.php`
- `packages/shipping/src/ShippingServiceProvider.php` (scoped binding)
- `packages/shipping/src/ShippingManager.php`
- `packages/shipping/src/Enums/ShipmentStatus.php` (remove after migrating callers to `States/` — verified dual definition via `ls States/` + `Enums/` and `class ShipmentStatus` in both; no deprecated alias).
- `packages/shipping/src/Services/ShipmentService.php` (+ new `Actions/RecalculateShipmentWeight.php`)
- `packages/shipping/src/Models/Shipment.php`, `Models/ReturnAuthorization.php`
- `packages/shipping/src/Services/FreeShippingEvaluator.php`, `Strategies/ThresholdFreeShippingPolicy.php`
- `packages/filament-shipping/src/Resources/ShippingRateResource.php`, `Resources/ShipmentResource.php`, `Resources/ShippingZoneResource.php`, `Resources/ReturnAuthorizationResource.php`, `Pages/FulfillmentQueue.php`, `Pages/ManifestPage.php`
- `packages/checkout/src/Integrations/ShippingAdapter.php`, `Steps/CalculateShippingStep.php`
- `packages/jnt/src/Shipping/JntShippingDriver.php`

## Files / Code That Should Be Removed
- `packages/shipping/src/Enums/ShipmentStatus.php` (after migrating callers to `States/` — verified dual definition via `ls States/` + `Enums/` and `class ShipmentStatus` in both).
- `packages/shipping/src/ShippingManager.php::setDefaultDriver()` (global config mutation; no external callers found outside the manager — replace with instance override).
- `packages/shipping/src/Services/FreeShippingResult.php` if collapsed into the policy return (tiny DTO; remove only if the collapse in M-2 is taken).
- No migration files removed. No legacy shims preserved.
- NOTE: `Drivers/NullShippingDriver.php` is test-support — keep (do not list as dead; it is the documented testing driver).

## Final Recommended Architecture
Keep the current shape: `shipping` owns drivers, zones/rates, shipment + RMA lifecycles, and cart/order hooks; `filament-shipping` stays a thin resource/page/action layer over Actions with `forOwner()` queries. Single spatie-state lifecycle, owner scoping via `commerce-support` primitives only, rates owner-bound like zones, query-based cascades, request-scoped resolver binding, and a real Pest suite. No new packages, no new abstractions.

# Shipping Audit — DONE (2026-09-11)

## Verdict

The `shipping` + `filament-shipping` pair is cleared. Every rated finding is
implemented, falsified with source evidence, or recorded as an explicit
deferral. The state, owner-scoping, driver-resolution, lifecycle, and delete
paths are covered by the reviewed implementation and Area suites. No
migration is required; the migration-track decision remains recorded in
[`migration-record.md`](migration-record.md).

## What was done

- **A-1 / A-2 — lifecycle and facade:** the Spatie state machine is the sole
  shipment-status authority (`packages/shipping/src/States/ShipmentStatus.php:16,68-96`);
  the enum callers were removed, and `Shipment` casts `status` to the state
  class (`packages/shipping/src/Models/Shipment.php:267-277`).
  `ShipmentService::recalculateWeight()` now delegates to the dedicated
  action (`packages/shipping/src/Services/ShipmentService.php:66-68`).
- **A-3 / C-2 — owner scope and request lifetime:** zone resolution uses
  `forOwner()` with explicit `include_global` behavior and validates owner
  tuples (`packages/shipping/src/Services/ShippingZoneResolver.php:172-232`).
  The manager and resolver are scoped bindings, and zone/rate writes clear
  resolver cache state (`packages/shipping/src/ShippingServiceProvider.php:41-76`,
  `packages/shipping/src/Models/ShippingZone.php:106-124`). Cross-tenant
  isolation is pinned by the resolver and owner-consolidation tests.
- **A-5 / D-2 / P-1 — integrity and deletes:** shipment children are deleted
  in 500-row chunks inside a transaction
  (`packages/shipping/src/Models/Shipment.php:249-264`); a return
  authorization with a return shipment is blocked from deletion
  (`packages/shipping/src/Models/ReturnAuthorization.php:208-215`).
  `ShipmentOperation::recordStart()` rejects an unpersisted shipment and
  reconciliation handles missing relations (`packages/shipping/src/Models/ShipmentOperation.php:52-74`).
- **C-1 / C-3 — driver resolution:** carrier quote paths enumerate driver
  names, resolve one driver in each task, and test destination support before
  calling it (`packages/shipping/src/Services/RateShoppingEngine.php:173-232`).
  The manager cache is request-scoped (`packages/shipping/src/ShippingManager.php:44-57`),
  and `setDefaultDriver()` is an instance runtime override rather than a
  global config mutation (`packages/shipping/src/ShippingManager.php:91-103`).
- **M-1 / S-2 — state and owner semantics:** tracking updates are transactional
  and transition the canonical state (`packages/shipping/src/Actions/RecordTrackingEvent.php:36-95`);
  tracking aggregation uses configured owner inclusion rather than a null-owner
  bypass (`packages/shipping/src/Services/TrackingAggregator.php:122-145`).
- **L-1 / L-3 / D-1 / D-3 / D-4 / D-5 / M-3 / S-3 / P-3 — compliant or
  documented findings:** PHP 8.4, immutable lifecycle dates, configured table
  names, UUID keys, no database cascades/foreign-key constraints, configurable
  JSON columns, integer minor-unit money, and the UUID-internal/ULID-external
  identity split are retained and documented. The latter is explicit in
  `packages/shipping/docs/04-usage.md:525-532`.
- **L-2 / M-2 / S-1 / T-1 — re-derived findings:** the alleged shared retry
  primitive was not present, so L-2 was dropped as falsified; the free-shipping
  registry is a real extension seam, so M-2 was dropped as a false positive
  (`packages/shipping/src/Support/FreeShippingPolicyRegistry.php:10-44`).
  Label access checks the token, tracking number, authenticated user, and
  owner tuple (`packages/shipping/src/Http/Controllers/LabelController.php:16-49`),
  so S-1 was not a scoping hole. The original zero-test premise is no longer
  true because the root Shipping and FilamentShipping Area suites now cover
  the high-risk paths.

## Audit deviations

- **P-2 remains an explicit deferral:** “`RateShoppingEngine` cache TTL 300s
  keyed via `OwnerScopeKey` — good; verify cache stampede protection when many
  checkouts quote simultaneously.” The owner-keyed cache and invalidation are
  implemented; stampede behavior is not claimed here.
- **S-1 residual hardening remains explicit:** “confirm token entropy/TTL and
  cache eviction, not a scoping hole.” The label endpoint's authenticated,
  tracking-number, and owner-tuple checks are implemented; token lifecycle
  hardening is not claimed here.
- The adapter's previously noted fulfillment-threshold placement was not a
  rated finding and was not promoted to a new closure item.
- No source outside the shipping pair was changed by this closure. The
  `cart`, `checkout`, `orders`, and `jnt` surfaces remain read-only
  dependencies for integration verification.

## Residual notes

No migration or compatibility shim is required. Revisit the deferred cache
stampede proof and label token entropy/TTL/cache-eviction hardening when their
operational risk makes them material.

## Verification

- Shipping Area: **530 passed, 1 skipped, 1,328 assertions**.
- FilamentShipping Area: **103 passed, 231 assertions**.
- All Pest commands used `--parallel`; no full-suite run was performed.

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.

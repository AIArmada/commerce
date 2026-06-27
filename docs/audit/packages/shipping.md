# Audit: `shipping` (AIArmada\Shipping)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Carrier-agnostic shipping abstraction, shipments, zones, rates, and returns.

**Surface:** domain

---

## Findings

### Low
1. **Event listeners stub empty** — `registerEventListeners()` wired but empty. Events fire, consumers must wire listeners.
2. **Commands stub empty** — `registerCommands()` wired but empty.
3. **Factory namespace declared, no files** — `AIArmada\Shipping\Database\Factories\` in autoload but directory absent.
4. **Owner scoping disabled by default** — `shipping.features.owner.enabled = false`. Documented with warning.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ `$fillable` | All 8 models use explicit `$fillable` |
| Money storage | ✅ Integer minor units | All monetary columns as integers |
| Owner scoping | ✅ Full | Shipment, ShippingZone, ReturnAuthorization have `HasOwner`; children scoped via parent |
| Migrations | ✅ Clean | No `constrained()`, no `cascadeOnDelete()` across 7 migrations |
| Exception hierarchy | ✅ 4 classes | `InvalidStatusTransitionException`, `ShipmentCreationFailedException`, `ShipmentAlreadyShippedException`, `ShipmentNotCancellableException` |
| State machines | ✅ 2 workflows | 11-state Shipment + 8-state ReturnAuthorization via spatie/laravel-model-states |
| Actions | ✅ 9 classes | Create, Ship, Cancel, UpdateStatus, GenerateLabel, RecordTracking, CalculateRate, Approve/Reject RMA |
| Events | ✅ 6 events | Created, Shipped, Delivered, Cancelled, StatusChanged, TrackingUpdated |
| Contracts | ✅ 6 interfaces | ShippingDriverInterface, FreeShippingPolicyInterface, RateSelectionStrategyInterface, ZoneResolutionStrategyInterface, StatusMapperInterface, AddressValidationResult |
| Drivers | ✅ 4 drivers | Manual, FlatRate, ZoneBased, Null (test) |
| Tests | ✅ 18 files | Actions, Models, Drivers, Services, Data, Events, Exceptions |
| Docs | ✅ 7 files | Full standard set + custom drivers + multitenancy |

---

## Summary

Feature-rich shipping package: 8 models (Shipment, ShipmentItem, ShipmentEvent, ShipmentLabel, ShippingZone, ShippingRate, ReturnAuthorization, ReturnAuthorizationItem), 4 enums, 9 actions, 6 events, 6 contracts, 4 drivers, 7 services, 6 strategies, 10 DTOs, 3 policies, 2 state machine workflows (11 + 8 states), 4 custom exceptions. Carrier-agnostic driver pattern with manual/flat-rate/zone-based implementations. Rate shopping engine with concurrent fetching, caching, strategy selection, and fallback. Free shipping evaluator with pluggable policies. Zone resolver with country/state/postcode/radius/Haversine matching.

Money as integer minor units throughout. Owner scoping on 3 top-level models, children scoped via parent. Exception hierarchy present. All 7 migrations clean. 18 test files. 7 docs files.

**Verdict:** Ready. Most comprehensive package audited so far. Exception hierarchy, state machines, clean migrations, strong architecture.

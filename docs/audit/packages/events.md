# Audit: `events` (AIArmada\Events)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Event definitions, scheduling, venues, registrations, ticketing, check-in, attendance, change workflows.

**Surface:** domain

---

## Findings

### Low
1. **No in-package tests** — 42 Pest test files exist in monorepo `tests/src/Events/` (excellent coverage) but none inside the package. Package won't self-test on standalone install.
2. **Some models lack `HasOwner`** — Only 7 of 54 models (`Event`, `EventItinerary`, `EventSeries`, `EventTemplate`, `Organization`, `EventWalkIn`, `EventHeadcountLog`) use `HasOwner`/`HasOwnerScopeConfig` directly. The remaining 47 rely on `EventOwnerScope`/`EventSubmissionOwnerScope`/`PolymorphicOwnerScope` global scope registration in the service provider, which is valid but harder to reason about.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ None | All 54 models use `$fillable` |
| Exception hierarchy | ✅ 14 custom exceptions | `EventsException` base + subtypes: `InvalidEventException`, `RegistrationException`, `CapacityExceededException`, `PassException`, `SeatException`, `EventScheduleException`, `EventSubmissionException`, `EventNotificationException`, `EventCheckInException`, `EventChangeException`, `EventVerificationException`, `EventModerationException`, `OrganizationException`, `EventNotFoundException` |
| PHP enums | ✅ 7 enums | `BundleInclusionMode`, `EventVisibility`, `LifecycleActionType`, `OccurrenceTimeMode`, `PricingMode`, `RegistrationMode`, `OpenDoorMode` |
| State machines | ✅ 4 families, 30+ states | Spatie `HasStates` — EventStatus (12 states), EventModerationStatus (6), OccurrenceStatus (10), RegistrationStatus (11) |
| Contracts | ✅ 48 interfaces | Strong abstraction layer |
| Events | ✅ 52 event classes | Full lifecycle coverage per domain entity |
| Actions | ✅ 38 action classes | Strong action pattern usage |
| Tests | ✅ 42 Pest files | Cover creation, lifecycle, ticketing, check-in, search, cross-tenant isolation, observers |
| Owner scoping | ✅ Global scope registration | `EventOwnerScope`, `EventWriteGuard`, `EventTenantBoundary` |
| Money storage | ✅ Integer minor units | Migration 080 converts all money columns to bigint |
| Immutable dates | ✅ `CarbonImmutable` | All datetime casts use `immutable_datetime` |
| DB constraints | ✅ None | No `constrained()` or `cascadeOnDelete()` |
| Resolvers | ✅ 12 config-driven | Timezone, classification, reference, schedule, search, change notices, checkout intent |
| Docs | ✅ 5 standard + 14 dev docs | Comprehensive documentation |
| Octane safety | ✅ No static mutable state | Services bound as singletons, no request leak |
| Size | ⚠️ 54 models, 81 migrations | Largest package in repo — 4x the next largest |

---

## Summary

The largest and most complex package in the monorepo: 54 models, 81 migrations, 38 actions, 48 contracts, 52 events, 14 exceptions, 7 enums, 4 state machine families (30+ states), 12 resolvers, 8 observers, 13 services, 42 tests. Full event lifecycle management — venues, scheduling, ticketing, registration, check-in, passes, seating, attendance, change workflows, submissions, approvals, search, notifications, and commerce integration.

Architecturally the most mature package: custom exception hierarchy (14 classes), PHP enums, Spatie model states, config-driven resolver binding, dedicated write guards (`EventWriteGuard`) and owner scopes (`EventOwnerScope`, `EventTenantBoundary`), money stored as integer minor units, application-level cascade delete enforcement, comprehensive event coverage for every lifecycle transition, and strong action pattern usage as per the package's own CONTEXT.md guardrails.

The 42 test files cover cross-tenant isolation, policy enforcement, lifecycle workflows, check-in, registration, search, observers, session management, and ticket type pricing. No blocked paths in the source code — all resolvers have null/no-op defaults for missing integrations.

**Verdict:** Ready. Best engineered package in the repo — exception hierarchy, enums, states, contracts, events, tests, strong security patterns. No blockers.

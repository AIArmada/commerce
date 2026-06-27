# Audit: `growth` (AIArmada\Growth)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Experimentation primitives, A/B testing, sticky assignments, winner metrics, signal enrichment.

**Surface:** analytics

---

## Findings

### Medium
1. **Owner columns in `$fillable`** — All 3 models (`Experiment`, `Variant`, `Assignment`) include `owner_type` and `owner_id` in `$fillable`. Every other package in the repo excludes owner columns from `$fillable`, relying on `HasOwner` trait for assignment. Having them fillable allows mass-assignment override of owner scoping unless `HasOwner::bootHasOwner()` always overwrites.

### Low
2. **No exception hierarchy** — 6 actions, 4 enums, but zero custom exceptions. Uses `InvalidArgumentException`, `RuntimeException`, `AuthorizationException` directly.
3. **No in-package tests** — 20 Pest test files in monorepo `tests/src/Growth/` but none inside the package.
4. **No routes** — Middleware is registered as an alias only; no route files in package.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ Not set (defaults to `['*']`) | All 3 models omit `$guarded` — safer default, `$fillable` controls mass assignment |
| Owner scoping | ✅ `HasOwner` + `HasOwnerScopeConfig` | All 3 models |
| Immutable dates | ✅ `CarbonImmutable` | All datetime casts |
| PHP enums | ✅ 4 enums | `ExperimentStatus`, `ExperimentModuleType`, `VariantStatus`, `ResolveStrategy` — all with `label()`/`color()` |
| Actions | ✅ 6 classes | Assignment, metrics, signal projection, preset resolution |
| Tests | ✅ 20 Pest files | Cross-tenant isolation, owner scoping contracts, assignment resolution, middleware, metrics aggregation, signal integration |
| Contracts | ✅ 1 interface | `RequestExperimentSubjectResolver` |
| Facade | ✅ `Growth` facade | Proxies `ExperimentContextManager` |
| Blade helper | ✅ `@variant` directive | Gated behind config flag |
| Livewire concern | ✅ `InteractsWithExperimentContext` | For Livewire components |
| HTTP middleware | ✅ `ResolveExperiment` | `growth.experiment` alias |
| `booted()` cascades | ✅ On all 3 models | `deleting` deletes children |
| Owner in fillable | ⚠️ Violates repo convention | See Medium finding 1 |

---

## Summary

Small, focused experimentation package: 3 models (Experiment, Variant, Assignment), 4 enums, 6 actions, 1 contract, 20 tests. Handles A/B testing lifecycle — preset configurations (A/B test, sales page, funnel, pricing), sticky assignment via crc32 hashing with weighted bucket distribution, signal event property enrichment, winner metric aggregation.

Well-integrated with `signals` package for event recording and metric computation. Provides middleware (`growth.experiment`), Blade directive (`@variant`), Livewire concern, global `experiment()` helper, and `Growth` facade for runtime experiment context access.

Owner columns in `$fillable` deviates from the repo convention. All 3 models have proper `booted()` cascades (deleting children on parent delete), immutable datetime casts, and enum-based status fields. 20 test files cover owner scoping, cross-tenant isolation, assignment resolution, middleware, metrics, and signal integration.

**Verdict:** Ready. Clean, focused, well-tested. Minor fillable convention deviation.

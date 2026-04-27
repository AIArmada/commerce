---
title: Tenancy Strategy Evaluation Report
---

# Tenancy Strategy Evaluation Report

**Repository:** `AIArmada/commerce`  
**Date:** 2026-04-28  
**Scope:** Evaluate current `commerce-support` tenancy architecture vs `stancl/tenancy` (ArchTech) for long-term suitability.

---

## Executive Summary

For this monorepo and package-first architecture, **`commerce-support` is currently the better core tenancy foundation**.

For full SaaS application tenancy (tenant domains, tenant lifecycle automation, multi-database isolation, provisioning pipelines), **`stancl/tenancy` is stronger out-of-the-box**.

### Strategic conclusion

- **Do not replace** `commerce-support` with `stancl/tenancy` at package core.
- **Do add an optional integration bridge** for host apps that require full SaaS tenancy capabilities.

---

## Research Method

### Internal codebase investigation

- `packages/commerce-support` core primitives
- Representative downstream package implementations (`products`, `customers`, `pricing`, `tax`, `vouchers`, `cart`, `orders`, `shipping`, `filament-*`)
- Tests in `tests/src` and selected `demo/tests`
- Query safety and escape-hatch usage (`DB::table`, `withoutOwnerScope`)

### External package investigation

- `archtechx/tenancy` repository
- `stancl/tenancy` package metadata
- Official Tenancy v3 docs pages
- Raw source verification for config/migrations/service provider stub/single DB traits

---

## Verified Internal Findings (`commerce-support`)

### Core tenancy primitives exist and are centralized

Key files:

- `packages/commerce-support/src/Contracts/OwnerResolverInterface.php`
- `packages/commerce-support/src/Support/OwnerContext.php`
- `packages/commerce-support/src/Traits/HasOwner.php`
- `packages/commerce-support/src/Traits/HasOwnerScopeConfig.php`
- `packages/commerce-support/src/Support/OwnerScope.php`
- `packages/commerce-support/src/Support/OwnerQuery.php`
- `packages/commerce-support/src/Support/OwnerWriteGuard.php`
- `packages/commerce-support/src/Support/OwnerRouteBinding.php`
- `packages/commerce-support/src/Testing/OwnerScopingContractTests.php`

### Design profile

- Polymorphic owner boundary via `owner_type` / `owner_id`.
- Owner resolution via pluggable resolver contract (`OwnerResolverInterface`).
- Supports both modes:
  - single-tenant/global mode (default resolver returns `null`)
  - owner-scoped multitenancy mode (package-level toggles)
- Eloquent + query-builder support:
  - global scopes (`HasOwner` + `OwnerScope`)
  - explicit scopes (`forOwner`, `globalOnly`, `withoutOwnerScope`)
  - raw query-builder helper (`OwnerQuery::applyToQueryBuilder`)
- Write guard and route binding hardening provided (`OwnerWriteGuard`, `OwnerRouteBinding`).

### Scale of existing adoption in this repository

Measured footprint:

- Models using `HasOwner`: **98**
- Migrations with owner morph columns: **92**
- Owner-related config/source files: **123**
- Owner/cross-tenant-related test files: **118**
- Package source files using `DB::table()`: **6**
- Source files using owner-scope bypasses (`withoutOwnerScope` / owner global scope bypass): **56**

### What is already strong

- Package-first central contract is implemented and reused broadly.
- Cross-tenant protections exist in multiple packages/tests.
- Filament resource scoping appears widespread in representative samples.
- Route/download hardening exists in critical flows (e.g. order invoice download, doc download).

### Where effort is still high

- Consistency burden remains significant across many packages.
- Manual care still required for:
  - raw query-builder paths
  - intentional scope bypasses
  - write-time validation of inbound foreign IDs
- Config key consistency across packages could be improved.
- Some docs/config examples appear drifted from actual keys.

---

## Verified External Findings (`stancl/tenancy`)

### Package status and compatibility

- Package: `stancl/tenancy`
- Verified release: `v3.10.0` (2026-03-18)
- Laravel support: `illuminate/support ^10|^11|^12|^13`
- GitHub maturity indicators:
  - 4k+ stars
  - active recent pushes

### Capability profile

`stancl/tenancy` is an **application tenancy framework** with robust built-ins for:

- tenant identification (domain/subdomain/path/request data/custom resolvers)
- automatic + manual tenancy modes
- single-database tenancy and multi-database tenancy
- tenant DB lifecycle tooling (create/migrate/seed/delete)
- tenancy bootstrappers for DB/cache/filesystem/queue (+ optional Redis)
- central-vs-tenant app boundary model

### Verified implementation details from source/docs

- Bootstrappers list includes:
  - `DatabaseTenancyBootstrapper`
  - `CacheTenancyBootstrapper`
  - `FilesystemTenancyBootstrapper`
  - `QueueTenancyBootstrapper`
  - optional `RedisTenancyBootstrapper`
- Published lifecycle stub wires events/jobs such as:
  - `TenantCreated` -> create/migrate/(optional seed)
  - `TenantDeleted` -> delete DB
- Docs note for single-database mode:
  - disable DB bootstrapper + DB lifecycle jobs
  - use tenant scoping trait patterns (`tenant_id`-style)
- Docs note testing caveats in automatic multi-db mode:
  - no `:memory:` SQLite
  - `RefreshDatabase` caveats due to DB switching
- Docs note middleware timing caveat:
  - route middleware runs after controller constructors

### Fit caveat for this monorepo conventions

Default Stancl migration stubs conflict with this repo’s strict DB conventions (UUID PK policy / no DB-level FK constraints/cascades). This is solvable, but non-trivial at this scale.

---

## Clear Comparison Matrix

### Legend

- ✅ = strong native fit  
- ⚠️ = partial / possible with significant custom work  
- ❌ = weak fit for that criterion

### Matrix A — Capability & Architectural Fit

| Area | `commerce-support` (current) | `stancl/tenancy` | Better fit **for this repo now** |
|---|---|---|---|
| Package independence (standalone install) | ✅ Native | ⚠️ App-framework oriented | **commerce-support** |
| Single-tenant mode simplicity | ✅ Native default | ⚠️ Possible, heavier | **commerce-support** |
| Single-DB owner scoping | ✅ Native (`owner_type/owner_id`) | ✅ Supported (different model style) | **commerce-support** |
| Polymorphic owner boundary | ✅ Native strength | ⚠️ Tenant-model-centric | **commerce-support** |
| Global/shared row semantics | ✅ Native (`include_global`, `globalOnly`) | ⚠️ Custom modeling needed | **commerce-support** |
| Cross-package tenancy contract reuse | ✅ Native | ⚠️ Not package-contract-first | **commerce-support** |
| Tenant identification middleware (domain/subdomain/path/header) | ⚠️ Needs build | ✅ Native | **stancl/tenancy** |
| Tenant provisioning lifecycle | ❌ Needs build | ✅ Native | **stancl/tenancy** |
| Multi-database tenancy | ❌ Needs build | ✅ Native | **stancl/tenancy** |
| Cache isolation per tenant | ❌ Needs build | ✅ Native | **stancl/tenancy** |
| Filesystem isolation per tenant | ❌ Needs build | ✅ Native | **stancl/tenancy** |
| Queue context isolation | ❌ Needs build | ✅ Native | **stancl/tenancy** |
| Central vs tenant app boundary model | ⚠️ Partial | ✅ Native | **stancl/tenancy** |
| Compatibility with existing monorepo conventions | ✅ High | ⚠️ Adaptation required | **commerce-support** |
| Migration cost from current state | ✅ None | ❌ Very high | **commerce-support** |
| Testing continuity in current setup | ✅ High | ⚠️ More complexity in multi-db automatic mode | **commerce-support** |

### Matrix B — Weighted Strategic Fit (for this monorepo)

Scoring model:

- Weight: 1 (low) to 5 (critical)
- Score: 1 (poor) to 5 (excellent)
- Weighted score = weight × score

| Criterion | Weight | `commerce-support` score | `commerce-support` weighted | `stancl/tenancy` score | `stancl/tenancy` weighted |
|---|---:|---:|---:|---:|---:|
| Package independence | 5 | 5 | 25 | 2 | 10 |
| Existing adoption leverage | 5 | 5 | 25 | 1 | 5 |
| Single-tenant simplicity | 4 | 5 | 20 | 3 | 12 |
| Single-DB owner scoping fit | 4 | 5 | 20 | 3 | 12 |
| SaaS tenant identification | 4 | 2 | 8 | 5 | 20 |
| SaaS provisioning lifecycle | 4 | 1 | 4 | 5 | 20 |
| Multi-database capability | 4 | 1 | 4 | 5 | 20 |
| Infrastructure isolation (cache/fs/queue) | 4 | 1 | 4 | 5 | 20 |
| Convention alignment | 3 | 5 | 15 | 2 | 6 |
| Migration risk from now | 5 | 5 | 25 | 1 | 5 |
| **Total** |  |  | **150** |  | **130** |

> Interpretation: for the **current monorepo/package context**, `commerce-support` leads. For a **hosted SaaS platform context**, Stancl would score much higher if SaaS infra criteria were weighted even more heavily.

---

## SWOT Analysis

### SWOT — `commerce-support`

#### Strengths

- Purpose-built for this package ecosystem.
- Polymorphic owner model supports diverse ownership domains.
- Works cleanly in single-tenant and owner-scoped modes.
- Already deeply integrated across code and tests.
- Fits current repo conventions and migration policies.
- Includes route/write guard utilities for defense-in-depth.

#### Weaknesses

- Not a full tenancy platform (identification/provisioning/multi-db/isolation are not native).
- High implementation discipline required across packages.
- Manual scoping still needed in some raw query and bypass scenarios.
- Inconsistencies in config key structure increase cognitive load.

#### Opportunities

- Add optional adapter/bridge to external tenancy frameworks.
- Introduce audit automation for unscoped queries and unsafe bypasses.
- Standardize owner config conventions across all packages.
- Add additional reusable helper APIs/macros to reduce manual error.

#### Threats

- Drift/inconsistency can cause cross-tenant leakage if governance weakens.
- Team fatigue from repetitive per-package tenancy plumbing.
- Future SaaS needs may exceed current abstraction if not extended.

---

### SWOT — `stancl/tenancy`

#### Strengths

- Mature, widely adopted application tenancy framework.
- Strong out-of-box support for SaaS tenancy concerns.
- Powerful tenant identification and lifecycle automation.
- Strong multi-database and tenancy bootstrapper ecosystem.

#### Weaknesses

- Framework-centric; not naturally package-contract-first.
- Migration and convention adaptation needed for this monorepo.
- Significant refactor risk if used as direct replacement.
- Testing and operational complexity increase in automatic multi-db setups.

#### Opportunities

- Great optional host-app layer for enterprise SaaS deployments.
- Can complement (not replace) package-level owner contract.
- Enables future product tiers requiring stronger infra isolation.

#### Threats

- Hard dependency could break package independence promise.
- Misaligned adoption can create dual-model confusion (owner scope vs tenant context).
- Large migration could introduce regressions if attempted wholesale.

---

## Recommendation

## 1) Keep `commerce-support` as the tenancy core contract

Do not replace core package tenancy abstractions.

## 2) Introduce an optional Stancl integration bridge

Create an optional integration package/provider (e.g. `commerce-tenancy-stancl`) that:

- binds `OwnerResolverInterface` to Stancl tenant context
- avoids hard requiring Stancl in all commerce packages
- allows host applications to opt into full SaaS tenancy features

## 3) Adopt a layered tenancy model

- **Layer A (package layer):** `commerce-support` owner contract and model/query guards
- **Layer B (host app layer):** optional Stancl for identification, provisioning, isolation, multi-db

## 4) Harden governance and consistency

- standardized owner config key shape
- scoping audit checks in CI
- explicit review checklist for `DB::table` + `withoutOwnerScope`
- contract tests across owner-aware models

---

## Direct answer to your question

> “If tenant identification, tenant provisioning, cache/filesystem/queue isolation, and other areas are implemented in `commerce-support`, would Stancl not be needed anymore?”

### Short answer

**Technically yes, practically maybe not worth it.**

If you fully build and maintain all those capabilities in `commerce-support` at production-grade quality (including long-term upgrades, edge cases, docs, tooling, and ecosystem integration), then you can reduce or eliminate the need for Stancl.

### Practical reality

That effort is effectively building and operating a full tenancy framework yourself. At that point, the key question becomes:

- **Build cost + maintenance burden** vs
- **Integrating a mature external framework for host apps**

Given this repository’s package-first architecture, the most practical path is:

- keep `commerce-support` as package tenancy core
- add Stancl only where full SaaS infra tenancy is required

That gives you maximum flexibility with minimum disruption.

---

## What Stancl is *especially good at* (and expensive to rebuild well)

This is the practical differentiator list — not because `commerce-support` *cannot* build these, but because these are hard to build, test, and maintain at the same maturity level over time.

### 1) Tenant lifecycle orchestration as a first-class system

Stancl already ships event-driven tenant lifecycle patterns (create DB, migrate, seed, delete, initialize/revert context). Rebuilding this means owning:

- idempotency guarantees
- rollback semantics
- operational retries/failure handling
- provisioning race-condition handling
- observability and diagnostics

### 2) Automatic runtime context switching across infrastructure

Stancl bootstrappers coordinate context for multiple subsystems (DB/cache/filesystem/queue, optional Redis). Rebuilding this safely means you must harden:

- request lifecycle boundaries
- queued job context propagation
- long-running worker context reset
- central-to-tenant context transitions

### 3) Production-grade tenant identification matrix

Stancl provides multiple identification strategies (domain/subdomain/path/request/custom) with middleware conventions. Rebuilding means implementing and supporting:

- canonical identifier resolution order
- edge-case handling (proxies, headers, local/dev hosts)
- route middleware ordering and early-identification behavior
- cross-domain/session/security nuances

### 4) Multi-database ergonomics and operations

Stancl includes patterns for tenant DB management and command workflows. Rebuilding means maintaining:

- per-driver behavior (MySQL/PostgreSQL/SQLite variants)
- migration orchestration at tenant scale
- tenancy-aware backup/restore and maintenance workflows
- database naming/schema collision prevention

### 5) Ecosystem integration surface

Stancl has documented integration pathways with common Laravel ecosystem tools. Rebuilding means continuously validating compatibility each time Laravel/ecosystem packages evolve.

### 6) Operational “unknown unknowns” learned by broad usage

A mature external package bakes in edge-case lessons from many deployments. Rebuilding internally means your team discovers those incidents the hard way in production unless you invest heavily in chaos testing and staged rollout controls.

### Net: build vs buy framing

- **Can `commerce-support` build all of this?** Yes.
- **Can it build it quickly, safely, and keep it current at low ongoing cost?** That is the real challenge.

So the realistic advantage of Stancl is less about theoretical capability and more about **time-to-reliability** and **maintenance leverage**.

---

## Proposed Decision Framework (Build vs Integrate)

Use this simple thresholding model:

- If >70% of your roadmap is package-level owner scoping and single-db isolation:  
  -> prioritize extending `commerce-support`.
- If >40% of roadmap includes SaaS infra tenancy (domains/provisioning/multi-db/infra isolation):  
  -> adopt optional Stancl host-layer integration now.
- If both are high:  
  -> layered hybrid model is the best long-term architecture.

---

## Appendix: Key Evidence References

### Internal

- `packages/commerce-support/src/Traits/HasOwner.php`
- `packages/commerce-support/src/Support/OwnerQuery.php`
- `packages/commerce-support/src/Support/OwnerWriteGuard.php`
- `packages/commerce-support/src/Support/OwnerRouteBinding.php`
- `packages/commerce-support/src/Support/OwnerContext.php`
- `packages/products/src/Models/Product.php`
- `packages/customers/src/Models/Customer.php`
- `packages/pricing/src/Models/PriceList.php`
- `packages/tax/src/Support/TaxOwnerScope.php`
- `packages/filament-orders/src/FilamentOrdersServiceProvider.php`
- `packages/filament-docs/src/Http/Controllers/DocDownloadController.php`
- `tests/src/Products/OwnerScopingTest.php`
- `tests/src/Pricing/CrossTenantIsolationTest.php`
- `tests/src/FilamentOrders/Feature/InvoiceDownloadRouteTest.php`
- `tests/src/FilamentVouchers/Integration/CartOwnerScopingTest.php`

### External

- https://github.com/archtechx/tenancy
- https://packagist.org/packages/stancl/tenancy
- https://tenancyforlaravel.com/docs/v3/introduction/
- https://tenancyforlaravel.com/docs/v3/tenant-identification/
- https://tenancyforlaravel.com/docs/v3/tenancy-bootstrappers/
- https://tenancyforlaravel.com/docs/v3/single-database-tenancy/
- https://tenancyforlaravel.com/docs/v3/multi-database-tenancy/
- https://tenancyforlaravel.com/docs/v3/testing/

---

## Final Position

You are not wrong: if `commerce-support` grows to include all major tenancy framework capabilities, Stancl becomes less necessary.

But for this repository’s current shape, the **best ROI and lowest risk** is the hybrid model:

- package-level tenancy contract remains in `commerce-support`
- optional host-app SaaS tenancy powered by Stancl when needed

That avoids a disruptive rewrite while preserving a credible path to full SaaS-grade tenancy.

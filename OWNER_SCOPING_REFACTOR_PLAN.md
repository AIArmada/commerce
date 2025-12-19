# Owner Scoping Refactor Plan (Monorepo)

## Goals

- Enforce the monorepo multi-tenancy contract across **all packages and all surfaces** (HTTP, Filament, jobs, commands, exports, widgets, health checks, webhooks).
- Reduce “forgot to scope” risk by moving from **opt-in** scoping (`->forOwner()`) to **default-on** enforcement where feasible.
- Make owner enforcement **consistent, auditable, and testable** with per-package cross-tenant regression tests.
- Centralize primitives in `packages/commerce-support` as the single source of truth.

## Non-Goals

- A style-only or formatting-only refactor.
- A repo-wide Pint run. Only run Pint on changed packages/files.
- Changing package dependencies.

## Current State (What We Have)

- `packages/commerce-support/src/Traits/HasOwner.php` provides `scopeForOwner(...)` and helpers, but most packages still rely on **opt-in calls** (`->forOwner(...)`) and/or per-package scopers.
- There are multiple enforcement styles in production code today:
  - **Local scopes** (`scopeForOwner`) + manual usage in resources/services.
  - **Helper scopers** (`PricingOwnerScope`, `TaxOwnerScope`, `ShippingOwnerScope`, etc.).
  - **Filament tenancy** via `protected static ?string $tenantOwnershipRelationshipName = 'owner';` (varies by resource).
  - **Global scopes** already exist in `inventory` models (`addGlobalScope('owner', ...)`) – this is the closest implementation to “default-on enforcement”.
  - **Raw DB/query-builder reads** (`DB::table(...)`) are used heavily in some packages (e.g., cart analytics/monitoring/AI) and bypass Eloquent/global scopes entirely.
- The repository contains more than packages:
  - A full demo app at `demo/` (Laravel + Filament) that uses the packages.
  - A monorepo test harness under `tests/` with extensive owner-scoping tests for many packages.

### High-impact inconsistencies found (must be addressed before ecosystem-wide enforcement)

1. **`owner_type` / `owner_id` is overloaded in some tables (naming collision)**
   - `packages/affiliates/src/Models/AffiliatePayout.php` uses `owner_type/owner_id` to mean “payout payee (Affiliate)”, not tenant owner.
   - `packages/vouchers/src/Models/VoucherWallet.php` uses `owner_type/owner_id` to mean “wallet holder”, not tenant owner (see `packages/vouchers/src/Traits/HasVouchers.php`).
   - This blocks “one global enforcement mechanism” because the same column names do not mean the same thing everywhere.

2. **Config + scoping behavior is not uniform**
   - Some packages have explicit owner toggles (`orders.owner.enabled`, `pricing.features.owner.enabled`, etc.).
   - Some packages have **no owner feature config** (e.g., `customers` has no owner config, yet models use `HasOwner`).
   - Some scopers expect config keys that are missing (e.g. `tax.features.owner.include_global` is read in code but not defined in `packages/tax/config/tax.php`).

3. **Known unscoped entrypoints exist (examples, not exhaustive)**
   - Orders health check counts across all owners: `packages/orders/src/Health/OrderProcessingCheck.php:67`.
   - Docs reminder job queries without owner scoping: `packages/docs/src/Jobs/SendDocReminderJob.php:167`.
   - Docs PDF download uses route-model binding + no owner/authorization check: `packages/filament-docs/routes/filament-docs.php:12` and `packages/filament-docs/src/Http/Controllers/DocDownloadController.php:15`.
   - Filament nav badges with unscoped counts: `packages/filament-docs/src/Resources/DocResource.php:81`, `packages/filament-docs/src/Resources/DocTemplateResource.php:70`, `packages/filament-cashier-chip/src/Resources/BaseCashierChipResource.php:28`.
   - DB-based dashboards/analytics without owner filters (leak risk when owner mode is enabled): e.g. `packages/filament-cart/src/Services/CartMonitor.php:31`, `packages/affiliates/src/Services/PerformanceBonusService.php:145`, `packages/vouchers/src/AI/VoucherMLDataCollector.php:32`.

## Target State (What We Want)

### 1) Centralized enforcement primitives (commerce-support)

Add a small set of reusable enforcement primitives so packages stop re-implementing owner logic:

1. **Owner context resolution**
   - A single helper that resolves current owner from `OwnerResolverInterface` (or returns `null`) with consistent behavior.
   - Add an explicit “overrideable owner context” for non-request surfaces (jobs/commands/tests), so owner context can be set intentionally and temporarily without rebinding the container.

2. **Default-on enforcement for owned models (global scope)**
   - A global scope that automatically constrains reads by current owner context when enabled by config.
   - Must support the contract semantics:
     - `forOwner($owner)` = owner-only, optionally include global when explicitly requested.
     - `globalOnly()` = global-only.
     - “Include global” must remain explicit and consistent (avoid accidental include-global).
   - Use the existing `inventory` implementation as a reference, but standardize it into `commerce-support`.

3. **Opt-out escape hatch**
   - Explicit, greppable ways to bypass enforcement for legitimate system-wide operations:
     - e.g. `Model::query()->withoutOwnerScope()` (preferred)
     - or `->withoutGlobalScope(OwnerScope::class)` (acceptable if standardized).
   - The goal is that “intentionally cross-tenant” code is easy to audit.

4. **Route binding hardening**
   - For HasOwner models, enforce owner scoping during route model binding by default where applicable (prevents cross-tenant resolution via `{model}` params).
   - Use the existing secure pattern as reference (invoice download): `packages/filament-orders/src/FilamentOrdersServiceProvider.php:39`.

5. **Write-path defense helpers**
   - Standardized utilities to validate inbound foreign IDs belong to current owner scope **inside action handlers**, not just in Filament option queries.
   - This is the “no UI trust” contract: submitted IDs must be validated server-side.
   - Extract and unify the patterns already implemented in `inventory` + `tax` models (e.g. validating `location_id`, `zone_id` within the current owner scope).

6. **Query-builder (DB::table) owner scoping**
   - Provide a parallel API for `Illuminate\Database\Query\Builder` (and joins) because many analytics/AI/reporting paths bypass Eloquent and won’t pick up Eloquent global scopes.
   - This API must support:
     - owner columns (`owner_type/owner_id`)
     - optional include-global
     - relationship-owned boundaries (e.g. “owned via location relation”)

### 2) Package-by-package standardized integration

Each package that stores tenant-owned data must:

- Use `HasOwner` and `nullableMorphs('owner')` for owned tables (already part of the contract).
- Adopt commerce-support enforcement primitives.
- Remove bespoke “OwnerScope” logic unless it’s needed for relationship-based ownership.
- Ensure existing cross-tenant tests still pass; add missing tests where coverage gaps exist.

### 3) Handling relationship-owned tables

Some tables may be “owned” by relation (e.g. `InventoryLevel` owned by `location.owner` rather than direct `owner_*` columns).

For these cases:

- Define an explicit boundary strategy per model:
  - Direct owner columns (preferred): `owner_type/owner_id`.
  - Relation-owned: scoping must be applied via `whereHas()` / `join` on the ownership relation.
- Provide a shared interface/hook in `commerce-support` so each model declares how owner scoping should be applied:
  - Example approach: `OwnedByOwnerColumns` vs `OwnedByRelation` strategies.
  - Ensure counts/aggregates/widgets/exports/health checks use the same scoping path.

## Design Decisions (To Lock In Early)

Before large-scale changes, align on these defaults:

1. **Define what `owner_*` means (non-negotiable)**
   - `owner_type/owner_id` MUST mean “tenant boundary owner” across the ecosystem.
   - Any other “ownership” concepts (wallet holder, payout payee, actor, etc.) MUST use different column names.

1. **Default include-global**: should it be `false` by default in enforcement?
   - Recommendation: default `includeGlobal = false` for security; allow opt-in include-global only where business rules require it.

2. **What does `owner = null` mean?**
   - Recommendation: `owner = null` means “global-only” (already the behavior in `HasOwner::scopeForOwner()`).

3. **Do we ever allow cross-tenant operations?**
   - If yes, require explicit opt-out methods that are greppable.

4. **Filament tenancy interaction**
   - Filament v5 is installed (`filament/filament` is `v5.0.0-beta7` in this repo).
   - Filament tenancy should be treated as a *source of owner context* (via `OwnerResolverInterface`), not the enforcement mechanism.
   - Don’t rely solely on `$tenantOwnershipRelationshipName` to satisfy the monorepo contract; the contract applies to non-UI surfaces too.

5. **Owner context in non-request code**
   - Jobs/commands/scheduled tasks must not depend on ambient web auth to resolve owner.
   - Owner context must be passed explicitly (serialized) or iterated (per owner), then applied via a standard mechanism.

## Execution Strategy (Phased Rollout)

### Phase 0 — Inventory & Mapping (read-only analysis)

Deliverables:

- A package list with “tenant-owned models”, “relationship-owned models”, and “global models”.
- A table-level map of columns:
  - which tables use `owner_type/owner_id` as **tenant boundary**
  - which tables use `owner_type/owner_id` for **non-tenant semantics** (must be renamed)
- A map of DB/query-builder usage on tenant-owned data (where Eloquent scopes cannot help).
- A list of entrypoints to audit per package:
  - Filament `Resource::getEloquentQuery()`
  - Widgets/pages
  - Exports/imports
  - Jobs/commands/scheduled tasks
  - Health checks
  - Controllers/routes (route-model binding)
  - Webhooks

Auditing greps (run per package):

```bash
rg -n -- \"::query\\(|->query\\(|getEloquentQuery\\(\" packages/<pkg>/src
rg -n -- \"count\\(|sum\\(|avg\\(|exists\\(\" packages/<pkg>/src
rg -n -- \"DB::table\\(\" packages/<pkg>/src
rg -n -- \"Route::.*\\{.*\\}\" packages/<pkg>/routes
```

### Phase 1 — Fix semantic collisions & config gaps (blocking work)

Deliverables:

- Rename non-tenant uses of `owner_type/owner_id` and introduce proper tenant owner columns where needed:
  - `affiliate_payouts` (currently uses `owner_*` for payee)
  - `voucher_wallets` (currently uses `owner_*` for wallet holder)
- Standardize/complete config keys that code expects (e.g., add missing `tax.features.owner.include_global` config key).
- Decide and document whether each package supports:
  - owner-only rows
  - global-only rows
  - explicit include-global behavior

Acceptance:

- `owner_type/owner_id` unambiguously means “tenant boundary” everywhere.
- Any non-tenant “owner” relationships use distinct columns and relationship names.

### Phase 2 — Add enforcement primitives in `commerce-support`

Deliverables:

- Owner context helper + “owner context override” for jobs/commands/tests.
- Standard owner enforcement global scope + explicit opt-out API.
- Route binding hardening (or standardized route patterns for downloads/actions).
- Query-builder scoping helpers (for `DB::table(...)`).
- Utilities for write-path foreign ID validation (shared assertions).
- Tests for the primitives themselves.

Acceptance:

- A minimal owned model using the new enforcement reads correctly without requiring `->forOwner()` calls.
- Escape hatch is explicit and greppable.

### Phase 3 — Convert one “simple” package end-to-end (Eloquent-first pilot)

Pick a package where most tables have direct `owner_*` columns (good starter candidates: `pricing`, `vouchers`, `products`, `orders`).

Deliverables per package:

- Remove / reduce bespoke owner helper classes if redundant.
- Ensure all non-UI surfaces use enforced queries (or explicit opt-out when intended).
- Keep existing tests passing; add missing regression tests only where gaps exist.

Acceptance:

- You can’t read another owner’s data via:
  - Filament resources and widgets
  - background jobs and commands
  - exports
  - route-model binding

### Phase 4 — Convert remaining Eloquent packages (direct-owner + relation-owner)

Repeat Phase 2 package-by-package until all direct-owner models are covered:

- `orders`
- `products`
- `customers`
- `pricing`
- `vouchers`
- `shipping`
- `docs`
- `affiliates`
- `chip` / `cashier` / `cashier-chip` (as applicable)
- `filament-authz` if it is owner-scoped (verify boundary)

### Phase 5 — Convert DB/query-builder heavy surfaces

Deliverables:

- Apply query-builder owner scoping to any `DB::table(...)` paths touching tenant-owned data (dashboards, reports, AI, monitoring).
- Ensure jobs/commands that operate outside request context carry owner context explicitly (or iterate owners).

Primary candidates (based on current code patterns):

- `cart` + `filament-cart` (heavy DB/query usage; note cart uses empty-string “global” in its own tables today)
- `affiliates` analytics/services (leaderboards, cohort analysis)
- `vouchers` AI collectors/analytics

### Phase 6 — Remove legacy patterns & lock enforcement

Deliverables:

- Consolidate duplicate owner logic (prefer commerce-support primitives).
- Delete/replace redundant per-package OwnerScope helpers (where safe).
- Add “audit guardrails”:
  - tests that ensure key entrypoints use enforced queries
  - greppable explicit opt-outs for cross-tenant/system behavior

## Package Playbook (Repeatable Checklist)

For each package `packages/<pkg>`:

0. **Column semantics audit**
   - Confirm `owner_type/owner_id` means tenant boundary.
   - If `owner_*` is used for other semantics, rename and add proper tenant owner columns.

1. **Boundary definition**
   - Identify all tenant-owned tables/models and confirm they have `owner_*` columns or define a relation-owned strategy.

2. **Model integration**
   - Use `HasOwner`.
   - Adopt the commerce-support enforcement primitive.
   - Ensure create paths auto-assign owner when enabled (if required by package semantics).

3. **Read surfaces**
   - Filament Resources: ensure `getEloquentQuery()` is owner-scoped (enforced by default; avoid manual duplication).
   - Widgets/pages: counts and aggregates must use enforced scopes.
   - Jobs/commands/health checks/webhooks: enforced by default or explicitly opted out for system-wide behavior.
   - DB/query-builder reads: ensure `DB::table(...)` paths touching tenant-owned data are owner-scoped too.

4. **Write surfaces**
   - Any inbound foreign IDs must be validated as belonging to the current owner scope in the action handler.
   - Relationship selects: scope option queries and validate submitted IDs.

5. **Tests**
   - Keep existing owner/cross-tenant tests passing.
   - Add missing tests for uncovered surfaces (common gaps: download routes, background jobs, DB-based dashboards).

6. **Audit**
   - Run greps and review deltas:
     - `rg -n -- \"::query\\(|->query\\(|getEloquentQuery\\(\" packages/<pkg>/src`
     - `rg -n -- \"DB::table\\(\" packages/<pkg>/src`
   - Confirm “intentionally cross-tenant” code uses explicit opt-out API.

## Risk Management

- **Behavior changes:** enforcing global scoping can change admin/system behavior. Require explicit opt-out for system operations and document those call sites.
- **Data visibility:** `includeGlobal` semantics must be consistent; avoid accidental include-global.
- **Scheduled jobs:** jobs that currently operate across tenants must either:
  - iterate owners explicitly, binding the owner context per iteration, or
  - intentionally use the opt-out API and document why.

## Suggested Conversion Order (Pragmatic)

1. Fix semantic collisions (`voucher_wallets`, `affiliate_payouts`) and config gaps (blocking work).
2. Extract/standardize global enforcement primitives in `commerce-support` (use `inventory` patterns as the reference).
3. `docs` + `filament-docs` (download route + job + badges are concrete, high-risk surfaces).
4. `orders` (health check + routes already show a good secure pattern).
5. `pricing`, `tax`, `products`, `vouchers` (many existing tests already cover owner scoping).
6. `shipping`, `jnt`, `chip` / `cashier*` (ensure non-UI surfaces + webhooks are owner-safe).
7. `filament-cart` + `cart` (DB/query-heavy; owner context propagation in jobs is critical).
8. Remaining Filament packages that still rely mostly on `$tenantOwnershipRelationshipName` (e.g. `filament-affiliates`) – add explicit enforced queries where needed.
9. Demo app integration (`demo/`) to showcase correct OwnerResolver + owner context propagation in jobs/commands.

## Definition of Done (Ecosystem)

- Every tenant-owned package has:
  - explicit boundary
  - enforced reads on all surfaces
  - validated writes (foreign IDs validated server-side)
- “`owner_type/owner_id`” is reserved for tenant boundaries everywhere (no semantic collisions).
- DB/query-builder reporting paths are scoped (or explicitly opted out).
- “Forgot to scope” is structurally prevented by default-on enforcement (or fails loudly).
- Any cross-tenant/system behavior is explicit and greppable.

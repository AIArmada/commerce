---
title: Owner Multitenancy Hardening Plan
---

# Owner Multitenancy Hardening Plan

This playbook standardizes owner scoping across Commerce packages after the `commerce-support` owner foundation hardening.

For package-by-package review, use the broader [Commerce Support Consumer Audit Standard](commerce-support-consumer-audit-standard.md). It expands this owner checklist to include targeting, webhooks, health checks, Filament actions, and non-request surfaces.

Related hardening semantics to carry into consumer packages:
- Non-custom targeting modes (`all` / `any`) require a present, non-empty `rules` array (missing/empty rules fail closed).
- Webhook processing is idempotent at both webhook-call level (row lock + `processed_at`) and provider-event level (cross-row dedupe by event ID + normalized event type). Cross-row dedupe requires an exact event type match when the payload carries one; type-less payloads dedupe only against other type-less rows. Same event ID with different types are distinct events and must both be processed.

## Goals

- Make `commerce-support` the single source of truth for owner context, scopes, route binding, write guards, and nullable-owner uniqueness helpers.
- Ensure every tenant-owned read/write path is owner-safe.
- Remove public `owner_key` patterns in favor of hidden internal uniqueness helpers.
- Make global row semantics explicit and long-lived.

## Core semantics

- Tenant ownership uses `owner_type` and `owner_id` as the default column names. Custom column names are supported by implementing `ownerScopeConfig()` on the model and returning an `OwnerScopeConfig` with `ownerTypeColumn`/`ownerIdColumn` set. All `HasOwner` helpers and the `owner()` relation respect the configured columns.
- Cross-package payload contracts should use snake_case owner tuple fields (`owner_type`, `owner_id`) at wire/persistence boundaries. PHP APIs should prefer camelCase fields (`ownerType`, `ownerId`, `ownerIsGlobal`).
- Non-Eloquent owner references passed into helper APIs must implement `AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable` instead of relying on raw duck-typed objects.
- `owner_scope` is an internal hidden uniqueness helper, not public API and not an authorization boundary.
- `forOwner($owner)` returns owner-only rows.
- `forOwner($owner, includeGlobal: true)` returns owner rows plus global rows.
- `globalOnly()` returns only ownerless rows.
- Missing owner context fails fast when owner mode is enabled.
- `commerce-support.owner.enabled=true` fails closed unless `OwnerResolverInterface` resolves through a concrete resolver instead of `NullOwnerResolver`.
- `OwnerContext::withOwner(null, ...)` is the explicit global context.
- `OwnerContext::setForRequest()` is HTTP-only and reserved for framework integrations (middleware/team resolvers); non-HTTP surfaces must use `OwnerContext::withOwner(...)`.
- Global rows visible from tenant contexts are not mutable unless the call site enters explicit global context.

## Standard config shape

Each owner-enabled package should expose:

```php
'owner' => [
    'enabled' => env('PACKAGE_OWNER_ENABLED', false),
    'include_global' => env('PACKAGE_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('PACKAGE_OWNER_AUTO_ASSIGN_ON_CREATE', true),
],
```

Defaults should keep `include_global` false.

The global support package should expose:

```php
'owner' => [
    'enabled' => env('COMMERCE_OWNER_ENABLED', false),
    'resolver' => env('COMMERCE_OWNER_RESOLVER', NullOwnerResolver::class),
],
```

`COMMERCE_OWNER_ENABLED` is not a replacement for per-package owner flags. It is the resolver safety switch that prevents an application from booting in an apparently owner-enabled state while still using the no-op resolver.

## Model checklist

For every tenant-owned model:

1. Use `HasOwner`.
2. Use `HasOwnerScopeConfig` when config-driven owner mode is needed.
3. Use `HasOwnerScopeKey` only when nullable-owner uniqueness is required.
4. Hide `owner_scope` from serialization.
5. Do not expose `owner_scope` in fillable arrays, exports, forms, tables, filters, or docs.
6. Do not implement package-specific owner guards when `commerce-support` already covers the behavior.

## Migration checklist

- Use `$table->nullableMorphs('owner')` / `$table->nullableUuidMorphs('owner')` for tenant-owned rows.
- Use `uuid('id')->primary()` for primary keys.
- Do not add database foreign-key constraints or cascades.
- Add `owner_scope` only for nullable-owner uniqueness.
- Use unique indexes such as `['owner_scope', 'slug']` or `['owner_scope', 'identifier', 'instance']` where needed.

## Query checklist

Search each package for:

```bash
rg -n -- "::query\(|->query\(|getEloquentQuery\(" packages/<pkg>/src
rg -n -- "count\(|sum\(|avg\(|exists\(" packages/<pkg>/src
rg -n -- "DB::table\(" packages/<pkg>/src
rg -n -- "Route::.*\{.*\}" packages/<pkg>/routes
rg -n -- "withoutOwnerScope\(|withoutGlobalScope\(.*Owner" packages/<pkg>/src
```

For each match:

- Prefer default-on `HasOwner` global scope.
- Use `OwnerQuery::applyToQueryBuilder()` for query-builder paths.
- Use `OwnerWriteGuard` for submitted IDs and foreign keys.
- Use `OwnerRouteBinding` for route-bound tenant-owned models.
- Document intentional cross-owner/system operations with explicit opt-outs.

## Filament checklist

- `getEloquentQuery()` must be owner-safe.
- Relationship selects must scope options by owner.
- Action handlers must revalidate submitted IDs server-side.
- Global rows shown via include-global must be read-only unless explicit global context is active.

## Non-UI checklist

Commands, jobs, schedules, exports, reports, health checks, and webhooks must not rely on ambient web auth. Pass or iterate owner explicitly, then call `OwnerContext::withOwner($owner, ...)`.

When reading owner tuples from raw rows (especially with configurable owner columns), prefer shared tuple helpers:

- `OwnerTupleColumns` for column resolution
- `OwnerTupleParser` for tri-state tuple parsing (owner / explicit global / unresolved)

## Test checklist

Every owner-enabled package should have tests for:

- owner-only reads,
- include-global reads,
- global-only reads,
- missing owner fail-fast,
- cross-owner writes rejected,
- global row write protection,
- query-builder owner scoping,
- route binding owner scoping,
- nested `OwnerContext::withOwner()` restoration,
- hidden `owner_scope` uniqueness if used.

## Package rollout order

1. `commerce-support` — foundation and contract tests.
2. Packages with shared operational read models (`cart`, `filament-cart`, `signals`).
3. Transactional packages (`orders`, `checkout`, `cashier`, `chip`, `jnt`).
4. Catalog/promotions packages (`products`, `pricing`, `promotions`, `vouchers`).
5. Admin-only Filament packages.

## Manual review notes

- Do not mass-format unrelated packages.
- Do not change package dependencies unless explicitly needed.
- Keep per-package tests and PHPStan runs scoped to touched packages.
- Treat owner hardening as security-sensitive: prefer false negatives over cross-tenant access.

---

## Implementation Status: Isolation Primitives (Q3 2026)

**Status update (2026-04-28):** Isolation helpers were delivered in `commerce-support`; selective downstream retrofits are now active (including `cart`, `filament-cart`, and owner-scoped `signals` surfaces).

### Decision matrix

| Decision | Status | Reasoning |
|---|---|---|
| Fail-closed owner context globally | ✅ Shipped | Explicit `withOwner(null, ...)` for intentional global access |
| Queue jobs carry owner reference | ✅ Shipped | Auto-enter owner context via shared `OwnerContextJob` trait |
| Strict cache key isolation | ✅ Shipped | `OwnerCache` helper enforces `owner:{key}:{logicalKey}` namespace |
| Filesystem path isolation | ✅ Shipped | `OwnerFilesystem` helper enforces `owners/{ownerKey}/...` structure |
| Tenant identification (A+B hybrid) | ✅ Shipped | Service provider binds resolver; middleware identifies at request time |
| Tenant-required routes fail closed | ✅ Shipped | `NeedsOwner` dispatches `OwnerNotResolvedForRequestEvent` and throws `NoCurrentOwnerException` |
| Owner transition observability | ✅ Shipped | Owner lifecycle events dispatched on make/forget boundaries |
| Provisioning pipeline | ⏸️ Deferred | Only needed when enabling multitenancy in a package; skip v1 |
| Package retrofits | ▶️ In progress | Foundation shipped; selective packages adopted, full convergence continues package-by-package |

### Foundation capabilities delivered (v1)

1. **`OwnerCache`** — owner-scoped cache key builder
   - Enforces `owner:{ownerScopeKey}:{logicalKey}` pattern
   - Prevents cache cross-tenant bleed
   - Uses tagged owner groups where supported; on non-tag drivers `forgetOwner()` is intentionally best-effort/no-op

2. **`OwnerFilesystem`** — owner-scoped artifact path builder
   - Enforces `owners/{ownerScopeKey}/...` structure
   - Guards downloads/serving via owner-checked resolver
   - Accepts Eloquent owners or `OwnerScopeIdentifiable` adapters

3. **`OwnerContextJob` trait** — auto-enters owner context for queued jobs
   - Supports owner-bearing model payloads or explicit owner context payloads (recommended via `OwnerScopedJob` + `OwnerJobContext`)
   - Supports explicit global execution with `ownerIsGlobal=true`
   - Rejects contradictory payloads (`ownerIsGlobal=true` combined with owner-bearing payload fields)
   - Fails closed if owner missing when owner mode enabled
   - Prevents queue cross-tenant leakage

4. **`OwnerIdentificationMiddleware` base middleware** — app-level request identification hook
   - Service provider binds resolver (static + known)
   - Middleware identifies owner from domain/auth/header at request time
   - Resolves before any owner-scoped queries

5. **`NeedsOwner` middleware** — tenant-required request hardening
   - Fails closed when owner context is missing
   - Dispatches `OwnerNotResolvedForRequestEvent`
   - Throws `NoCurrentOwnerException`

6. **Owner lifecycle events** — transition observability hooks
   - `MakingOwnerCurrentEvent`
   - `MadeOwnerCurrentEvent`
   - `ForgettingCurrentOwnerEvent`
   - `ForgotCurrentOwnerEvent`

7. **Updated documentation** — 04-multi-tenancy, 10-traits-utilities, 11-isolation-primitives
   - Usage examples for each primitive and middleware
   - Integration patterns for optional adoption

### Delivery sequence (completed)

1. `OwnerCache` (simplest, no side effects)
2. `OwnerFilesystem` (next, also isolated)
3. `OwnerContextJob` trait (touches job lifecycle)
4. `OwnerIdentificationMiddleware` base middleware (defines request-time hook)
5. `NeedsOwner` middleware (fail-closed tenant-required routes)
6. Owner lifecycle events
7. Documentation + comprehensive tests

**Delivery scope:** `commerce-support` only.

### Validation status

- ✅ Unit tests for support primitives pass in `tests/src/Support`
- ✅ PHPStan level 6 is clean for `packages/commerce-support/src`
- ✅ Primitive documentation is published in package docs
- 📌 Consumer-package adoption/coverage is tracked as follow-up hardening work

### Traceability updates (2026-04-28)

- ✅ Commit `4b972f2e` hardens owner-context handling in non-request surfaces:
   - `packages/commerce-support/src/Traits/OwnerContextJob.php`
      - `ReflectionClass($this)` replaced with `ReflectionObject($this)` to remove PHPStan trait-context constructor-type failures.
   - `packages/signals/src/Console/Commands/ProcessSignalAlertsCommand.php`
   - `packages/signals/src/Console/Commands/AggregateDailyMetricsCommand.php`
      - Raw owner-row parsing replaced with `OwnerTupleColumns::forModelClass(...)` + `OwnerTupleParser::fromRow(...)` + `toOwnerModel()`.
      - Malformed owner tuples now fail fast instead of degrading to implicit global context.
- ✅ Verification recorded for the hardening commit:
   - `./vendor/bin/phpstan analyse --level=6 packages/commerce-support/src packages/cart/src packages/filament-cart/src packages/signals/src packages/filament-signals/src` → no errors.
   - `./vendor/bin/pest --parallel tests/src/Signals` → 65 passed.

### What is deferred (non-v1)

- **Provisioning pipeline** — only needed when enabling multitenancy in packages
- **Broad package retrofits** — selective adoptions are complete; remaining packages continue migrating on a controlled rollout
- **Config key standardization** — separate effort after primitives stabilize
- **Conformance audit** — separate sweep after adoption patterns emerge

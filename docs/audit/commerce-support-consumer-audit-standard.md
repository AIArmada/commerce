---
title: Commerce Support Consumer Audit Standard
---

# Commerce Support Consumer Audit Standard

Use this checklist to audit any package that consumes `commerce-support` primitives. The goal is consistent, repeatable review across tenant isolation, targeting, webhooks, health checks, and operational surfaces.

## Audit principles

- Fail closed for security-sensitive behavior.
- Never treat UI scoping as authorization.
- Treat `commerce-support` as the source of truth for owner primitives.
- Non-Eloquent owner references passed into owner-scoped helpers must implement `AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable`; do not rely on raw duck-typing.
- Treat `owner_type` and `owner_id` as the default tenant boundary columns. Custom column names are supported by implementing `ownerScopeConfig()` on the model and returning an `OwnerScopeConfig` with `ownerTypeColumn`/`ownerIdColumn` set. All `HasOwner` helpers and the `owner()` relation must respect the configured columns.
- Prefer small package-scoped changes and tests.
- Document intentional cross-owner/system operations with a greppable escape hatch.

## Package setup

Review package config first.

Required checks:

- Tenant-owned packages expose an owner config block.
- `include_global` defaults to `false` unless business rules require global fallback rows.
- `auto_assign_on_create` defaults to `true` unless records are intentionally global or system-owned.
- JSON-column packages define and use `database.json_column_type`.
- No config key is defined without a read path.

Suggested owner block:

```php
'owner' => [
    'enabled' => env('PACKAGE_OWNER_ENABLED', false),
    'include_global' => env('PACKAGE_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('PACKAGE_OWNER_AUTO_ASSIGN_ON_CREATE', true),
],
```

Global `commerce-support.owner.enabled` is the resolver safety switch. Package-level `*.owner.enabled` keys decide whether package models enforce owner scoping.

> **Implementing owner support from scratch?** This standard covers compliance *checks*. For code templates, migration patterns, and package rollout order when adding owner support to a package that has none, see the [Owner Multitenancy Hardening Plan](owner-multitenancy-hardening-plan.md).

## Model audit

For every tenant-owned model:

- Uses `HasOwner`.
- Uses `HasOwnerScopeConfig` for config-driven owner mode.
- Defines a package config key via `$ownerScopeConfigKey` when config-driven.
- Uses `HasOwnerScopeKey` only for nullable-owner uniqueness.
- Hides `owner_scope` from serialization.
- Does not expose `owner_scope` in forms, tables, exports, fillable arrays, or public docs.
- Uses `getTable()` from config instead of `protected $table`.
- Uses application-level cascade behavior, not database cascades.

Search:

```bash
rg -n -- "use HasOwner|ownerScopeConfig|owner_scope|protected \\\$table" packages/<pkg>/src
```

## Migration audit

For tenant-owned tables:

- Primary keys use `uuid('id')->primary()`.
- Owner columns use `$table->nullableMorphs('owner')` or `$table->nullableUuidMorphs('owner')`.
- Foreign keys use `foreignUuid()` only.
- No `constrained()`, no `cascadeOnDelete()`, no database-level foreign-key constraints.
- Nullable-owner uniqueness uses `owner_scope` if needed.

Search:

```bash
rg -n -- "nullable.*Morphs\('owner'\)|owner_scope|constrained\(|cascadeOnDelete\(" packages/<pkg>/database
```

## Query audit

Every read path touching tenant-owned data must be owner-safe.

Search:

```bash
rg -n -- "::query\(|->query\(|getEloquentQuery\(" packages/<pkg>/src
rg -n -- "count\(|sum\(|avg\(|exists\(" packages/<pkg>/src
rg -n -- "DB::table\(" packages/<pkg>/src
rg -n -- "withoutOwnerScope\(|withoutGlobalScope\(.*Owner" packages/<pkg>/src
```

Review expectations:

- Eloquent models with enabled `HasOwner` should rely on the global scope for normal reads.
- Explicit owner selection should use `forOwner($owner, includeGlobal: false)`.
- Global-only reads should use `globalOnly()`.
- Raw query-builder paths must call `OwnerQuery::applyToQueryBuilder()`.
- Aggregate queries must be scoped the same as list/detail queries.
- Any `withoutOwnerScope()` use must be intentional, documented, and surrounded by explicit owner iteration or system context.

## Write and submitted-ID audit

Any inbound foreign ID must be revalidated server-side.

Examples:

- `location_id`
- `order_id`
- `batch_id`
- `category_id`
- related record IDs in `sync()` calls
- bulk action record IDs

Preferred pattern:

```php
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;

$category = OwnerWriteGuard::findOrFailForOwner(
    Category::class,
    $categoryId,
    includeGlobal: false,
);
```

A scoped select option list is useful UX, not authorization.

## Route binding audit

Route model binding must not resolve cross-owner records.

Search:

```bash
rg -n -- "Route::.*\{.*\}|Route::bind|resolveRouteBinding" packages/<pkg>/routes packages/<pkg>/src
```

Preferred pattern:

```php
use AIArmada\CommerceSupport\Support\OwnerRouteBinding;

OwnerRouteBinding::bind('product', Product::class);
```

Use `includeGlobal: true` only when global rows are explicitly readable from tenant context.

## Filament audit

Filament tenancy is not sufficient. Audit resources, pages, relation managers, widgets, actions, imports, and exports.

Required checks:

- `getEloquentQuery()` is owner-safe.
- Relation managers do not leak cross-owner records.
- Form selects scope option queries and action handlers revalidate submitted IDs.
- Table actions revalidate records before mutation.
- Bulk actions revalidate every selected record.
- Widgets use the same owner scope as resources.
- Exports/imports apply owner context on every query and write.

Search:

```bash
rg -n -- "getEloquentQuery\(|Select::make\(|relationship\(|->action\(|BulkAction|Export|Import|Widget" packages/filament-<pkg>/src
```

## Non-request surfaces

Jobs, commands, schedules, reports, exports, health checks, and webhook processors must not rely on ambient web auth.

Expected patterns:

- Pass owner IDs into jobs.
- Reconstruct owner models with `OwnerContext::fromTypeAndId()` where needed.
- Iterate all owners with an explicit opt-out, then enter `OwnerContext::withOwner($owner, ...)` for owner-scoped work.
- Use `OwnerContext::withOwner(null, ...)` only for intentional global records.
- Treat `OwnerContext::setForRequest()` as HTTP-only framework integration API; do not call it from jobs/commands/other non-request surfaces.

Search:

```bash
rg -n -- "handle\(|schedule|ShouldQueue|Command|Export|Report|HealthCheck|Webhook" packages/<pkg>/src
```

## Targeting audit

Packages using `TargetingEngine` must store and evaluate valid targeting arrays.

Expected shape:

```php
$targeting = [
    'mode' => 'all',
    'rules' => [
        ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
    ],
];
```

Custom expression shape:

```php
$targeting = [
    'mode' => 'custom',
    'expression' => [
        'and' => [
            ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
            ['type' => 'user_segment', 'operator' => 'in', 'values' => ['vip']],
        ],
    ],
];
```

Audit checks:

- `validate($targeting)` runs before storing admin-authored rules.
- Non-empty invalid targeting does not grant eligibility.
- Empty targeting is used only for intentional “no restrictions”.
- Non-custom modes (`all` / `any`) use a present, non-empty `rules` array; missing/empty `rules` is invalid and fails closed.
- Consumer docs use current operator names for each evaluator.
- Custom evaluators implement `TargetingRuleEvaluator::validate()` and fail closed.

Search:

```bash
rg -n -- "TargetingEngine|targeting|evaluate\(|validate\(" packages/<pkg>/src packages/<pkg>/docs tests/src/<Pkg>
```

## Webhook audit

Webhook integrations must use concrete validators and processors.

Required checks:

- A concrete signature validator extends `CommerceSignatureValidator` or implements Spatie's validator directly.
- `signing_secret` is required in production.
- The validator rejects missing signatures, invalid signatures, and empty secrets.
- Processors extend `CommerceWebhookProcessor` and implement `processEvent(string $eventType, array $payload): void`.
- Processing is idempotent at webhook-call level (row lock + `processed_at` guard) and provider-event level (cross-row dedupe when event IDs are present).
- Cross-row dedupe matches strictly by event type when the payload carries one (`event_type`, `event`, or `type`); type-less rows dedupe only against other type-less rows with the same event ID. Same event ID + different type = two distinct events, both processed.
- Owner context is resolved explicitly before tenant-owned writes.
- Raw payloads are preserved by Spatie's `WebhookCall` model.

Search:

```bash
rg -n -- "Webhook|SignatureValidator|CommerceWebhookProcessor|Route::webhooks" packages/<pkg>/src packages/<pkg>/routes tests/src/<Pkg>
```

## Health check audit

Health checks should be fast, scoped, and safe.

Required checks:

- Concrete checks extend `CommerceHealthCheck` and implement `performCheck()`.
- Do not override `run()` because the base class owns exception-to-failed conversion.
- Tenant-owned health checks either run per owner with explicit context or report system-level checks only.
- External calls have short timeouts.
- Health widgets/resources are access-controlled.

## Test requirements

Every owner-enabled package should include tests for:

- owner-only reads,
- include-global reads,
- global-only reads,
- missing owner fail-fast,
- cross-owner writes rejected,
- global row write protection,
- query-builder owner scoping,
- route binding owner scoping,
- nested `OwnerContext::withOwner()` restoration,
- Filament submitted-ID validation where applicable,
- non-request owner context for jobs/commands/webhooks,
- targeting invalid-rule fail-closed behavior if targeting is used,
- webhook cross-row dedupe: same event_id + same type deduped, same event_id + different type both processed, no event_id means row-level only.

Run package-scoped tests only:

```bash
./vendor/bin/pest tests/src/<Package> --parallel
./vendor/bin/phpstan analyse packages/<pkg>/src --level=6
```

## Reporting template

Use this structure for package audit reports:

```md
---
title: <Package> Commerce Support Audit
---

# <Package> Commerce Support Audit

## Scope

## Summary

| Severity | Finding | Status |
| --- | --- | --- |

## Findings

### <Severity>: <Title>

**Files:**

**Impact:**

**Recommendation:**

**Verification:**

## Follow-ups
```

---

## Implementation Plan: Isolation Primitives (Q3 2026)

**Locked decision (grilled 2026-04-28):** Build isolation helpers in `commerce-support` without cross-package changes.

### Five primitives to build (v1)

1. **`OwnerCache`** — owner-scoped cache key builder
   - Enforces `owner:{ownerScopeKey}:{logicalKey}` pattern
   - Prevents cache cross-tenant bleed
    - Uses tagged owner groups on tag-capable drivers; `forgetOwner()` is intentionally a no-op on stores without tags

2. **`OwnerFilesystem`** — owner-scoped artifact path builder
   - Enforces `owners/{ownerScopeKey}/...` structure
   - Guards downloads/serving via owner-checked resolver
    - Accepts Eloquent owners or `OwnerScopeIdentifiable` adapters

3. **`OwnerContextJob` trait** — auto-enters owner context for queued jobs
   - Requires `owner_type`/`owner_id` in job payload
   - Fails closed if owner missing when owner mode enabled
   - Prevents queue cross-tenant leakage

4. **`OwnerIdentificationMiddleware` base middleware** — app-level request identification hook
   - Service provider binds resolver (static + known)
   - Middleware identifies owner from domain/auth/header at request time
   - Resolves before any owner-scoped queries

5. **Updated documentation** — 08-webhooks, 04-multi-tenancy, 10-traits-utilities
   - Usage examples for each primitive
   - Integration patterns for optional adoption

### What is deferred

- **Provisioning pipeline** — only needed when enabling multitenancy in packages; skip v1
- **Package retrofits** — primitives are optional/advisory; packages adopt on their own timeline

### Implementation sequence

1. `OwnerCache` (simplest, no side effects)
2. `OwnerFilesystem` (next, also isolated)
3. `OwnerContextJob` trait (touches job lifecycle)
4. `OwnerIdentificationMiddleware` base middleware (defines request-time hook)
5. Documentation + comprehensive tests

**Estimated effort:** 1–2 weeks, commerce-support only.

### Validation (required before merge)

- All primitives pass unit tests in `tests/src/Support`
- All primitives have complete PHPDoc + usage examples
- PHPStan level 6 clean
- Test coverage ≥80%

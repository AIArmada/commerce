---
title: Owner Multitenancy Hardening Plan
---

# Owner Multitenancy Hardening Plan

This playbook standardizes owner scoping across Commerce packages after the `commerce-support` owner foundation hardening.

For package-by-package review, use the broader [Commerce Support Consumer Audit Standard](commerce-support-consumer-audit-standard.md). It expands this owner checklist to include targeting, webhooks, health checks, Filament actions, and non-request surfaces.

## Goals

- Make `commerce-support` the single source of truth for owner context, scopes, route binding, write guards, and nullable-owner uniqueness helpers.
- Ensure every tenant-owned read/write path is owner-safe.
- Remove public `owner_key` patterns in favor of hidden internal uniqueness helpers.
- Make global row semantics explicit and long-lived.

## Core semantics

- Tenant ownership uses `owner_type` and `owner_id` only.
- `owner_scope` is an internal hidden uniqueness helper, not public API and not an authorization boundary.
- `forOwner($owner)` returns owner-only rows.
- `forOwner($owner, includeGlobal: true)` returns owner rows plus global rows.
- `globalOnly()` returns only ownerless rows.
- Missing owner context fails fast when owner mode is enabled.
- `commerce-support.owner.enabled=true` fails closed unless `OwnerResolverInterface` resolves through a concrete resolver instead of `NullOwnerResolver`.
- `OwnerContext::withOwner(null, ...)` is the explicit global context.
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

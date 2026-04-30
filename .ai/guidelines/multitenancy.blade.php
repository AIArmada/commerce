# Multitenancy Guidelines

## Monorepo Contract
- **Boundary is mandatory**: Every package that stores tenant-owned data MUST define an explicit tenant boundary and enforce it on **every** read/write path.
- **Single source of truth**: Multi-tenancy primitives live in `commerce-support`.
- **No UI trust**: Filament form options are not security. Always validate on the server.
- **Column semantics**: `owner_type/owner_id` is the default tenant boundary tuple. If a package customizes the column names, it MUST still preserve the same semantics through `HasOwner` / `ownerScopeConfig()` and MUST NOT reuse the boundary columns for unrelated meaning.

## Runtime Ground Truth (`commerce-support`)
- **Owner mode switch**: owner enforcement is activated by `commerce-support.owner.enabled`.
- **Resolver contract**: bind `AIArmada\CommerceSupport\Contracts\OwnerResolverInterface` to resolve the current owner. When owner mode is enabled, using `NullOwnerResolver` is invalid and should fail fast.
- **Owner context API**: use `OwnerContext::withOwner($owner, fn () => ...)` for scoped work and `OwnerContext::withOwner(null, fn () => ...)` for explicit global work.
- **HTTP-only override**: use `OwnerContext::setForRequest()` only from middleware/framework integrations. Do **not** use it as a generic escape hatch.
- **Request protection**: use `AIArmada\CommerceSupport\Middleware\NeedsOwner` on routes/surfaces that must not proceed without a resolved owner.

## Design defaults (align ecosystem behavior)
- **Default enforcement**: models using `HasOwner` get a global `OwnerScope` when their owner scoping config is enabled.
- **Default include-global**: `false` unless explicitly required by business rules.
- **Meaning of `owner = null`**: treat as **global-only** records, not “all owners”.
- **Cross-tenant/system operations**: allowed only when the call site uses an explicit, greppable opt-out.
- **Explicit global is first-class**: code that reads or mutates global rows must enter explicit global context instead of relying on a missing owner.

## Data Model (Required)
- **Migration**: `$table->nullableMorphs('owner')` for tenant-owned tables.
- **Model**: `use HasOwner` (from `commerce-support`).
- **Config-backed models**: when scoping is package-configurable, implement `ownerScopeConfig()` via `HasOwnerScopeConfig` and set the package config key.
- **Provider**: Bind `OwnerResolverInterface` to resolve current owner context.
- **Non-tenant "ownership"** (wallet holder, payee, actor, etc.) MUST use different column names/relationships.

## Model Semantics (Non-negotiable)
- **Reads require context**: owner-scoped reads require either a resolved owner or explicit global context.
- **Persisted owner tuple is immutable**: after creation, owned rows MUST NOT be promoted, demoted, or reassigned by editing `owner_type` / `owner_id` directly.
- **Auto-assignment**: new owned rows may inherit the current owner automatically when `auto_assign_on_create` is enabled.
- **Global writes are privileged**: mutating persisted global rows requires explicit global context.
- **Unsaved exceptions only**: helper methods like `removeOwner()` are only safe on unsaved/new models.

## Enforcement (Default-on)
- Tenant-owned `HasOwner` models SHOULD rely on commerce-support's `OwnerScope` rather than package-local ad hoc scoping logic.
- **Escape hatch**: intentionally cross-tenant/system operations MUST use an explicit, greppable opt-out (e.g. `->withoutOwnerScope()` / `withoutGlobalScope(OwnerScope::class)`).
- **Scoped lookup helper**: for inbound IDs on write paths, prefer `OwnerWriteGuard::findOrFailForOwner()` or `ResolveOwnedModelOrFailAction` over hand-rolled checks.
- **Non-request surfaces** (jobs/commands/schedules): MUST NOT rely on ambient web auth. Pass/iterate owner explicitly and apply owner context via `OwnerContext::withOwner(...)` or the job helpers below.

## Query Rules (Non-negotiable)
- **Reads**: Must be owner-enforced on every surface (UI + non-UI). Use the default `HasOwner` global scope whenever possible; use `Model::forOwner($owner)` when intentionally selecting an owner context and/or explicitly including global rows.
- **Writes**: Any inbound foreign IDs (e.g., `location_id`, `order_id`, `batch_id`) MUST be validated as belonging to the current owner scope before attach/update.
- **Query builder**: `DB::table(...)` paths touching tenant-owned data MUST apply `OwnerQuery::applyToQueryBuilder(...)` (Eloquent global scopes do not apply).
- **Global rows**: If a package supports global rows, provide clear semantics:
  - `Model::forOwner($owner)` (owner-only)
  - `Model::globalOnly()` (global-only)
  - Optional: include-global behavior must be explicit and consistent.
- **Unscoped Eloquent is opt-out only**: if you remove the global scope, immediately reapply scoping intentionally (`forOwner`, `globalOnly`, `OwnerQuery`, etc.) unless the operation is truly cross-tenant by design.

## Filament Rules
- **Resources**: Ensure `getEloquentQuery()` is owner-safe (default-on scope or explicit scoping). Don’t rely solely on `$tenantOwnershipRelationshipName` or UI filters.
- **Actions**: Validate IDs again inside `->action()` handlers (defense-in-depth), preferably with `OwnerWriteGuard` / `ResolveOwnedModelOrFailAction`.
- **Relationship selects**: Scope option queries and validate submitted IDs.
- **Widgets / badges / aggregates**: any `count()`, `sum()`, `exists()`, or stats query must be owner-scoped too.

## Routing & Binding
- Route-model binding/download routes MUST NOT resolve cross-tenant rows for `HasOwner` models.
- Prefer hardened binding patterns where applicable (e.g., `OwnerRouteBinding::bind(...)`, an owner-safe query, or `ResolveOwnedModelOrFailAction`).

## Non-UI Surfaces
- Console commands, queued jobs, scheduled tasks, exports, reports, health checks, and webhooks MUST apply the same owner scoping as HTTP/Filament.
- **Jobs**: prefer `OwnerContextJob` for queued jobs, or implement `OwnerScopedJob` when you need an explicit owner payload contract.
- **Commands / batch processing**: iterate owners explicitly and wrap each unit of work in `OwnerContext::withOwner(...)`.
- Jobs/commands MUST NOT rely on ambient web auth to resolve owner; pass/iterate owner explicitly and apply owner context via the standard override mechanism.

## Shared Infrastructure Rules
- **Cache**: use `OwnerCache` for tenant-sensitive cache keys; never share raw cache keys across owners.
- **Filesystem**: use `OwnerFilesystem` for tenant-sensitive file storage paths; never concatenate raw `owner_id` paths manually.
- **Owner scope keys**: when a package needs stable owner-specific cache/file prefixes, use `OwnerScopeKey` rather than inventing a package-local format.

## Verification (Required)
- Add at least one cross-tenant regression test proving reads are isolated and writes throw/abort.
- For `HasOwner` models, prefer reusing `commerce-support`'s `OwnerScopingContractTests` where practical.
- Grep for unscoped entrypoints:
  - `rg -n -- "::query\(|->query\(|getEloquentQuery\(" packages/<pkg>/src`
  - `rg -n -- "count\(|sum\(|avg\(|exists\(" packages/<pkg>/src`
  - `rg -n -- "DB::table\(" packages/<pkg>/src`
  - `rg -n -- "Route::.*\{.*\}" packages/<pkg>/routes`
  - `rg -n -- "withoutOwnerScope\(|withoutGlobalScope\(.*Owner" packages/<pkg>/src`
- Grep for non-standard tuple usage and confirm it is intentional/configured:
  - `rg -n -- "owner_type|owner_id|owner_type_column|owner_id_column" packages/<pkg>/src packages/<pkg>/config packages/<pkg>/database`

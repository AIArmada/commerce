# Multitenancy Guidelines

## Boundary and Trust
- Every package that stores tenant-owned data must define an explicit tenant boundary and enforce it on every read and write path.
- Multi-tenancy primitives live in `commerce-support`.
- Filament form options are not security. Always validate on the server.
- `owner_type` / `owner_id` is the default tenant boundary tuple. If a package customizes the column names, it must preserve the same semantics through `HasOwner` and `ownerScopeConfig()`, and it must not reuse the boundary columns for unrelated meaning.

## Runtime Ownership
- Owner enforcement is activated by `commerce-support.owner.enabled`.
- Bind `AIArmada\CommerceSupport\Contracts\OwnerResolverInterface` to resolve the current owner. When owner mode is enabled, using `NullOwnerResolver` is invalid and should fail fast.
- Use `OwnerContext::withOwner($owner, fn () => ...)` for scoped work and `OwnerContext::withOwner(null, fn () => ...)` for explicit global work.
- Use `OwnerContext::setForRequest()` only from middleware or framework integrations. Do not use it as a generic escape hatch.
- Use `AIArmada\CommerceSupport\Middleware\NeedsOwner` on routes or surfaces that must not proceed without a resolved owner.

## Data Model
- Tenant-owned tables use `$table->nullableMorphs('owner')`.
- Tenant-owned models use `HasOwner` from `commerce-support`.
- When scoping is package-configurable, implement `ownerScopeConfig()` via `HasOwnerScopeConfig` and set the package config key.
- Non-tenant ownership concepts such as wallet holder, payee, or actor must use different column names and relationships.

## Ownership Semantics
- Owner-scoped reads require either a resolved owner or explicit global context.
- Persisted owner tuples are immutable after creation. Owned rows must not be promoted, demoted, or reassigned by editing `owner_type` or `owner_id` directly.
- New owned rows may inherit the current owner automatically when `auto_assign_on_create` is enabled.
- Global writes are privileged. Mutating persisted global rows requires explicit global context.
- Helper methods such as `removeOwner()` are only safe on unsaved or new models.
- Default enforcement is a global `OwnerScope` when the model's owner scoping config is enabled.
- Default include-global is `false` unless business rules require otherwise.
- `owner = null` means global-only records, not "all owners".
- Cross-tenant or system operations are allowed only when the call site uses an explicit, greppable opt-out.
- Code that reads or mutates global rows must enter explicit global context instead of relying on a missing owner.

## Query And Write Enforcement
- Tenant-owned `HasOwner` models should rely on `commerce-support`'s `OwnerScope` rather than package-local ad hoc scoping logic.
- Intentionally cross-tenant work must use an explicit opt-out such as `->withoutOwnerScope()` or `withoutGlobalScope(OwnerScope::class)`.
- For inbound IDs on write paths, prefer `OwnerWriteGuard::findOrFailForOwner()` or `ResolveOwnedModelOrFailAction` over hand-rolled checks.
- Reads must be owner-enforced on every surface, UI and non-UI alike. Use `Model::forOwner($owner)` when intentionally selecting an owner context or including global rows, and use `Model::globalOnly()` for global-only records.
- Any inbound foreign IDs such as `location_id`, `order_id`, or `batch_id` must be validated as belonging to the current owner scope before attach or update.
- `DB::table(...)` paths touching tenant-owned data must apply `OwnerQuery::applyToQueryBuilder(...)` because Eloquent global scopes do not apply.
- If a package supports global rows, include-global behavior must be explicit and consistent.
- If you remove the global scope, immediately reapply scoping intentionally (`forOwner`, `globalOnly`, `OwnerQuery`, and so on) unless the operation is truly cross-tenant by design.

## HTTP, Filament, And Background Work
- Filament resources must return owner-safe queries from `getEloquentQuery()`.
- Validate IDs again inside `->action()` handlers for defense in depth, preferably with `OwnerWriteGuard` or `ResolveOwnedModelOrFailAction`.
- Scope relationship option queries and validate submitted IDs.
- Widgets, badges, counts, sums, exists checks, and other aggregates must be owner-scoped.
- Route model binding and download routes must not resolve cross-tenant rows for `HasOwner` models.
- Prefer hardened binding patterns where applicable, such as `OwnerRouteBinding::bind(...)`, an owner-safe query, or `ResolveOwnedModelOrFailAction`.
- Console commands, queued jobs, scheduled tasks, exports, reports, health checks, and webhooks must apply the same owner scoping as HTTP and Filament.
- Prefer `OwnerContextJob` for queued jobs, or implement `OwnerScopedJob` when you need an explicit owner payload contract.
- Commands and batch processing should iterate owners explicitly and wrap each unit of work in `OwnerContext::withOwner(...)`.
- Jobs and commands must not rely on ambient web auth to resolve the owner.

## Shared Infrastructure
- Use `OwnerCache` for tenant-sensitive cache keys. Never share raw cache keys across owners.
- Use `OwnerFilesystem` for tenant-sensitive file storage paths. Never concatenate raw `owner_id` paths manually.
- Use `OwnerScopeKey` when a package needs stable owner-specific cache or file prefixes.

## Verification
- Add at least one cross-tenant regression test proving reads are isolated and writes throw or abort.
- For `HasOwner` models, prefer reusing `commerce-support`'s `OwnerScopingContractTests` where practical.
- Grep for unscoped entry points:
  - `rg -n -- "::query\(|->query\(|getEloquentQuery\(" packages/<pkg>/src`
  - `rg -n -- "count\(|sum\(|avg\(|exists\(" packages/<pkg>/src`
  - `rg -n -- "DB::table\(" packages/<pkg>/src`
  - `rg -n -- "Route::.*\{.*\}" packages/<pkg>/routes`
  - `rg -n -- "withoutOwnerScope\(|withoutGlobalScope\(.*Owner" packages/<pkg>/src`
- Grep for non-standard tuple usage and confirm it is intentional and configured:
  - `rg -n -- "owner_type|owner_id|owner_type_column|owner_id_column" packages/<pkg>/src packages/<pkg>/config packages/<pkg>/database`

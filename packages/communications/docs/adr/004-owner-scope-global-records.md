# ADR 004: Owner Scope and Global-Record Semantics

**Status:** Accepted

**Context:** Tenant-owned data must be isolated. Every read and write path in the communications package must respect the current owner context. Some records (system templates, shared configurations) are intentionally global.

**Decision:**

1. **Owner boundary** — tenant-owned tables use `nullableMorphs('owner')`. Models use `HasOwner` from `commerce-support` with `HasOwnerScopeConfig` for package-configurable scoping.
2. **OwnerContext** — `OwnerContext::withOwner($owner, fn () => ...)` scopes a block of work to a specific owner. `withOwner(null, ...)` is explicit global context. `setForRequest()` is middleware-only.
3. **OwnerScope** — a global Eloquent scope on `HasOwner` models filters queries to the resolved owner. Default `include_global` is `false` — only matching owner rows are visible unless explicitly opted in.
4. **Global records** — `owner = null` means global-only. Global writes require explicit `OwnerContext::withOwner(null, ...)`. Helper methods like `removeOwner()` are only safe on unsaved/new models — persisted owner tuples are immutable.
5. **Cross-tenant operations** — must use an explicit grep-able opt-out: `->withoutOwnerScope()` or `withoutGlobalScope(OwnerScope::class)`. `OwnerQuery::applyToQueryBuilder(...)` applies the same scoping to raw `DB::table(...)` queries.
6. **ID validation** — inbound foreign IDs on write paths use `OwnerWriteGuard::findOrFailForOwner()` or `ResolveOwnedModelOrFailAction` to prevent cross-tenant reference attachment.
7. **Shared infrastructure** — `OwnerCache` for tenant-sensitive cache keys, `OwnerFilesystem` for storage, `OwnerScopeKey` for stable prefixes.
8. **Filament** — Resources return owner-safe queries from `getEloquentQuery()`. IDs are re-validated inside `->action()` handlers.

**Consequences:** Tenant isolation is enforced at the query layer, not the UI layer. Accidental cross-tenant data leaks are prevented. Global records are explicit and require intentional context. Background jobs restore owner context through `OwnerContextJob` or explicit `OwnerContext::withOwner(...)` wrapping.

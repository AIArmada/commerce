# authz Audit

## Packages Reviewed (bullets)

- `packages/authz` — Spatie Permission wrapper: teams/scopes, wildcard permissions, impersonation, sync/super-admin commands (24 `src/` files, `config/authz.php`, 2 migrations, no routes dir listing, `src/helpers.php`)
- `packages/filament-authz` — Filament adapter: plugin, Role/Permission/User resources, discovery service, policy generator, impersonation actions/middleware (36 `src/` files, `config/filament-authz.php`, `routes/web.php`)
- Root test coverage consulted: `tests/src/Authz/` (3 files — thinnest core coverage in the set), `tests/src/FilamentAuthz/` (19 files), `tests/src/FilamentAuthzScoped/` (1 file)

## Overall Assessment (quality, health, risks, refactor size)

authz is a capable Spatie wrapper (teams resolver, wildcard matching with cache, scope teams, impersonation with tenant guard, Octane listeners, scoped bindings for `AuthzScopeContext`/`WildcardPermissionCache`). The highest-severity items are narrow rather than systemic: the one over-broad scope strip (`PermissionResource::getEloquentQuery()->withoutGlobalScopes()`) is mitigated by an accurate comment (Spatie permissions are global; only roles carry `team_id`) but still strips more than it names; the filament `Authz` discovery service holds per-panel caches on a singleton; and the package's own models live in commerce-support instead of here. Filament tenancy-is-not-security posture is otherwise decent (server-side `can*` gates, `ImpersonationScopeGuard` on the user query). The glaring gap is test depth: 3 root tests for the security-critical core. No schema migration required. Refactor size: Small-Medium, code-only.

## Migration Impact

**Migration Required: NO**

| Table | Column/Index/Constraint | Data migration | Notes |
|---|---|---|---|
| `roles`, `permissions`, `model_has_*`, `role_has_permissions`, `authz_scopes` | none proposed | none | uuid PKs on owned tables verified; Spatie pivot shape unchanged; no FK constraints found (rg clean) |
| none | no `down()` removal required | none | Existing `down()` methods in the two authz migrations are harmless; guideline requires no `down()`, it does not forbid it — leave as-is |

## Package Responsibilities

- Spatie Permission bootstrapping for the monorepo (`AuthzServiceProvider::configureSpatiePermissions()`, team resolver, `OwnerContextTeamResolver` wiring).
- Permission-key standard (`Services/PermissionKeyBuilder.php`), wildcard resolution (`WildcardPermissionResolver` + `WildcardPermissionCache`), scope context/teams (`Support/AuthzScopeContext.php`, `AuthzScopeResolver`, `AuthzScopeTeamResolver`).
- Impersonation (`Services/ImpersonateManager.php`, `Guard/SessionGuard.php`, `Support/ImpersonationScopeGuard.php`, controllers/middleware in filament adapter, `Concerns/CanBeImpersonated.php`).
- Tenancy bridge for user/role assignment (`Concerns/HasAuthzScope.php`, `Concerns/ScopesAuthzTenancy.php`, `Concerns/SyncsRolePermissions.php`, `Middleware/SyncAuthzTenant.php`).
- Operator tooling (`SuperAdminCommand`, `SyncAuthzCommand`, `filament-authz` `DiscoverCommand`/`GeneratePoliciesCommand`/`SeederCommand`, `Services/EntityDiscoveryService.php`, `Authz` discovery in filament adapter).

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)

### A1 — Domain models live in commerce-support, not in authz
- Severity: High
- Location: `packages/commerce-support/src/Models/Role.php`, `Models/Permission.php`, `Models/AuthzScope.php`; wired in `packages/authz/src/AuthzServiceProvider.php:16-17`
- Problem: authz is a domain package whose entities are owned by the foundation. Authz cannot change its own storage without a foundation release; foundation consumers inherit authz tables.
- Why It Matters: Wrong ownership boundary; see commerce-support audit A1 for the full case.
- Recommended Fix: Create `AIArmada\Authz\Models\{Role,Permission,AuthzScope}` in this package (move, not fork), re-point `configureSpatiePermissions()` and `Support/FilamentPermission.php`; leave no aliases beyond one release. Agreed direction with commerce-support audit A1 (cross-checked 2026-09-07, both files prescribe commerce-support → authz).
- Breaking Change: YES
- Affected Packages: commerce-support, filament-authz, events (`SyncManagementAssignmentToAuthzAction`), organizations (`DefaultOrganizationAuthorization`), membership (`MembershipRoleSyncService`)
- Required Dependent Changes: update all `CommerceSupport\Models\(Role|Permission|AuthzScope)` imports repo-wide (verified hits in authz provider, commerce-support `FilamentPermission`, plus re-grep before merge)
- Migration Required: NO

### A2 — `PermissionResource` strips all global scopes instead of naming the opt-out
- Severity: Medium
- Location: `packages/filament-authz/src/Resources/PermissionResource.php:40-46` (`return parent::getEloquentQuery()->withoutGlobalScopes();`)
- Problem: The comment is correct (Spatie permissions are global; only roles carry team), but `withoutGlobalScopes()` removes every scope — including any future tenant, soft-delete-analog, or package scope — instead of the one scope being opted out of. The monorepo contract requires explicit, greppable opt-outs.
- Why It Matters: Over-broad strips are silent security holes: the next global scope added to the/model is silently disabled on this surface.
- Recommended Fix: Replace with the narrow opt-out actually meant: if the Spatie Permission model ever gains `OwnerScope`, use `->withoutGlobalScope(OwnerScope::class)`; today (no `HasOwner` on that model) replace the call with `parent::getEloquentQuery()` plus the existing comment, so the opt-out is documented without disabling unknown scopes.
- Breaking Change: NO
- Affected Packages: filament-authz only
- Required Dependent Changes: none
- Migration Required: NO

### A3 — Discovery caches live on a singleton (Octane staleness)
- Severity: Medium
- Location: `packages/filament-authz/src/Authz.php:20-23` (`$discoveryCache`, `$permissionCache`), bound in `packages/filament-authz/src/FilamentAuthzServiceProvider.php:31` as `singleton(Authz::class)`
- Problem: Per-panel discovery results cached in singleton instance state persist across Octane requests. There is a `clearCache()`-style reset at line ~146 but no Octane wiring in this package guarantees it runs per request.
- Why It Matters: Newly registered resources/permissions can vanish (or removed ones linger) across requests under Octane; permission-gated navigation then enforces stale policy.
- Recommended Fix: Bind as `$app->scoped(Authz::class)` (request-scoped, matching authz core's own `AuthzScopeContext`/`WildcardPermissionCache` scoped bindings) and add an Octane `RequestTerminated` flush calling the existing reset. Update no consumers (facade accessor unchanged).
- Breaking Change: NO
- Affected Packages: filament-authz
- Required Dependent Changes: none
- Migration Required: NO

### A4 — Two `Authz` facades with the same short name
- Severity: Low
- Location: `packages/authz/src/Facades/Authz.php` vs `packages/filament-authz/src/Facades/Authz.php` (accessing `AIArmada\Authz\Authz` vs `AIArmada\FilamentAuthz\Authz`)
- Problem: Two facades share the alias `Authz` in different namespaces. Any file importing both (or a developer auto-importing the wrong one) silently gets discovery methods mixed with enforcement methods.
- Why It Matters: Confusion at the security boundary: discovery (`getResources`) vs enforcement (`can`, scope checks) must never be mistaken for each other.
- Recommended Fix: Rename the filament one to `FilamentAuthz` facade (`AIArmada\FilamentAuthz\Facades\FilamentAuthz`, accessor unchanged) and update its docblock + internal references. Keep the core `AIArmada\Authz\Facades\Authz` as the only `Authz` facade.
- Breaking Change: YES
- Affected Packages: filament-authz, any host app importing `FilamentAuthz\Facades\Authz`
- Required Dependent Changes: update imports at those call sites (rg `FilamentAuthz\\Facades\\Authz` — verified internal-only: adapter `src/` + tests + package `README.md`/`docs/*.md`; update docs examples in the same pass)
- Migration Required: NO

## Code Quality Findings (same finding format)

### Q1 — `GeneratePoliciesCommand` emits `$user->can()` stubs without owner context
- Severity: Low
- Location: `packages/filament-authz/src/Console/GeneratePoliciesCommand.php:203,213`
- Problem: Generated policy templates return `$user->can('...')` with no owner-scoping guidance, teaching downstream authors that a bare permission check is sufficient on tenant data.
- Why It Matters: Generated code becomes the codebase's most-copied code; insecure templates scale insecurity.
- Recommended Fix: Change the stub to emit an owner-aware template (`OwnerWriteGuard::findOrFailForOwner(...)` or `->forOwner()` scoping comment) alongside the `can()` check.
- Breaking Change: NO
- Affected Packages: future generated policies in host apps
- Required Dependent Changes: none
- Migration Required: NO

## Laravel-Specific Findings

- PHP 8.4 (`"php": "^8.4"` in both composers) — compliant.
- No FK constraints/cascades in migrations or src (rg clean) — compliant.
- `AuthzServiceProvider` correctly uses `$app->scoped()` for request state (`AuthzScopeContext`, `WildcardPermissionCache`) and `singleton()` for stateless services — compliant; A3 asks only that the filament `Authz` discovery join the scoped pattern.
- Octane listeners registered (`registerOctaneListeners`, `RequestReceived`) — good; extend the same pattern to the A3 flush.
- Gate::before ordering: super-admin check runs before wildcard check; both return `null` (not `false`) on non-match so they compose rather than veto — correct. Note the super-admin bypass is global by design (documented `super_admin_role`); tenant isolation for super-admin tooling is enforced at the query layer (`ImpersonationScopeGuard`), not the gate — acceptable only because the guard exists; do not remove the guard.

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)

- Direction correct: filament-authz depends on authz + commerce-support; neither core package depends back.
- Resources (`RoleResource`, `PermissionResource`, `UserResource`) correctly read nested navigation config and implement server-side `canViewAny`/`checkAbility` gates — tenancy-is-not-security compliant at the gate layer.
- `UserResource::getEloquentQuery()` correctly routes through `ImpersonationScopeGuard::applyScopeToUserQuery()` — good defense-in-depth example other adapters should copy.
- `FilamentAuthzPlugin::navigationGroup()` builder writes into `filament-authz.navigation.group` config at runtime (lines ~666–667) — acceptable run-time override mechanics, but it mutates global config from a plugin instance; under Octane with multiple panels this leaks one panel's group into another. Fix with A3's request scoping (plugin already singleton at line 29 — make it scoped too).
- `EntityDiscoveryService` (singleton, line 30) holds no request state — verified stateless, keep as singleton.

## Database Findings

- uuid PKs on owned tables; Spatie pivot tables keep upstream shape — compliant, no change.
- No missing-index finding: permission/role lookups follow Spatie's indexed columns; `authz_scopes` table is tiny operator data.

## Model / Domain Findings

- After A1, models live here: `Role`/`Permission` extend Spatie models with team support; `AuthzScope` is the tenant-team record. Keep the `HasAuthzScope`/`ScopesAuthzTenancy`/`SyncsRolePermissions` concerns but document which is for host User models vs package models — today a reader cannot tell without reading all three.
- `WildcardPermissionResolver` + cache: sound; keep separator/case config (`authz.permissions.separator/case`) but add a boot-time assertion that separator is a single non-alphanumeric char (misconfiguration currently fails silently with over-matching wildcards).

## Security Findings

- Impersonation: `ImpersonateManager` + `SessionGuard` + `ImpersonateController::abort(403)` + `ImpersonationScopeGuard::canAccessTarget()` form a coherent chain; the guard's `shouldEnforceTenantScope()` falls back to unscoped when `registrar->teams` is off — this is correct only when teams are genuinely disabled; when `authz.scopes.enabled=true` but Spatie teams are off, the guard silently stops enforcing. Fix: make `shouldEnforceTenantScope()` return true when either Spatie teams OR `authz.scopes.enforce` is on, so the two flags cannot disagree into an open gate.
- `SyncAuthzTenant` middleware + `NeedsOwner` (commerce-support) ordering on Filament routes should be asserted in a test (panel request without owner → 403/redirect, not silent cross-tenant list).
- No mass-assignment issue found on resources (forms are explicit); no signed-URL surface in this package.

## Performance Findings

- `WildcardPermissionCache` is request-scoped — correct granularity; do not promote to shared cache (permissions change mid-request in tests/seeders; shared cache would need invalidation the package does not have).
- Discovery (`EntityDiscoveryService` + `Authz::getResources/Pages/Widgets`) reflects over panels per cache-miss; after A3 the miss rate rises slightly under Octane (per-request rebuild) — acceptable; if profiling later shows pain, cache by (panel, code-hash) with explicit invalidation, not unbounded singleton arrays.

## Testing Findings

- `tests/src/Authz/` has 3 files for the security-critical core — the worst coverage-to-risk ratio in this review set. `tests/src/FilamentAuthz/` (19) + `FilamentAuthzScoped` (1) cover the adapter better than the core.
- Required tests (add under `tests/src/Authz/`): super-admin `Gate::before` composition (super-admin passes, non-admin falls through to wildcard, unknown ability returns null); wildcard matching incl. separator/case misconfiguration assertion; `ImpersonationScopeGuard` cross-team denial (teams on, target in other team → `canAccessTarget false`, query excludes); `SyncAuthzTenant` + owner-less request behavior; A3 Octane rebuild test. Run: `./vendor/bin/pest --parallel tests/src/Authz tests/src/FilamentAuthz`.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)

| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| commerce-support | `FilamentPermission`, model imports | A1 move | Delete moved models; re-point to `AIArmada\Authz\Models\*` |
| events | `SyncManagementAssignmentToAuthzAction` → authz | A1 import move | Update model imports |
| organizations | `DefaultOrganizationAuthorization` → authz | A1 import move | Update model imports |
| membership | `MembershipRoleSyncService` → authz roles | A1 import move | Update model imports |
| host apps | `FilamentAuthz\Facades\Authz` | A4 rename | Import `Facades\FilamentAuthz` instead |

## Recommended Refactor Plan (ordered steps)

1. A1: move the three models into authz; update provider + `FilamentPermission`; repo-wide import sweep.
2. A2: narrow the `PermissionResource` scope strip.
3. A3: scope the filament `Authz` binding (+ plugin) per-request; add Octane flush.
4. Security hardening: unify `shouldEnforceTenantScope()` flags; add permission separator assertion.
5. A4: rename filament facade; update internal refs + tests.
6. Q1: owner-aware policy stubs.
7. Add the required tests above; verify with `./vendor/bin/pest --parallel tests/src/Authz tests/src/FilamentAuthz tests/src/FilamentAuthzScoped`.

## Files Likely to Change

- `packages/authz/src/AuthzServiceProvider.php`, `src/Support/ImpersonationScopeGuard.php`, `src/Services/PermissionKeyBuilder.php` (assertion), plus new `src/Models/{Role,Permission,AuthzScope}.php`
- `packages/filament-authz/src/Authz.php`, `src/FilamentAuthzServiceProvider.php`, `src/FilamentAuthzPlugin.php`, `src/Resources/PermissionResource.php`, `src/Console/GeneratePoliciesCommand.php`, `src/Facades/Authz.php` (rename)
- `packages/commerce-support/src/Support/FilamentPermission.php` (import updates), deleted `src/Models/{Role,Permission,AuthzScope}.php`

## Files / Code That Should Be Removed (explicit list, no legacy preservation)

- `packages/commerce-support/src/Models/Role.php`, `Models/Permission.php`, `Models/AuthzScope.php` (moved to authz — verified consumers: authz provider, `FilamentPermission`; re-grep `CommerceSupport\\Models\\(Role|Permission|AuthzScope)` repo-wide before deleting)
- `packages/filament-authz/src/Facades/Authz.php` (replaced by `Facades/FilamentAuthz.php`; verified no outside-package imports via rg `FilamentAuthz\\Facades\\Authz` — adapter-internal `src/` + tests + package `README.md`/`docs/*.md` examples, all updated same pass)
- `->withoutGlobalScopes()` in `PermissionResource::getEloquentQuery()` (replaced by narrow expression + comment)
- Nothing else: `scopeForOwner` duck-type checks stay until cashier/cashier-chip/contacting migrate in their own audits; `down()` methods stay (harmless)

## Final Recommended Architecture

authz owns its models, keys, wildcards, scopes, and impersonation end-to-end on top of Spatie; commerce-support keeps only the tenancy contract authz implements. All shared state is request-scoped with Octane flush; gates compose via null-fallthrough; impersonation is team-scoped whenever either teams or scope-enforcement is on; generated policies teach owner-aware checks by default.

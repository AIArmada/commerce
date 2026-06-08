# Filament Authz friendliness review

This note reviews `packages/filament-authz` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (3)
- `src/Concerns` (7)
- `src/Console` (5 commands + 1 concern)
- `src/Events` (2)
- `src/Guard/SessionGuard.php`
- `src/Http/` (Controllers + Middleware)
- `src/Middleware/SyncAuthzTenant.php`
- `src/Models` (3 — domain models in Filament package)
- `src/Services` (3)
- `src/Support` (4 — authz+owner resolvers/contexts)
- `src/Tables/Actions/ImpersonateTableAction.php`
- `FilamentAuthzPlugin.php`
- downstream in `commerce-support` (owner primitives), every Filament package

## What is already friendly

### Plugin is the entry point

- `FilamentAuthzPlugin.php`

Standard shape.

### Custom auth guard for impersonation

- `Guard/SessionGuard.php`

The guard pattern is the right way to implement impersonation.

### Console commands for permission management

- 5 commands for discover, generate policies, seeder, super admin, sync

Heavy command surface but well-scoped.

## Findings

### 1. `Models/Permission.php` and `Models/Role.php` are domain models in a Filament package

**Files**

- `src/Models/Permission.php`
- `src/Models/Role.php`
- `src/Models/AuthzScope.php`

**Why this hurts friendliness**

Eloquent models for the auth realm belong in the foundation, not in a Filament package. This is the "authz foundation" — but it lives in `filament-authz`.

**Recommendation**

Move `Permission`, `Role`, and `AuthzScope` to `commerce-support`. The Filament package consumes them.

### 2. 4 resolver/context classes for authz+owner likely overlap

**Files**

- `Support/AuthzScopeContext.php`
- `Support/AuthzScopeResolver.php`
- `Support/AuthzScopeTeamResolver.php`
- `Support/OwnerContextTeamResolver.php`

**Why this hurts friendliness**

4 classes for the same concern area (authz scope + owner team). The team-resolver responsibility should live in `commerce-support`, not duplicated here.

**Recommendation**

Move `OwnerContextTeamResolver` and possibly `AuthzScopeTeamResolver` to `commerce-support`. The authz-specific ones stay here.

### 3. `Resources/RoleResource/Concerns/HasAuthzFormComponents.php` is 613 lines

**Files**

- `src/Resources/RoleResource/Concerns/HasAuthzFormComponents.php` (613 lines)

**Why this hurts friendliness**

A 613-line concern nested under a Resource. Mixing concern namespace with resource namespace. Strongly suggests a refactor-in-progress.

**Recommendation**

Split into smaller concerns (e.g., `HasRoleFormFields`, `HasPermissionFormFields`, `HasRoleValidationRules`). Or move to `Forms/Components/` namespace.

### 4. 7 concerns at top-level likely overlap

**Files**

- `Concerns/HasAuthzScope.php`
- `Concerns/HasPageAuthz.php`
- `Concerns/HasWidgetAuthz.php`
- `Concerns/HasPanelAuthz.php`
- `Concerns/CanBeImpersonated.php`
- `Concerns/ScopesAuthzTenancy.php`
- `Concerns/SyncsRolePermissions.php`

**Why this hurts friendliness**

7 traits for one concern area. `HasPageAuthz`, `HasWidgetAuthz`, `HasPanelAuthz` look like 3 separate traits for the same authorization check across different Filament surfaces.

**Recommendation**

Consider a single `HasAuthzChecks` trait with config or a single `AuthorizesAuthz` interface. Or, document the deliberate split.

### 5. `Middleware/SyncAuthzTenant.php` is global middleware

**Files**

- `src/Middleware/SyncAuthzTenant.php`

**Why this hurts friendliness**

Global middleware-level tenant sync is an Octane-safety risk. If the middleware leaks state between requests, Octane workers will cross-contaminate.

**Recommendation**

Verify the middleware restores state after the request. Use `OwnerContext::setForRequest(...)` only inside request boundaries.

### 6. `Facades/Authz.php` + `Authz.php` (class) + `helpers.php` = 3 access patterns

**Files**

- `src/Facades/Authz.php`
- `src/Authz.php`
- `src/helpers.php`

**Why this hurts friendliness**

3 access patterns for the same API. Callers have to choose.

**Recommendation**

Pick one canonical access pattern (typically the Facade). Document the others as deprecated.

### 7. Impersonation is a full feature in a Filament package

**Files**

- `Events/TakeImpersonation.php`, `LeaveImpersonation.php`
- `Http/Controllers/ImpersonateController.php`, `LeaveImpersonationController.php`
- `Http/Middleware/ImpersonationBannerMiddleware.php`
- `Guard/SessionGuard.php`
- `Tables/Actions/ImpersonateTableAction.php`
- `Concerns/CanBeImpersonated.php`

**Why this hurts friendliness**

Impersonation is bigger than UI. It includes a custom guard, controllers, middleware, events, and a model concern. This belongs in a domain package.

**Recommendation**

Move impersonation to a domain package (or `commerce-support`). The Filament package consumes.

### 8. `Tables/Actions/ImpersonateTableAction.php` lives at top-level `Tables/Actions/`

**Files**

- `src/Tables/Actions/ImpersonateTableAction.php`

**Why this hurts friendliness**

A Table action at the top level of `Tables/Actions/` is structurally odd. Most packages put Table actions inside a Resource.

**Recommendation**

Move inside the appropriate Resource (likely `UserResource/Tables/Actions/`) or keep but document.

## Concrete refactor plan

### Phase 1 — move domain models to foundation

**Steps**

1. Move `Models/Permission.php`, `Models/Role.php`, `Models/AuthzScope.php` to `commerce-support`.
2. Re-import in `filament-authz`.

### Phase 2 — consolidate resolver/context classes

**Steps**

1. Move `OwnerContextTeamResolver` to `commerce-support`.
2. Audit `AuthzScopeTeamResolver` for the same.

### Phase 3 — split `HasAuthzFormComponents`

**Steps**

1. Split into smaller concerns.
2. Move to a `Forms/Components/` namespace.

### Phase 4 — move impersonation to domain

**Steps**

1. Move impersonation controllers, middleware, events, guard to a domain package.
2. Filament package consumes.

### Phase 5 — verify Octane safety

**Steps**

1. Audit `Middleware/SyncAuthzTenant` for request boundary handling.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — move domain models to foundation

- [pending] Move `Models/Permission.php`, `Models/Role.php`, `Models/AuthzScope.php` to `commerce-support`.
- [pending] Re-import in `filament-authz`.

### Phase 2 — consolidate resolver/context classes

- [pending] Move `OwnerContextTeamResolver` to `commerce-support`.
- [pending] Audit `AuthzScopeTeamResolver` for the same.

### Phase 3 — split `HasAuthzFormComponents`

- [pending] Split into smaller concerns.
- [pending] Move to a `Forms/Components/` namespace.

### Phase 4 — move impersonation to domain

- [pending] Move impersonation controllers, middleware, events, guard to a domain package.
- [pending] Filament package consumes.

### Phase 5 — verify Octane safety

- [pending] Audit `Middleware/SyncAuthzTenant` for request boundary handling.



## Suggested verification scope

- per-Resource tests
- Middleware tests
- Guard tests
- cross-package tests for commerce-support/filament-tax

## Recommended first move

Phase 1 — move domain models to foundation. This is the most visible inversion of dependencies and the fix aligns the package with the monorepo convention.

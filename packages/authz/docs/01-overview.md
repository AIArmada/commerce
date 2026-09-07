---
title: Authz Overview
---

## Purpose

`aiarmada/authz` provides the reusable authorization core for Commerce packages:

- UUID-backed Spatie roles and permissions
- Optional Authz scopes through Spatie teams
- Super-admin and wildcard permission Gate hooks
- Permission key generation
- Session-based impersonation services and Blade directives

Filament resources and UI behavior belong to `aiarmada/filament-authz`.

## What this package owns

- `Services\PermissionKeyBuilder` — canonical `resource.action[.scope]` key construction
- `Services\WildcardPermissionResolver` — wildcard (`*`) matching for Gate checks
- `Services\ImpersonateManager` — session-based impersonation lifecycle
- `Support\AuthzScopeContext`, `AuthzScopeResolver`, `AuthzScopeTeamResolver` — scope → team resolution
- `Support\ImpersonationScopeGuard`, `Support\CommandProhibitor`, `Support\UserRoleChecker` — guard rails
- `Models\Role`, `Models\Permission`, `Models\AuthzScope` — UUID-backed authz domain models
- `Models\Concerns` — `HasAuthzScope`, `ScopesAuthzTenancy`, `SyncsRolePermissions` traits for hosts
- Config `authz.php`: `database`, `super_admin_role`, `guards`, `users`, `wildcard_permissions`, `permissions`, `custom_permissions`, `sync`, `scopes`, `impersonate`

## What this package does not own

- Filament resources, pages, or widgets — see `aiarmada/filament-authz`
- Tenant row scoping via `HasOwner` — this package uses Spatie teams + `AuthzScope`, not the owner tuple

## Related packages

- `aiarmada/commerce-support` — provides owner primitives used by optional integrations
- `aiarmada/filament-authz` — Filament UI (roles/users discovery, impersonation buttons)
- `aiarmada/membership` — team-scoped role sync for member pivots

The global role configured by `authz.super_admin_role` is checked against a global role assignment even while a request is inside a team scope. Impersonation still requires the target to have an assignment in the active scope when `authz.scopes.enforce` is enabled.

Deleting an Authz scope removes the roles and assignments bound to that scope; global roles and permissions are preserved.

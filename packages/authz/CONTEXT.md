---
title: Authz Context
package: authz
status: current
surface: core
family: foundation
keywords:
  - roles
  - permissions
  - wildcard
  - scopes
  - impersonation
  - spatie
  - teams
---

# Authz Context

## Snapshot
- Composer: `aiarmada/authz`
- Role: Framework-agnostic Spatie Permission core: UUID schema, authz models, scopes, wildcard permissions, and impersonation services.
- Triggers: roles, permissions, wildcard, scopes, impersonation, spatie, teams
- Search first: `src/Services, src/Support, config, docs`
- Related: `commerce-support`, `filament-authz`, `membership`
- Paired: `filament-authz` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-authz/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-authz`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Defining permissions, scopes, or impersonation rules.
- Skip when: Filament UI for roles/users — see filament-authz.
- Owner/security: No HasOwner; uses Spatie teams + AuthzScope.

## Key surfaces
- Actions/Services: `Services/ImpersonateManager`, `Services/PermissionKeyBuilder`, `Services/WildcardPermissionResolver`, `Support/AuthzScopeContext`, `Support/AuthzScopeResolver`, `Support/AuthzScopeTeamResolver`, `Support/CommandProhibitor`, `Support/ImpersonationScopeGuard`
- Config `authz.php`: `database`, `table_prefix`, `tables`, `roles`, `permissions`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`, `scopes`, `super_admin_role`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package

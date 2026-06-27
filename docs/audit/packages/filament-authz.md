# Audit: `filament-authz` (AIArmada\FilamentAuthz)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Filament v5 admin UI for roles, permissions, and user management with Spatie permissions.

**Surface:** filament

---

## Findings

### Medium
1. **`PermissionResource::getEloquentQuery()` blanket `->withoutGlobalScopes()`** — Removes ALL global scopes including any future `OwnerScope`. Documented as intentional (Spatie permissions are global). If commerce-support's `OwnerScope` is ever registered on Permission model, it will be silently bypassed.

### Low
2. **No commerce-support owner primitives** — Uses Spatie teams for tenant isolation instead of `HasOwner`/`OwnerWriteGuard`. Architecturally valid but a different tenancy model.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| Navigation — static `$navigationGroup` | ✅ 0 violations | All 3 resources use config-driven |
| Navigation — config structure | ✅ Nested `navigation.group` | Compliant |
| Resources | ✅ 3 resources | Role, Permission, User |
| Pages | ✅ 10 pages | 3/3/4 per resource |
| Concern traits | ✅ 4 traits | CanBeImpersonated, HasPage/Widget/PanelAuthz |
| Console commands | ✅ 3 commands | Discover, GeneratePolicies, Seeder |
| Tests | ✅ 19 files | Config, plugin, commands, traits, impersonation |
| Docs | ✅ 10 files | Exceeds standard set |
| Impersonation | ✅ Complete | Controller, middleware, banner, redirect protection |

---

## Summary

Spatie-Permissions Filament adapter: 3 resources (Role, Permission, User), 10 pages, 2 actions (impersonate), 3 console commands, 19 tests, 10 docs. Navigation compliance clean. Uses Spatie teams for multi-tenancy instead of commerce-support owner primitives — valid architectural choice.

Impersonation with open-redirect protection. Policy generation command. Permission tab factory for reusable form component.

**Verdict:** Ready. Well-implemented with different tenancy model.

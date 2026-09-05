---
title: Organizations Context
package: organizations
status: current
surface: core
family: foundation
keywords:
  - organization
  - tenant
  - ownership
  - transfer
  - current-org
---

# Organizations Context

## Snapshot
- Composer: `aiarmada/organizations`
- Role: Reusable organization aggregate: identity, lifecycle, visibility, ownership invariants, current-org context.
- Triggers: organization, tenant, ownership, transfer, current-org
- Search first: `src/Models, src/Actions, src/Resolvers, config, docs`
- Related: `membership`, `filament-organizations`, `commerce-support`
- Paired: `filament-organizations` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-organizations/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns the reusable tenant aggregate, membership ownership invariants, lifecycle actions, and current-org context only.
- Does NOT own events, addresses, media, billing, moderation, Livewire, or Filament. Applications provide profile extensions, public projections, and domain authorization policy.
- Invariants: every org gets exactly one `Owner` membership; `created_by` is immutable; transfers are transactional; the final owner cannot leave or be removed; public queries must use the `public()` scope.
- Use `CurrentOrganizationMiddleware` to establish the `OwnerContext` used by owner-aware packages.
- If admin UI changes too, audit `filament-organizations`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Tenant aggregate or ownership transfer rules.
- Skip when: Join/invite flows — see membership.
- Owner/security: Org IS the owner; no HasOwner on Organization.

## Key surfaces
- Models: `Organization`
- Actions/Services: `Actions/ArchiveOrganizationAction`, `Actions/CreateOrganizationAction`, `Actions/MakeOrganizationPrivateAction`, `Actions/MakeOrganizationPublicAction`, `Actions/RestoreOrganizationAction`, `Actions/SuspendOrganizationAction`, `Actions/TransferOrganizationOwnershipAction`
- Config `organizations.php`: `database`, `tables`, `organizations`, `members`, `resolver`, `middleware`, `require_context`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package

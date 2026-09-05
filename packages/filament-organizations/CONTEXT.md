---
title: Filament Organizations Context
package: filament-organizations
status: current
surface: filament
family: foundation
keywords:
  - filament
  - organizations-ui
  - members
  - invitations
---

# Filament Organizations Context

## Snapshot
- Composer: `aiarmada/filament-organizations`
- Role: Filament admin for organizations: org resource + member/invitation managers + lifecycle actions.
- Triggers: filament, organizations-ui, members, invitations
- Search first: `src/Resources, config, docs`
- Related: `organizations`, `membership`
- Paired: `organizations` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../organizations/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `organizations`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `organizations` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Org admin UI.
- Skip when: Membership invariants — see organizations/membership.
- Owner/security: Org is the owner; no HasOwner.

## Key surfaces
- Resources: `OrganizationResource`
- Config `filament-organizations.php`: `navigation`, `group`, `sort`, `resources`, `enabled`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package

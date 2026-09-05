---
title: Filament Contacting Context
package: filament-contacting
status: active
surface: filament
family: foundation
keywords:
  - filament
  - contacts-ui
  - relation-manager
---

# Filament Contacting Context

## Snapshot
- Composer: `aiarmada/filament-contacting`
- Role: Filament adapter for contact methods/social/snapshots; relation-managers-first, resources off by default.
- Triggers: filament, contacts-ui, relation-manager
- Search first: `src/Resources, src/Schemas, config, docs`
- Related: `contacting`, `commerce-support`
- Paired: `contacting` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../contacting/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `contacting`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `contacting` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Admin UI for contact points.
- Skip when: Normalization rules — see contacting.
- Owner/security: OwnerUiScope in all 3 resources.

## Key surfaces
- Resources: `ContactMethodResource`, `ContactSnapshotResource`, `SocialProfileResource`
- Actions/Services: `Support/ContactingFilamentConfig`, `Support/GuardsContactingUi`, `Support/ResolvesContactingModels`
- Config `filament-contacting.php`: `navigation`, `group`, `sort`, `icons`, `contact_methods`, `social_profiles`, `contact_snapshots`, `tables`, `default_pagination`, `show_owner_columns`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package

---
title: Filament Seating Context
package: filament-seating
status: current
surface: filament
family: venue
keywords:
  - filament
  - seat-map
  - occupancy
---

# Filament Seating Context

## Snapshot
- Composer: `aiarmada/filament-seating`
- Role: Filament seat-map admin: maps, editor + occupancy pages, overview widget.
- Triggers: filament, seat-map, occupancy
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `seating`, `commerce-support`
- Paired: `seating` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../seating/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `seating`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `seating` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Seat-map visual admin.
- Skip when: Allocation logic — see seating.
- Owner/security: OwnerUiScope in queries.

## Key surfaces
- Resources: `SeatMapResource`
- Config `filament-seating.php`: `navigation`, `group`, `resources`, `enabled`, `seat_map`, `navigation_sort`, `seat_map`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package

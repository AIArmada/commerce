---
title: Filament Seating Context
package: filament-seating
status: current
surface: filament
family: venue
---

# Filament Seating Context

## Snapshot
- Composer: `aiarmada/filament-seating`
- Role: Filament admin UI for seat maps, sections, occupancy, and visual layout management.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `config`, `docs`
- Related: `seating`, `commerce-support`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../seating/CONTEXT.md` when domain behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, and admin-only workflow actions for seat layout management.
- Keep seating-domain rules, persistence, and lifecycle workflows in `seating`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.
- Use owner-safe resource queries and do not rely on Filament tenancy as a security boundary.
- Update `docs/*.md` in the same pass when public behavior or config changes.

---
title: Filament Promotions Context
package: filament-promotions
status: current
surface: filament
family: growth-and-incentives
---

# Filament Promotions Context

## Snapshot
- Composer: `aiarmada/filament-promotions`
- Role: Filament admin UI for promotion management.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Actions`, `config`, `docs`
- Related: `promotions`, `filament-pricing`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../promotions/CONTEXT.md` when domain behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, tables, forms, and panel/plugin glue.
- Keep domain rules, persistence, and state transitions in `promotions`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.

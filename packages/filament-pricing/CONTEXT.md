---
title: Filament Pricing Context
package: filament-pricing
status: current
surface: filament
family: catalog-and-identity
---

# Filament Pricing Context

## Snapshot
- Composer: `aiarmada/filament-pricing`
- Role: Filament admin UI for pricing, settings, and simulation.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Actions`, `config`, `docs`
- Related: `pricing`, `promotions`, `filament-promotions`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../pricing/CONTEXT.md` when domain behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, tables, forms, and panel/plugin glue.
- Keep domain rules, persistence, and state transitions in `pricing`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.

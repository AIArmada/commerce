---
title: Filament Cart Context
package: filament-cart
status: current
surface: filament
family: checkout-flow
---

# Filament Cart Context

## Snapshot
- Composer: `aiarmada/filament-cart`
- Role: Filament admin UI for cart snapshots, conditions, monitoring, and abandonment workflows.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Actions`, `config`, `docs`
- Related: `cart`, `signals`, `filament-signals`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../cart/CONTEXT.md` when domain behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, tables, forms, and panel/plugin glue.
- Keep domain rules, persistence, and state transitions in `cart`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.

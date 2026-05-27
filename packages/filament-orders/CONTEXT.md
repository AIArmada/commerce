---
title: Filament Orders Context
package: filament-orders
status: current
surface: filament
family: checkout-flow
---

# Filament Orders Context

## Snapshot
- Composer: `aiarmada/filament-orders`
- Role: Filament admin UI for orders, timelines, widgets, and invoice downloads.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Actions`, `config`, `docs`
- Related: `orders`, `checkout`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../orders/CONTEXT.md` when domain behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, tables, forms, and panel/plugin glue.
- Keep domain rules, persistence, and state transitions in `orders`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.

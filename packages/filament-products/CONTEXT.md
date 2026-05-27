---
title: Filament Products Context
package: filament-products
status: current
surface: filament
family: catalog-and-identity
---

# Filament Products Context

## Snapshot
- Composer: `aiarmada/filament-products`
- Role: Filament admin UI for product and variant management.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Actions`, `config`, `docs`
- Related: `products`, `filament-authz`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../products/CONTEXT.md` when domain behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, tables, forms, and panel/plugin glue.
- Keep domain rules, persistence, and state transitions in `products`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.

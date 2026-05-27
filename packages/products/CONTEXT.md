---
title: Products Context
package: products
status: current
surface: domain
family: catalog-and-identity
---

# Products Context

## Snapshot
- Composer: `aiarmada/products`
- Role: Catalog products, variants, and product-domain behavior.
- Search first: `src/Models`, `src/Actions`, `src/Services`, `src/Events`, `config`, `docs`
- Related: `filament-products`, `pricing`, `inventory`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-products/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-products`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

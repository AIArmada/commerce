---
title: Cart Context
package: cart
status: current
surface: domain
family: checkout-flow
---

# Cart Context

## Snapshot
- Composer: `aiarmada/cart`
- Role: Cart storage, cart items, conditions, metadata, and owner-aware persistence.
- Search first: `src/Models`, `src/Actions`, `src/Services`, `src/Events`, `config`, `docs`
- Related: `filament-cart`, `checkout`, `signals`, `vouchers`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-cart/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-cart`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

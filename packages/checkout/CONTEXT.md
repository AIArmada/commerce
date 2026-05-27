---
title: Checkout Context
package: checkout
status: current
surface: orchestrator
family: checkout-flow
---

# Checkout Context

## Snapshot
- Composer: `aiarmada/checkout`
- Role: Checkout session orchestration across cart, pricing, shipping, payments, docs, and orders.
- Search first: `src/Actions`, `src/Services`, `src/Support`, `config`, `docs`
- Related: `cart`, `orders`, `shipping`, `chip`, `cashier`, `docs`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. related package contexts when the change crosses carts, payments, shipping, or orders
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns cross-package checkout workflow orchestration.
- Audit related packages because session, payment, shipping, and order changes often fan out.
- Update `docs/*.md` in the same pass when public behavior or config changes.

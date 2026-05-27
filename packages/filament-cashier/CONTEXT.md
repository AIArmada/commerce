---
title: Filament Cashier Context
package: filament-cashier
status: current
surface: filament
family: payments-and-documents
---

# Filament Cashier Context

## Snapshot
- Composer: `aiarmada/filament-cashier`
- Role: Unified Filament billing UI for subscriptions, invoices, dashboards, and gateway comparisons.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Support`, `src/Actions`, `config`, `docs`
- Related: `cashier`, `cashier-chip`, `filament-cashier-chip`
- `UnifiedSubscription` and `UnifiedInvoice` are shared DTOs; support-layer changes can affect resources, widgets, and customer portal pages at once.
- Stripe and CHIP subscription normalization relies on gateway-specific fallbacks for plan IDs, amounts, external IDs, and dashboard URLs.

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../cashier/CONTEXT.md` when billing behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, tables, forms, and panel/plugin glue.
- Keep billing contracts, persistence, and gateway-neutral rules in `cashier`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.

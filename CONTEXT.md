---
title: AIArmada Commerce AI Entry
status: current
kind: ai-entrypoint
---

# AIArmada Commerce AI Entry

Use this file first. It is intentionally short and optimized for routing an AI model to the right package and docs quickly.

## Read order

1. `CONTEXT-MAP.md`
2. `docs/ai/package-manifests.json`
3. `packages/<target-package>/CONTEXT.md`
4. `packages/<target-package>/docs/01-overview.md`
5. `packages/<target-package>/docs/03-configuration.md`
6. `packages/<target-package>/docs/04-usage.md`

## Choose the owning package

- shared contracts, owner scoping, health checks, targeting, webhooks, money helpers → `packages/commerce-support`
- admin-only Filament work → the paired `packages/filament-*` package
- customers, products, pricing, inventory, tax → the corresponding domain package
- promotions, vouchers, affiliates, affiliate-network, growth → the corresponding incentives or analytics package
- cart, checkout, orders, shipping, J&T → the corresponding checkout-flow package
- CHIP, cashier, cashier-chip, docs → the corresponding payments/documents package
- signals and events → the corresponding analytics/events package
- metapackage installation only → `packages/csuite`

## Non-negotiables

- `packages/*/docs/*.md` are canonical.
- `filament-*` packages are adapters, not domain owners.
- owner safety is never a UI-only concern.
- query-builder paths need explicit owner handling.
- money is minor units plus currency.

## Package context convention

- every `packages/*` root now has `CONTEXT.md`
- use the package context before code search or edits
- if a task crosses core + Filament boundaries, read both package contexts

## Cross-package guides

- affiliate routing across `affiliates`, `vouchers`, `checkout`, and `filament-affiliates` → `docs/affiliates.md`

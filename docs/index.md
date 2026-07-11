---
title: AIArmada Commerce Documentation Index
status: current
---

# AIArmada Commerce Documentation

This index intentionally points only to **current** documentation.

## Source-of-truth rules

1. `packages/*/docs/*.md` are the canonical source for package behavior, configuration, extension points, and admin UI.
2. `docs/*.md` and active subfolders under `docs/` are ecosystem guides, onboarding, operations, and cross-package context.
3. Historical implementation context should come from Git history, targeted audits, or the repository memory files under `memories/repo/`.
4. `memories/**/*` is assistant working memory, not product documentation.
5. `stubs/**/*` contains static-analysis support files, not runtime implementation.

## Current root-level guides

- [AI Context](../CONTEXT.md)
- [AI Retrieval Layer](ai/01-overview.md)
- [AI Package Manifests](ai/package-manifests.json)

## Canonical package documentation

### Core and domain packages

- [Addressing](../packages/addressing/docs/01-overview.md)
- [Affiliate Network](../packages/affiliate-network/docs/01-overview.md)
- [Affiliates](../packages/affiliates/docs/01-overview.md)
- [Authz](../packages/authz/docs/01-overview.md)
- [Cart](../packages/cart/docs/01-overview.md)
- [Cashier](../packages/cashier/docs/01-overview.md)
- [Cashier CHIP](../packages/cashier-chip/docs/01-overview.md)
- [Checkout](../packages/checkout/docs/01-overview.md)
- [CHIP](../packages/chip/docs/01-overview.md)
- [Commerce Support](../packages/commerce-support/docs/01-overview.md)
- [Communications](../packages/communications/docs/01-overview.md)
- [Contacting](../packages/contacting/docs/01-overview.md)
- [CSuite](../packages/csuite/docs/01-overview.md)
- [Customers](../packages/customers/docs/01-overview.md)
- [Docs](../packages/docs/docs/01-overview.md)
- [Events](../packages/events/docs/01-overview.md)
- [Engagement](../packages/engagement/docs/01-overview.md)
- [Feedback](../packages/feedback/docs/01-overview.md)
- [Growth](../packages/growth/docs/01-overview.md)
- [Inventory](../packages/inventory/docs/01-overview.md)
- [J&T](../packages/jnt/docs/01-overview.md)
- [Membership](../packages/membership/docs/01-overview.md)
- [Moderation](../packages/moderation/docs/01-overview.md)
- [Orders](../packages/orders/docs/01-overview.md)
- [Pricing](../packages/pricing/docs/01-overview.md)
- [Products](../packages/products/docs/01-overview.md)
- [Promotions](../packages/promotions/docs/01-overview.md)
- [References](../packages/references/docs/01-overview.md)
- [Seating](../packages/seating/docs/01-overview.md)
- [Shipping](../packages/shipping/docs/01-overview.md)
- [Signals](../packages/signals/docs/01-overview.md)
- [Tax](../packages/tax/docs/01-overview.md)
- [Ticketing](../packages/ticketing/docs/01-overview.md)
- [Vouchers](../packages/vouchers/docs/01-overview.md)

### Filament and admin packages

- [Filament Addressing](../packages/filament-addressing/docs/01-overview.md)
- [Filament Affiliate Network](../packages/filament-affiliate-network/docs/01-overview.md)
- [Filament Affiliates](../packages/filament-affiliates/docs/01-overview.md)
- [Filament Authz](../packages/filament-authz/docs/01-overview.md)
- [Filament Cart](../packages/filament-cart/docs/01-overview.md)
- [Filament Cashier](../packages/filament-cashier/docs/01-overview.md)
- [Filament Cashier CHIP](../packages/filament-cashier-chip/docs/01-overview.md)
- [Filament CHIP](../packages/filament-chip/docs/01-overview.md)
- [Filament Commerce Support](../packages/filament-commerce-support/docs/01-overview.md)
- [Filament Communications](../packages/filament-communications/docs/01-overview.md)
- [Filament Contacting](../packages/filament-contacting/docs/01-overview.md)
- [Filament Customers](../packages/filament-customers/docs/01-overview.md)
- [Filament Docs](../packages/filament-docs/docs/01-overview.md)
- [Filament Events](../packages/filament-events/docs/01-overview.md)
- [Filament Engagement](../packages/filament-engagement/docs/01-overview.md)
- [Filament Feedback](../packages/filament-feedback/docs/01-overview.md)
- [Filament Growth](../packages/filament-growth/docs/01-overview.md)
- [Filament Inventory](../packages/filament-inventory/docs/01-overview.md)
- [Filament J&T](../packages/filament-jnt/docs/01-overview.md)
- [Filament Orders](../packages/filament-orders/docs/01-overview.md)
- [Filament Pricing](../packages/filament-pricing/docs/01-overview.md)
- [Filament Products](../packages/filament-products/docs/01-overview.md)
- [Filament Promotions](../packages/filament-promotions/docs/01-overview.md)
- [Filament Seating](../packages/filament-seating/docs/01-overview.md)
- [Filament Shipping](../packages/filament-shipping/docs/01-overview.md)
- [Filament Signals](../packages/filament-signals/docs/01-overview.md)
- [Filament Tax](../packages/filament-tax/docs/01-overview.md)
- [Filament Ticketing](../packages/filament-ticketing/docs/01-overview.md)
- [Filament Vouchers](../packages/filament-vouchers/docs/01-overview.md)

## Need history?

Use Git history or repository memory files when you need older implementation context. Current package behavior should always be taken from the package docs listed above.

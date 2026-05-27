---
title: Affiliates Integration Map
status: current
---

# Affiliates Integration Map

This page is a **current cross-package map** for affiliate-related work. Keep it for ecosystem routing; package docs remain the canonical reference.

For detailed behavior, configuration keys, and API surface, prefer the package docs directly.

## Canonical docs

- [Affiliates package](../packages/affiliates/docs/01-overview.md)
- [Filament Affiliates package](../packages/filament-affiliates/docs/01-overview.md)
- [Checkout package](../packages/checkout/docs/01-overview.md)
- [Vouchers package](../packages/vouchers/docs/01-overview.md)
- [Filament Vouchers package](../packages/filament-vouchers/docs/01-overview.md)
- [Commerce Support package](../packages/commerce-support/docs/01-overview.md)

## Use this page when

Use this guide when you need to understand **how affiliate behavior spans packages**.

If you need exact config keys, model fields, events, or extension points, jump to the package docs above.

## Read by task

| Task | Read first |
| --- | --- |
| Public referral entry routes and cookies | `packages/affiliates/docs/03-configuration.md`, `packages/affiliates/docs/04-usage.md` |
| Voucher-driven affiliate attribution | `packages/affiliates/docs/04-usage.md`, `packages/vouchers/docs/09-usage-tracking.md` |
| Checkout carry-through and conversion behavior | `packages/checkout/docs/08-integrations.md`, `packages/affiliates/docs/04-usage.md` |
| Commission maturity, payout eligibility, and performance bonuses | `packages/affiliates/docs/05-models.md`, `packages/affiliates/docs/06-services.md`, `packages/affiliates/docs/08-payouts.md` |
| Admin operations and payout workflows | `packages/filament-affiliates/docs/04-usage.md`, `packages/affiliates/docs/08-payouts.md` |
| Affiliate-aware voucher reporting and exports | `packages/filament-vouchers/docs/04-usage.md`, `packages/filament-vouchers/docs/05-widgets.md` |
| Owner scoping / tenant safety | `packages/affiliates/docs/10-multi-tenancy.md`, `packages/commerce-support/docs/04-multi-tenancy.md` |

## Boundary summary

- `aiarmada/affiliates` owns attribution, conversions, commissions, payouts, and referral links.
- `aiarmada/filament-affiliates` owns affiliate-facing admin resources and widgets.
- `aiarmada/vouchers` can carry affiliate hints and redemption metadata, but it does not own affiliate logic.
- `aiarmada/checkout` carries affiliate context through checkout and order creation, but it does not replace the affiliates domain.
- `aiarmada/commerce-support` owns owner-scoping rules used when affiliate data is tenant-scoped.

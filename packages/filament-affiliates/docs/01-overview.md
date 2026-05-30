---
title: Overview
---

# Filament Affiliates Plugin

## Purpose

`aiarmada/filament-affiliates` is the Filament adapter for `aiarmada/affiliates`.

It provides:

- admin resources/pages/widgets for affiliate operations,
- an optional affiliate self-service portal panel,
- UI-layer workflow actions that delegate domain rules to the core affiliates package.

## What this package owns

- Filament resources and relation managers for affiliate operations surfaces
- Admin workflow pages (`FraudReviewPage`, `PayoutBatchPage`, `ReportsPage`)
- Portal pages (`PortalDashboard`, `PortalProfile`, `PortalLinks`, `PortalPrograms`, `PortalConversions`, `PortalPayouts`, `PortalSupport`, and optional `PortalRegistration`)
- Widget surfaces (`AffiliateStatsWidget`, `PerformanceOverviewWidget`, `RealTimeActivityWidget`, plus feature-gated widgets)

## What this package does not own

- Commission calculation, attribution core logic, payout accounting rules, and fraud-rule semantics — these stay in `aiarmada/affiliates`
- Tenant resolution internals — this package consumes owner context from `commerce-support`

## Related packages

- [`aiarmada/affiliates`](../../affiliates/docs/01-overview.md) — core affiliate domain package
- [`aiarmada/affiliate-network`](../../affiliate-network/docs/01-overview.md) — optional marketplace/network extension
- [`aiarmada/filament-affiliate-network`](../../filament-affiliate-network/docs/01-overview.md) — network marketplace admin plugin
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and shared primitives

## Main surfaces

- **Resources** — affiliates, conversions, payouts, programs, commission templates, links, touchpoints, ranks, rank history, support tickets, tax documents, fraud signals, network
- **Pages** — fraud review, payout batching, reports, and portal pages
- **Widgets** — stats, performance, activity, fraud alerts, payout queue, network visualization

## Owner scoping and security notes

- Owner scoping follows `affiliates` + `commerce-support` primitives.
- Filament filtering is not authorization.
- Write paths must still validate submitted IDs server-side (owner-safe lookup).

## Requirements

- PHP 8.4+
- Laravel app with Filament v5 panel support
- Filament v5
- `aiarmada/affiliates` (core package)

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Widgets](05-widgets.md)
- [Portal](06-portal.md)
- [Troubleshooting](99-troubleshooting.md)
- [Core affiliates overview](../../affiliates/docs/01-overview.md)

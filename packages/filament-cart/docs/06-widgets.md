---
title: Widgets
---

# Widgets

Filament Cart widgets focus on live cart operations.

## CartStatsWidget

Displays total carts, active carts, item quantity, and cart value.

## CartStatsOverviewWidget

Displays active carts, cart value, checkout starts, and abandoned carts with a simple abandonment rate.

## LiveStatsWidget

Displays recent active carts, carts with items, checkouts in progress, recent abandonments, total value, and high-value cart counts.

> **Note:** `CartStatsOverviewWidget` and `LiveStatsWidget` have been removed from the package. Use `CartStatsWidget` for cart statistics.

## RecentActivityWidget

Shows a recent activity table with status values:

- `active`
- `checkout`
- `abandoned`

## AbandonedCartsWidget

Shows snapshots with `checkout_abandoned_at` set in the last seven days.

## Analytics widgets

Dedicated analytics charts and alert widgets were removed from Filament Cart. Use `filament-signals` for dashboards, alert rules, pending alerts, reports, and event trend charts.

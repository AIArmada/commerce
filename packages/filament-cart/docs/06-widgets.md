---
title: Widgets
---

# Widgets

Filament Cart widgets focus on live cart operations.

## CartStatsWidget

Stats overview on the `CartDashboard` page showing active carts (with the count of
those that have items), checkouts in progress, recent abandonments (with a 24h
abandonment rate), and total cart value (with a high-value cart count).

## RecentActivityWidget

Footer widget on the `LiveDashboardPage`. Shows a recent activity table with
columns for session identifier, item count, formatted value, and update time,
with status values:

- `active`
- `checkout`
- `abandoned`

## AbandonedCartsWidget

Footer widget on the `CartDashboard` page. Shows snapshots with
`checkout_abandoned_at` set in the last seven days.

## Analytics widgets

Dedicated analytics charts and alert widgets were removed from Filament Cart. Use `filament-signals` for dashboards, alert rules, pending alerts, reports, and event trend charts.

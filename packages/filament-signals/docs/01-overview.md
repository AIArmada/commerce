---
title: Overview
---

# Filament Signals Plugin

## Purpose

The `aiarmada/filament-signals` package is the Filament analytics UI for `aiarmada/signals`.

## What this package owns

- Filament dashboard and report pages for analytics workflows
- Management resources for tracked properties, goals, segments, saved reports, alert rules, and alert logs
- Signals feature toggles, navigation ordering, and monetary-aware analytics UI behavior

## What this package does not own

- Event ingestion, identity/session tracking, or analytics persistence; those stay in `aiarmada/signals`
- Domain-specific commerce logic from cart, checkout, orders, vouchers, or affiliates
- Tenant resolution itself; it consumes the owner context from the host app and `commerce-support`

## Related packages

- [`aiarmada/signals`](../../signals/docs/01-overview.md) — core analytics foundation
- [`aiarmada/cart`](../../cart/docs/01-overview.md), [`aiarmada/checkout`](../../checkout/docs/01-overview.md), [`aiarmada/orders`](../../orders/docs/01-overview.md), and [`aiarmada/affiliates`](../../affiliates/docs/01-overview.md) — common data sources surfaced in reports
- [`aiarmada/growth`](../../growth/docs/01-overview.md) — experimentation package that enriches Signals events

## Main models services or surfaces

- **Pages** — dashboard and report pages for page views, funnels, acquisition, journeys, retention, content performance, live activity, and goals
- **Resources** — tracked properties, goals, segments, saved reports, alert rules, and alert logs
- **UI behaviors** — feature-flagged resources/widgets and monetary-aware UI suppression when revenue analytics are disabled

## Owner scoping and security notes

- The plugin should mirror the owner-scoping behavior defined by `aiarmada/signals`
- Report filters are not authorization; underlying Signals queries and alert actions must still enforce owner-safe reads and writes

The `aiarmada/filament-signals` package provides a Filament v5 analytics UI on top of the Signals package. It registers report pages, management resources, and dashboard widgets for product analytics workflows.

## Key Features

- Dashboard and report pages for page views, funnels, acquisition, journeys, retention, content performance, live activity, and goals
- Dedicated devices and technology report for device type, browser, OS, and brand analysis
- Resource management for tracked properties, goals, segments, saved reports, alert rules, and alert logs
- Feature flags to enable/disable individual pages/resources/widgets
- Navigation ordering and label customization via config
- Bot-aware reporting surfaces for page views and acquisition analysis
- Monetary-aware UI that automatically hides revenue-focused cards, columns, and actions when the Signals package disables monetary analytics

## Requirements

- PHP 8.4+
- Filament v5
- `aiarmada/signals`

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Customization](05-customization.md)
- [Troubleshooting](99-troubleshooting.md)
- [Core Signals overview](../../signals/docs/01-overview.md)

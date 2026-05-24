---
title: Overview
---

# Filament Affiliates Plugin

## Purpose

The `aiarmada/filament-affiliates` package is the Filament admin and affiliate-portal adapter for `aiarmada/affiliates`.

## What this package owns

- Filament resources for affiliates, conversions, payouts, programs, and fraud signals
- Admin pages for fraud review, payout batching, and affiliate reporting
- Affiliate self-service portal pages for dashboards, conversions, payouts, links, and registration
- Affiliate-focused widgets and UI-specific services

## What this package does not own

- Attribution, commission calculation, payout bookkeeping, or fraud-detection rules; those stay in `aiarmada/affiliates`
- Marketplace offer publishing and network-specific admin surfaces; those belong to `aiarmada/affiliate-network` and `aiarmada/filament-affiliate-network`
- Tenant resolution itself; it consumes the owner context supplied by the host app and `commerce-support`

## Related packages

- [`aiarmada/affiliates`](../../affiliates/docs/01-overview.md) — core affiliate domain package
- [`aiarmada/affiliate-network`](../../affiliate-network/docs/01-overview.md) — optional marketplace/network extension
- [`aiarmada/filament-affiliate-network`](../../filament-affiliate-network/docs/01-overview.md) — network marketplace admin plugin
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and shared primitives

## Main models services or surfaces

- **Resources** — affiliates, conversions, payouts, programs, and fraud signals
- **Pages** — fraud review, payout batches, reports, and the affiliate portal pages
- **Widgets** — affiliate stats, performance overview, fraud alerts, payout queue, activity feed, and network visualization

## Owner scoping and security notes

- The plugin should mirror the owner-scoping behavior defined by `aiarmada/affiliates` and `commerce-support`
- Portal and admin UI filters are not authorization; the backing domain package must still validate submitted IDs, ownership, and payout eligibility on write paths

The `aiarmada/filament-affiliates` package provides a complete Filament v5 admin interface for managing affiliates, conversions, payouts, programs, and fraud detection.

## Features

### Resources

- **AffiliateResource** - Full CRUD for affiliate partners with status management
- **AffiliateConversionResource** - View and manage conversions with status moderation
- **AffiliatePayoutResource** - Track and process payout batches
- **AffiliateProgramResource** - Manage affiliate programs and tiers
- **AffiliateFraudSignalResource** - Review and act on fraud signals

### Pages

- **FraudReviewPage** - Dedicated fraud review workflow with bulk actions
- **PayoutBatchPage** - Batch payout processing interface
- **ReportsPage** - Affiliate performance reports and analytics

### Portal Pages (Affiliate Self-Service)

- **PortalDashboard** - Affiliate dashboard with stats
- **PortalConversions** - View conversion history
- **PortalPayouts** - Track payout status
- **PortalLinks** - Generate and manage affiliate links
- **PortalRegistration** - Self-registration for new affiliates

### Widgets

- **AffiliateStatsWidget** - Key performance indicators
- **PerformanceOverviewWidget** - Trends and charts
- **FraudAlertWidget** - Recent fraud signals
- **PayoutQueueWidget** - Pending payout overview
- **RealTimeActivityWidget** - Live attribution/conversion feed
- **NetworkVisualizationWidget** - MLM network tree view

## Architecture

```
src/
├── Actions/               # Filament action classes
├── AffiliatePanelProvider.php  # Optional standalone panel
├── Concerns/              # Shared traits
├── FilamentAffiliates.php      # Facade
├── FilamentAffiliatesPlugin.php # Main plugin class
├── FilamentAffiliatesServiceProvider.php
├── Pages/                 # Custom pages
│   ├── FraudReviewPage.php
│   ├── PayoutBatchPage.php
│   ├── ReportsPage.php
│   └── Portal/           # Affiliate self-service portal
├── Policies/              # Authorization policies
├── Resources/             # Filament resources
│   ├── AffiliateResource/
│   ├── AffiliateConversionResource/
│   ├── AffiliatePayoutResource/
│   ├── AffiliateProgramResource/
│   └── AffiliateFraudSignalResource/
├── Services/              # UI-specific services
├── Support/               # Form schemas, aggregators
└── Widgets/               # Dashboard widgets
```

## Requirements

- PHP 8.4+
- Laravel 11+
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

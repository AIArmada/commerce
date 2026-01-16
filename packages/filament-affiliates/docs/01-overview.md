---
title: Overview
---

# Filament Affiliates Plugin

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

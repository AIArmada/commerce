---
title: Overview
---

# Affiliates Package

## Purpose

The `aiarmada/affiliates` package owns affiliate attribution, referral programs, commission calculation, payout workflows, fraud signals, and affiliate analytics for the Commerce ecosystem.

## What this package owns

- Affiliate programs, memberships, conversions, commissions, payout records, and fraud signals
- Attribution capture, tracking cookies, UTM and fingerprint data, and conversion recording flows
- Commission tiers, maturity periods, payout batching, and fraud-detection rules
- Affiliate-facing events, services, commands, and optional API surfaces

## What this package does not own

- Multi-merchant marketplace offers and site publishing; those belong to `aiarmada/affiliate-network`
- Filament admin or affiliate portal surfaces; those belong to `aiarmada/filament-affiliates`
- Cart, voucher, or checkout persistence; integrations enrich those packages rather than owning them

## Related packages

- [`aiarmada/filament-affiliates`](../../filament-affiliates/docs/01-overview.md) — Filament admin UI and affiliate portal surfaces
- [`aiarmada/affiliate-network`](../../affiliate-network/docs/01-overview.md) — marketplace/network extension on top of core affiliates
- [`aiarmada/cart`](../../cart/docs/01-overview.md) — automatic attribution metadata and cart helpers
- [`aiarmada/vouchers`](../../vouchers/docs/01-overview.md) — native voucher-to-affiliate linking and default-code attribution
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and shared infrastructure

## Main models services or surfaces

- **Models** — affiliates, programs, tiers, conversions, payouts, fraud signals, creatives, and related tracking records
- **Services and actions** — attribution, commission calculation, payout processing, fraud scoring, reporting, and program workflows
- **Interfaces** — optional API, commands, and event surfaces documented in the deeper package pages

## Owner scoping and security notes

- Owner enforcement follows `affiliates.owner.enabled` and the broader `commerce-support` owner contract
- Child or derived models use relationship-based scoping; non-request surfaces still need explicit owner context
- Consuming applications should validate affiliate, program, payout, and conversion identifiers server-side rather than trusting filtered UI options

The `aiarmada/affiliates` package provides a complete affiliate marketing and referral tracking system for Laravel applications. Built for commerce platforms, it handles attribution, commission calculations, payouts, fraud detection, and multi-level network management.

## Key Features

### Attribution & Tracking
- **Cookie-based tracking** with configurable TTL and consent management
- **Multi-touch attribution models** (first-touch, last-touch, linear)
- **UTM parameter capture** for campaign analytics
- **Fingerprint detection** to prevent duplicate attributions
- **IP rate limiting** to block spam/bots

### Commission Management
- **Flexible commission types** - percentage (basis points) or fixed amounts
- **Volume-based tiers** for progressive commission rates
- **Program-specific rules** with custom conditions
- **Commission templates** for quick affiliate setup
- **Maturity periods** before commissions become payable

### Payout System
- **Batch payout processing** with reconciliation
- **Multiple payout methods** (PayPal, Stripe, bank transfer)
- **Payout holds** for fraud review or policy compliance
- **Multi-level commissions** for MLM/network structures
- **Tax document management** (W-9, 1099 thresholds)

### Programs & Tiers
- **Affiliate programs** with approval workflows
- **Program tiers** for commission escalation
- **Creative assets** (banners, links) per program
- **Membership management** with status tracking

### Fraud Prevention
- **Real-time fraud signals** with severity scoring
- **Velocity checks** (clicks/hour, conversions/day)
- **Geo-anomaly detection**
- **Conversion time analysis**
- **Blocking thresholds** with auto-suspension

### Network/MLM Support
- **Hierarchical affiliate networks** with parent-child relationships
- **Multi-level override commissions**
- **Rank qualification system**
- **Network visualization** via Filament plugin

### Reporting & Analytics
- **Daily stats aggregation**
- **Cohort analysis**
- **Performance bonus calculations**
- **Leaderboards** for gamification

## Architecture

The package follows a service-oriented architecture with clear separation of concerns:

```
src/
├── Actions/           # Single-purpose action classes
├── Cart/              # Cart integration (discount conditions)
├── Console/           # Artisan commands
├── Contracts/         # Interfaces for extensibility
├── Data/              # Spatie Laravel Data DTOs
├── Enums/             # Status and type enumerations
├── Events/            # Domain events
├── Exceptions/        # Custom exceptions
├── Facades/           # Laravel facades
├── Http/              # Controllers for API routes
├── Listeners/         # Event listeners
├── Models/            # Eloquent models (28 models)
├── Services/          # Business logic services (16 services)
├── Support/           # Helpers, middleware, webhooks
└── Traits/            # Reusable model traits
```

## Multi-Tenancy

The package fully supports multi-tenant architectures using the `commerce-support` owner scoping system:

- **Models**: All tenant-owned models use `HasOwner` and `HasOwnerScopeConfig` traits
- **Derived data**: Child models use `ScopesByAffiliateOwner` for affiliate-relative scoping
- **Global scope**: Default-on enforcement when `affiliates.owner.enabled` is true
- **Global records**: Optional include-global behavior for shared affiliate programs

## Integrations

| Package | Integration |
|---------|-------------|
| `aiarmada/cart` | Automatic cart metadata, fluent helpers, conversion recording |
| `aiarmada/vouchers` | Auto-attach affiliates from voucher metadata |
| `aiarmada/filament-affiliates` | Full admin UI with resources, widgets, portal |

All integrations are detected via `class_exists()` and enabled automatically.

## Requirements

- PHP 8.4+
- Laravel 11+
- `aiarmada/commerce-support` (for multi-tenancy primitives)

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Models](05-models.md)
- [Services](06-services.md)
- [Programs](07-programs.md)
- [Payouts](08-payouts.md)
- [Fraud detection](09-fraud-detection.md)
- [Multitenancy](10-multi-tenancy.md)
- [Commands](11-commands.md)
- [Events](12-events.md)
- [API](13-api.md)
- [Filament Affiliates overview](../../filament-affiliates/docs/01-overview.md)

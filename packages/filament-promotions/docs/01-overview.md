---
title: Overview
---

# Filament Promotions

## Purpose

The `aiarmada/filament-promotions` package is the Filament admin adapter for `aiarmada/promotions`. It provides the dedicated promotions management UI for Commerce installs that use Filament.

## What this package owns

- Filament resources, tables, forms, and widgets for promotion administration
- The dedicated promotions navigation and resource surface when installed alongside `aiarmada/filament-pricing`
- Promotion-focused owner-scoped admin queries and stats widgets

## What this package does not own

- Promotion calculation, targeting evaluation, or persistence rules; those stay in `aiarmada/promotions`
- Pricing list management and simulator flows; those stay in `aiarmada/filament-pricing`
- Product or cart domain behavior

## Related packages

- [`aiarmada/promotions`](../../promotions/docs/01-overview.md) — core promotion models and targeting logic
- [`aiarmada/filament-pricing`](../../filament-pricing/docs/01-overview.md) — pricing admin that hands off promotions when this plugin is installed
- [`aiarmada/filament-cart`](../../filament-cart/docs/01-overview.md) — optional deep links from promotion usage to cart snapshots
- [`aiarmada/filament-products`](../../filament-products/docs/01-overview.md) — optional product-aware promotion targeting helpers

## Main models services or surfaces

- **Resource** — `PromotionResource`
- **Widget** — `PromotionStatsWidget`
- **Support** — owner-scoped query helpers for admin listings and detail pages

## Owner scoping and security notes

- The plugin should mirror the owner-scope behavior defined by `aiarmada/promotions` and `commerce-support`
- Resource filtering improves usability, but submitted records still rely on the core package to enforce owner-safe writes and promotion usage semantics
- In combined installs, this package explicitly owns the promotions admin surface to avoid duplicate navigation with Filament Pricing

A Filament v5 plugin for administering promotional discounts and campaigns in your admin panel.

When this package is installed alongside `aiarmada/filament-pricing`, it becomes the dedicated promotions admin surface. Filament Pricing then skips its legacy fallback `PromotionResource` so promotions are managed from one clear place instead of duplicated across plugins.

## Features

- **Full CRUD Operations** — Create, view, edit, and delete promotions
- **Dedicated Admin Surface** — Owns the promotions navigation/resource in combined installs with `aiarmada/filament-pricing`
- **Rich Data Tables** — Sortable, filterable lists with badges and icons
- **Promotion Types** — Percentage, fixed amount, and Buy X Get Y with visual indicators
- **Usage Statistics** — Monitor promotion redemption counts
- **Scheduling Display** — View start/end dates clearly
- **Owner Scoping** — Multi-tenant support for SaaS applications
- **Stats Widget** — Dashboard overview of promotion metrics

## Architecture

```
filament-promotions/
├── Enums/
│   └── PromotionType.php           # Filament-aware enum with HasColor/HasIcon/HasLabel
├── Models/
│   └── Promotion.php               # Extended model with Filament enum
├── Resources/
│   └── PromotionResource/
│       ├── Pages/
│       │   ├── CreatePromotion.php
│       │   ├── EditPromotion.php
│       │   ├── ListPromotions.php
│       │   └── ViewPromotion.php
│       ├── Schemas/
│       │   ├── PromotionForm.php
│       │   └── PromotionInfolist.php
│       └── Tables/
│           └── PromotionsTable.php
├── Support/
│   └── OwnerScopedQueries.php      # Owner scope helper
├── Widgets/
│   └── PromotionStatsWidget.php    # Dashboard stats
├── FilamentPromotionsPlugin.php    # Plugin registration
└── FilamentPromotionsServiceProvider.php
```

## Requirements

- PHP 8.4+
- Laravel 13+
- Filament 5.0+
- aiarmada/promotions package

## Plugin Registration

The plugin registers:

- **PromotionResource** — Full CRUD for promotions
- **PromotionStatsWidget** — Dashboard statistics overview

## Integration with Filament Pricing

If your application uses both pricing and promotions admin packages, register both plugins. The dedicated promotions plugin owns the Promotions resource and navigation, while Filament Pricing continues to provide price lists, settings, simulator flows, and pricing stats.

See [Filament Pricing Overview](../../filament-pricing/docs/01-overview.md) for the pricing-side handoff details.

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Troubleshooting](99-troubleshooting.md)
- [Core promotions overview](../../promotions/docs/01-overview.md)

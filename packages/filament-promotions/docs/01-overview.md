---
title: Overview
---

# Filament Promotions

A Filament v5 plugin for administering promotional discounts and campaigns in your admin panel.

## Features

- **Full CRUD Operations** — Create, view, edit, and delete promotions
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
- Laravel 12+
- Filament 5.0+
- aiarmada/promotions package

## Plugin Registration

The plugin registers:

- **PromotionResource** — Full CRUD for promotions
- **PromotionStatsWidget** — Dashboard statistics overview

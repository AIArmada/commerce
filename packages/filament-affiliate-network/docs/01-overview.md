---
title: Overview
---

# Filament Affiliate Network Plugin

The `aiarmada/filament-affiliate-network` plugin provides a complete Filament v5 admin interface for managing the affiliate network marketplace.

## Features

- **Site Management** - Register and verify merchant domains with status tracking
- **Offer Management** - Create and manage affiliate offers with commission configuration
- **Category Management** - Organize offers in hierarchical categories
- **Application Review** - Approve/reject/revoke affiliate applications with workflow actions
- **Marketplace Page** - Affiliates browse, search, and apply for offers
- **Merchant Dashboard** - Analytics with pending applications and top offers
- **Network Stats Widget** - Overview statistics (sites, offers, clicks, conversions, revenue)
- **Top Offers Widget** - Performance table of best-performing offers

## Plugin Architecture

```
filament-affiliate-network/
├── config/
│   └── filament-affiliate-network.php
├── resources/
│   └── views/
│       └── pages/
│           ├── affiliate-marketplace.blade.php
│           └── merchant-dashboard.blade.php
└── src/
    ├── FilamentAffiliateNetworkPlugin.php
    ├── FilamentAffiliateNetworkServiceProvider.php
    ├── Pages/
    │   ├── AffiliateMarketplacePage.php
    │   └── MerchantDashboardPage.php
    ├── Resources/
    │   ├── AffiliateSiteResource.php
    │   │   └── Pages/ (List, Create, Edit)
    │   ├── AffiliateOfferResource.php
    │   │   └── Pages/ (List, Create, Edit)
    │   ├── AffiliateOfferCategoryResource.php
    │   │   └── Pages/ (List, Create, Edit)
    │   └── AffiliateOfferApplicationResource.php
    │       └── Pages/ (List, View)
    └── Widgets/
        ├── NetworkStatsWidget.php
        └── TopOffersWidget.php
```

## Components Summary

### Resources

| Resource | Model | Actions |
|----------|-------|---------|
| `AffiliateSiteResource` | `AffiliateSite` | List, Create, Edit, Delete, Verify |
| `AffiliateOfferResource` | `AffiliateOffer` | List, Create, Edit, Delete, Activate, Pause |
| `AffiliateOfferCategoryResource` | `AffiliateOfferCategory` | List, Create, Edit, Delete, Reorder |
| `AffiliateOfferApplicationResource` | `AffiliateOfferApplication` | List, View, Approve, Reject, Revoke |

### Pages

| Page | URL | Description |
|------|-----|-------------|
| `MerchantDashboardPage` | `/affiliate-network/merchant-dashboard` | Merchant analytics dashboard |
| `AffiliateMarketplacePage` | `/affiliate-network/marketplace` | Offer discovery for affiliates |

### Widgets

| Widget | Type | Description |
|--------|------|-------------|
| `NetworkStatsWidget` | StatsOverview | 6 stat cards (sites, offers, applications, clicks, rate, revenue) |
| `TopOffersWidget` | Table | Top 10 offers by clicks with performance metrics |

## Screenshots

### Site Management

The site resource provides:
- Domain registration with unique validation
- Status badge colors (pending: yellow, verified: green, suspended/rejected: red)
- Quick verify action for pending sites
- Offers count display

### Offer Management

The offer resource provides:
- Site and category selection
- Commission configuration (percentage or fixed amount)
- Featured and public toggles
- Date range scheduling
- Quick activate/pause actions

### Application Review

The application resource provides:
- Pending applications queue
- One-click approve/reject with reasons
- Revoke action for approved applications
- Bulk approve capability

### Marketplace

The marketplace page provides:
- Search by name/description
- Category filtering
- Sort by featured, newest, or commission
- Apply to offers with reason
- Generate tracking links for approved offers

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament v5
- `aiarmada/affiliate-network` package
- `aiarmada/filament-affiliates` package (recommended)

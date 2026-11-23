---
title: Overview
---

# Filament Affiliate Network Plugin

## Purpose

`aiarmada/filament-affiliate-network` is the Filament v5 admin and marketplace
adapter for `aiarmada/affiliate-network`. It renders and dispatches; all domain
rules, calculations, and persistence live in the core package.

## What this package owns

- Filament resources for sites, offers, categories, applications, and merchant statements
- Six offer relation managers (creatives, links, legs, invitations, deals, coupons)
- Merchant dashboard page
- Network stats and top-offers widgets
- Filament action workflows for verification, catalog sync/token rotation, application review, offer state, leg adjust/lock/reverse, and statement lifecycle

## What this package does not own

- Site verification logic, link tracking, offer persistence, money math, or statements — those stay in `aiarmada/affiliate-network`
- Core affiliate attribution or payout logic — those stay in `aiarmada/affiliates`
- Tenant resolution itself; it consumes the owner context supplied by the host app and `commerce-support`

## Related packages

- [`aiarmada/affiliate-network`](../../affiliate-network/docs/01-overview.md) — core marketplace, enrollment, ledger, statements, postbacks
- [`aiarmada/affiliates`](../../affiliates/docs/01-overview.md) — affiliate identities and merchant commission domain
- [`aiarmada/filament-affiliates`](../../filament-affiliates/docs/01-overview.md) — complementary affiliate admin and portal UI

## Surfaces

### Resources

| Resource | Model | Pages | Notes |
|---|---|---|---|
| `AffiliateSiteResource` | `Features\Sites\Models\AffiliateSite` | List, Create, Edit | Settlement/locking fields, sync + token rotation header actions |
| `AffiliateOfferResource` | `Features\Catalog\Models\AffiliateOffer` | List, Create, Edit | Caps, fallback, event rates, promotions, six relation tabs |
| `AffiliateOfferCategoryResource` | `Features\Catalog\Models\AffiliateOfferCategory` | List, Create, Edit | Hierarchical, cycle-safe |
| `AffiliateOfferApplicationResource` | `Features\Enrollment\Models\AffiliateOfferApplication` | List, View | Approve/reject/revoke + bulk approve |
| `MerchantStatementResource` | `Features\Ledger\Models\MerchantStatement` | List, View | **Admin-only**; Generate/Issue/Mark Paid/Void |

### Offer relation managers

| Manager | Relationship | Actions |
|---|---|---|
| `CreativesRelationManager` | `creatives` | Create/update/delete manual creatives; imported read-only |
| `LinksRelationManager` | `links` | Stats + reconcile |
| `LegsRelationManager` | `legs` | Adjust, Lock matured, Reverse |
| `InvitationsRelationManager` | `invitations` | Invite affiliate, Revoke |
| `DealsRelationManager` | `deals` | Upsert deal, Revoke |
| `CouponsRelationManager` | `coupons` | Assign coupon, Revoke |

### Pages / widgets

| Surface | Description |
|---|---|
| `MerchantDashboardPage` | Owner-scoped merchant analytics at `/affiliate-network/merchant-dashboard` |
| `NetworkStatsWidget` | Network-wide stats (6 cards), 30s `OwnerCache` |
| `TopOffersWidget` | Network-wide top-10 offers by clicks, 30s `OwnerCache` |

## Plugin architecture

```
filament-affiliate-network/
├── config/filament-affiliate-network.php
├── resources/views/pages/merchant-dashboard.blade.php
└── src/
    ├── FilamentAffiliateNetworkPlugin.php
    ├── FilamentAffiliateNetworkServiceProvider.php
    ├── Actions/SyncSiteCatalog.php
    ├── Pages/MerchantDashboardPage.php
    ├── Policies/
    │   ├── AffiliateSitePolicy.php
    │   ├── AffiliateOfferPolicy.php
    │   ├── AffiliateOfferCategoryPolicy.php
    │   ├── AffiliateOfferApplicationPolicy.php
    │   └── MerchantStatementPolicy.php
    ├── Resources/
    │   ├── AffiliateSiteResource/{Pages,Schemas,Tables}
    │   ├── AffiliateOfferResource/{Pages,Schemas,Tables,RelationManagers}
    │   ├── AffiliateOfferCategoryResource/{Pages,Schemas,Tables}
    │   ├── AffiliateOfferApplicationResource/{Pages,Schemas,Tables}
    │   └── MerchantStatementResource/{Pages,Schemas,Tables}
    ├── Support/{NetworkAdminAccess,NetworkStatsAggregator}
    └── Widgets/{NetworkStatsWidget,TopOffersWidget}
```

## Core domain imports

All PHP snippets in this package use the Modular Dune namespaces:

```php
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\{NetworkConversionLeg, MerchantStatement};
use AIArmada\AffiliateNetwork\Data\{OfferStatus, SiteStatus, LegStatus, SettlementStatus};
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
```

> **warning**
> The pre-Dune namespaces (`AIArmada\AffiliateNetwork\{Models,Actions,Services,Enums,Support}\*`)
> are removed. Update any custom resource/policy to the `Features\*` namespaces.

## Owner scoping and security

- Resources mirror the core relationship scoping (`offer.site`, `affiliate`).
- Filters are not authorization: action handlers revalidate the submitted ID
  server-side (core actions re-assert owner access).
- `MerchantStatementResource` and the two widgets are deliberate network-wide
  admin surfaces: they bypass owner scope and gate on
  `NetworkAdminAccess::allows()` (`authorization.admin_ability`).
- The merchant dashboard is owner-scoped and never embeds the network-wide widgets.

## Requirements

- PHP 8.5+
- Laravel 13+
- Filament v5
- `aiarmada/affiliate-network`
- `aiarmada/affiliates` (optional; engine-backed seams)
- `aiarmada/filament-affiliates` (optional complementary UI)

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Pages and widgets](05-pages-widgets.md)
- [Customization](06-customization.md)
- [Actions reference](07-actions-reference.md)
- [Testing](08-testing.md)
- [Troubleshooting](99-troubleshooting.md)
- [Core affiliate-network overview](../../affiliate-network/docs/01-overview.md)

---
title: Installation
---

# Installation

## Requirements

- PHP 8.5+
- Laravel 13+
- Filament v5
- `aiarmada/affiliate-network`
- `aiarmada/affiliates` (optional; engine-backed seams)

## Install via Composer

```bash
composer require aiarmada/filament-affiliate-network
```

`aiarmada/affiliate-network` is installed as a dependency.
`aiarmada/affiliates` and `aiarmada/filament-affiliates` remain optional.

## Publish Configuration

```bash
php artisan vendor:publish --tag=filament-affiliate-network-config
```

## Register the Plugin

```php
use AIArmada\FilamentAffiliateNetwork\FilamentAffiliateNetworkPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentAffiliateNetworkPlugin::make(),
        ]);
}
```

## Authorize the admin ability

Resources, the merchant dashboard, and the network widgets require
`filament-affiliate-network.authorization.admin_ability` (default
`affiliate-network.admin`):

```php
use Illuminate\Support\Facades\Gate;

Gate::define('affiliate-network.admin', fn (User $user): bool => $user->is_admin);
```

Without it, the network-wide surfaces return `403`.

## Publish Views (Optional)

```bash
php artisan vendor:publish --tag=filament-affiliate-network-views
```

## Clear Caches

```bash
php artisan filament:clear-cached-components
php artisan view:clear
php artisan config:clear
```

## What's registered

### Resources

- `AffiliateSiteResource` — sites, verification, catalog sync, token rotation, settlement fields
- `AffiliateOfferResource` — offers, caps, fallback, event rates, promotions, six relation tabs
- `AffiliateOfferCategoryResource` — hierarchical categories
- `AffiliateOfferApplicationResource` — application review
- `MerchantStatementResource` — **admin-only** merchant statements

### Offer relation managers

`CreativesRelationManager`, `LinksRelationManager`, `LegsRelationManager`,
`InvitationsRelationManager`, `DealsRelationManager`, `CouponsRelationManager`.

### Pages

- `MerchantDashboardPage` — owner-scoped merchant analytics

### Widgets

- `NetworkStatsWidget` — network overview stats
- `TopOffersWidget` — top offers by clicks

### Policies

`AffiliateSitePolicy`, `AffiliateOfferPolicy`, `AffiliateOfferCategoryPolicy`,
`AffiliateOfferApplicationPolicy`, `MerchantStatementPolicy` — auto-bound in the
service provider.

## Navigation

Resources appear under the configured navigation group (`Affiliate Network` by
default):

```php
// config/filament-affiliate-network.php
'navigation' => [
    'group' => 'Affiliate Network',
    'sort' => 50,
],
```

Each resource reads `getNavigationGroup()` / `getNavigationSort()` from config,
so the `CommerceNavigation` engine can override them at runtime.

## Verify the install

```bash
php artisan route:list | grep affiliate-network
php artisan about | grep -i filament
./vendor/bin/pest --parallel tests/src/FilamentAffiliateNetwork
```

## Next Steps

1. Configure [settings](03-configuration.md)
2. Verify merchant sites
3. Review [usage](04-usage.md) for every resource and relation tab
4. Gate the admin surfaces behind your authorization layer

---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament v5
- `aiarmada/affiliate-network` package

## Install via Composer

```bash
composer require aiarmada/filament-affiliate-network
```

The core package `aiarmada/affiliate-network` is installed as a dependency.

## Publish Configuration

```bash
php artisan vendor:publish --tag=filament-affiliate-network-config
```

## Register the Plugin

Add the plugin to your Filament panel provider:

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

## Publish Views (Optional)

To customize the marketplace or dashboard views:

```bash
php artisan vendor:publish --tag=filament-affiliate-network-views
```

## Clear Caches

```bash
php artisan filament:clear-cached-components
php artisan view:clear
php artisan config:clear
```

## What's Registered

The plugin automatically registers:

### Resources
- `AffiliateSiteResource` - Manage sites
- `AffiliateOfferResource` - Manage offers
- `AffiliateOfferCategoryResource` - Manage categories
- `AffiliateOfferApplicationResource` - Review applications

### Pages
- `MerchantDashboardPage` - Merchant analytics
- `AffiliateMarketplacePage` - Offer discovery

### Widgets
- `NetworkStatsWidget` - Network overview stats
- `TopOffersWidget` - Top performing offers

## Navigation

Resources appear under the "Affiliate Network" navigation group by default.

To change the group:

```php
// config/filament-affiliate-network.php
'navigation' => [
    'group' => 'Partners',
    'sort' => 50,
],
```

## Next Steps

1. Configure [settings](03-configuration.md)
2. Create merchant sites
3. Publish offers
4. Let affiliates browse the marketplace

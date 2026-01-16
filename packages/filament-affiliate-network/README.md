# Filament Affiliate Network

Filament admin plugin for managing AIArmada affiliate network marketplace.

## Overview

This package provides Filament resources and pages for managing a multi-merchant affiliate network.

## Installation

```bash
composer require aiarmada/filament-affiliate-network
```

## Configuration

Register the plugin in your Filament panel:

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

## Features

### Admin Resources
- **Sites** - Manage merchant domains and verification
- **Offers** - Create and manage affiliate offers
- **Categories** - Organize offers into categories
- **Applications** - Review affiliate applications

### Merchant Portal
- Dashboard with site/offer performance
- Offer management
- Creative asset uploads
- Affiliate approval workflow

### Affiliate Marketplace
- Browse available offers
- Apply for offers
- Generate deep tracking links
- View performance statistics

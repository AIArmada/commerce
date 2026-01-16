---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament 5.0+
- `aiarmada/shipping` package

## Install via Composer

```bash
composer require aiarmada/filament-shipping
```

This will also install `aiarmada/shipping` if not already present.

## Register the Plugin

Add the plugin to your Filament panel provider:

```php
<?php

namespace App\Providers\Filament;

use AIArmada\FilamentShipping\FilamentShippingPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->plugins([
                FilamentShippingPlugin::make(),
            ]);
    }
}
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=filament-shipping-config
```

## Publish Views (Optional)

```bash
php artisan vendor:publish --tag=filament-shipping-views
```

## Plugin Configuration

Configure the plugin fluently in your panel provider:

```php
FilamentShippingPlugin::make()
    // Enable/disable resources
    ->enableShipmentResource()
    ->enableShippingZoneResource()
    ->enableReturnAuthorizationResource()
    
    // Enable/disable pages
    ->enableDashboard()
    ->enableFulfillmentQueue()
    ->enableManifestPage()
    
    // Enable/disable widgets
    ->enableWidgets()
```

Or disable specific features:

```php
FilamentShippingPlugin::make()
    ->disableFulfillmentQueue()
    ->disableManifestPage()
```

## Required Permissions

The package uses Laravel policies for authorization. Ensure your users have the appropriate permissions:

| Permission | Description |
|------------|-------------|
| `view_any_shipment` | View shipment list |
| `view_shipment` | View individual shipment |
| `create_shipment` | Create new shipments |
| `update_shipment` | Edit shipments |
| `delete_shipment` | Delete shipments |
| `cancel_shipment` | Cancel shipments |
| `ship_shipment` | Mark as shipped |

Similar permissions exist for `ShippingZone` and `ReturnAuthorization`.

## Verification

After installation, navigate to your Filament panel. You should see:

1. **Shipping** navigation group
2. **Shipments** resource
3. **Shipping Zones** resource  
4. **Return Authorizations** resource
5. **Shipping Dashboard** page
6. **Fulfillment Queue** page (if Orders package installed)
7. **Manifest** page

If any items are missing, check:
- Plugin is registered in panel provider
- User has required permissions
- Feature is enabled in config/plugin

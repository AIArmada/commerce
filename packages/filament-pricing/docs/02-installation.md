---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament v5
- `aiarmada/pricing` package

## Install via Composer

```bash
composer require aiarmada/filament-pricing
```

The package auto-registers its service provider.

## Register the Plugin

Add the plugin to your Filament panel provider:

```php
use AIArmada\FilamentPricing\FilamentPricingPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ... other configuration
        ->plugins([
            FilamentPricingPlugin::make(),
        ]);
}
```

## Publish Views (Optional)

```bash
php artisan vendor:publish --tag=filament-pricing-views
```

This publishes the Blade views to `resources/views/vendor/filament-pricing/`.

## Setup Base Package

Ensure the base pricing package is properly configured:

```bash
# Publish pricing config
php artisan vendor:publish --tag=pricing-config

# Run migrations
php artisan migrate
```

## Promotions UI Modes

Filament Pricing supports two promotion-management modes:

1. **Fallback promotions UI** — install `aiarmada/promotions` and let Filament Pricing register its legacy `PromotionResource`.
2. **Dedicated promotions UI** — install `aiarmada/filament-promotions` as well. In this mode the dedicated promotions plugin owns the navigation/resource and Filament Pricing keeps handling price lists, pricing settings, simulator flows, and pricing stats.

## Optional: Enable Fallback Promotions

To enable the fallback Promotions resource inside Filament Pricing, install the promotions package:

```bash
composer require aiarmada/promotions
```

The fallback `PromotionResource` will automatically appear in the Pricing navigation.

## Optional: Use the Dedicated Promotions Plugin

If you want a dedicated promotions admin surface, install the Filament Promotions plugin alongside Filament Pricing:

```bash
composer require aiarmada/filament-promotions
```

Register both plugins in your Filament panel provider. When both are installed, `aiarmada/filament-promotions` owns the promotions navigation/resource and Filament Pricing skips its fallback promotions resource.

See [Filament Promotions Installation](../../filament-promotions/docs/02-installation.md) for the dedicated setup flow.

## Optional: Enable Price Simulator

To enable the Price Simulator page, install the products package:

```bash
composer require aiarmada/products
```

The simulator also benefits from the customers package:

```bash
composer require aiarmada/customers
```

## Verify Installation

After installation, you should see:

1. **Price Lists** resource in the Pricing navigation group
2. **Pricing Settings** page in the Settings navigation group
3. **Pricing Stats** widget on the dashboard

If only the promotions package is installed:
- **Promotions** resource in the Pricing navigation group

If the dedicated promotions plugin is also installed:
- **Promotions** resource in the navigation group configured by `aiarmada/filament-promotions`

If products package is installed:
- **Price Simulator** page in the Pricing navigation group

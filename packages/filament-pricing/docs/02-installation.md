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

## Optional: Enable Promotions

To enable the Promotions resource, install the promotions package:

```bash
composer require aiarmada/promotions
```

The `PromotionResource` will automatically appear in the navigation.

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

If promotions package is installed:
- **Promotions** resource in the Pricing navigation group

If products package is installed:
- **Price Simulator** page in the Pricing navigation group

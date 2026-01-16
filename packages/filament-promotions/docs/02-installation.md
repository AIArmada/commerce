---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament 5.0+
- aiarmada/promotions package

## Composer Installation

```bash
composer require aiarmada/filament-promotions
```

This will also install the `aiarmada/promotions` package as a dependency.

## Plugin Registration

Register the plugin in your Filament panel provider:

```php
use AIArmada\FilamentPromotions\FilamentPromotionsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugins([
                FilamentPromotionsPlugin::make(),
            ]);
    }
}
```

## Configuration

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=filament-promotions-config
```

This creates `config/filament-promotions.php`.

## Database Migrations

Ensure the promotions migrations have been run:

```bash
php artisan vendor:publish --tag=promotions-migrations
php artisan migrate
```

## Verifying Installation

After installation:

1. Navigate to your Filament admin panel
2. Look for "Promotions" in the navigation (under "Marketing" group by default)
3. You should see an empty promotions table

## Next Steps

- Configure the plugin: [Configuration](03-configuration.md)
- Learn about the resource: [Usage](04-usage.md)

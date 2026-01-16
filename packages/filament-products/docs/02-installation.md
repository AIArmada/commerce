---
title: Installation
---

# Installation

## Composer Installation

```bash
composer require aiarmada/filament-products
```

This will also install `aiarmada/products` as a dependency.

## Register Plugin

Add the plugin to your Filament panel provider:

```php
// app/Providers/Filament/AdminPanelProvider.php

use AIArmada\FilamentProducts\FilamentProductsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            FilamentProductsPlugin::make(),
        ]);
}
```

## Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=filament-products-config
```

## Publish Views (Optional)

```bash
php artisan vendor:publish --tag=filament-products-views
```

## Publish Translations (Optional)

```bash
php artisan vendor:publish --tag=filament-products-translations
```

## Run Migrations

If you haven't already run the products migrations:

```bash
php artisan migrate
```

## Plugin Configuration

The plugin supports fluent configuration:

```php
FilamentProductsPlugin::make()
    ->navigationGroup('Catalog')
    ->navigationSort(10);
```

### Available Methods

| Method | Description |
|--------|-------------|
| `navigationGroup(string $group)` | Set the navigation group |
| `navigationSort(int $sort)` | Set the navigation sort order |

## Multi-Tenancy Setup

The plugin automatically respects owner scoping from `commerce-support`. Ensure your panel has the owner resolver configured:

```php
// AppServiceProvider.php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

public function register(): void
{
    $this->app->bind(OwnerResolverInterface::class, YourOwnerResolver::class);
}
```

All resources will automatically scope queries and validate foreign IDs against the current owner.

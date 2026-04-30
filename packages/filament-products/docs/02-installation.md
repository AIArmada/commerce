---
title: Installation
---

# Installation

## Install the package

```bash
composer require aiarmada/filament-products
```

## Register the plugin

```php
use AIArmada\FilamentProducts\FilamentProductsPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentProductsPlugin::make(),
        ]);
}
```

## Publish config if needed

```bash
php artisan vendor:publish --tag=filament-products-config
```

## Make sure Products is installed

`filament-products` depends on the `products` package and its migrations. Run the products migrations if you have not already:

```bash
php artisan migrate
```

## Configure owner resolution

The Filament package follows the owner context from `commerce-support`, so your app must bind an `OwnerResolverInterface` implementation.

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

public function register(): void
{
    $this->app->bind(OwnerResolverInterface::class, YourOwnerResolver::class);
}
```

Once that is in place, the plugin resources and pages will scope queries and revalidate submitted foreign IDs against the current owner.

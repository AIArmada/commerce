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

Owner scoping is off by default (`products.features.owner.enabled`). For a single-tenant or global catalog no owner setup is required.

For multi-tenant installs, enable owner scoping and bind an `OwnerResolverInterface` implementation so the panel can resolve the current owner:

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

public function register(): void
{
    $this->app->bind(OwnerResolverInterface::class, YourOwnerResolver::class);
}
```

Once both are in place, the plugin resources and pages will scope queries and revalidate submitted foreign IDs against the current owner. If you enable owner scoping without a resolver, panel requests must instead enter explicit global context (`OwnerContext::withOwner(null, ...)` or `SetExplicitGlobalOwnerContext`).

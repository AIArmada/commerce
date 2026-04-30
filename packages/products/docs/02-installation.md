---
title: Installation
---

# Installation

## Install the package

```bash
composer require aiarmada/products
```

## Publish config

```bash
php artisan vendor:publish --tag=products-config
```

## Run migrations

```bash
php artisan migrate
```

With the default config, the package creates these tables:

- `products`
- `product_variants`
- `product_options`
- `product_option_values`
- `product_variant_options`
- `product_categories`
- `category_product`
- `product_collections`
- `collection_product`
- `product_attributes`
- `product_attribute_groups`
- `product_attribute_values`
- `product_attribute_sets`
- `product_attribute_attribute_group`
- `product_attribute_attribute_set`
- `product_attribute_group_attribute_set`

## Configure owner resolution

`aiarmada/products` expects `commerce-support` to resolve the current owner.

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

public function register(): void
{
    $this->app->bind(OwnerResolverInterface::class, YourOwnerResolver::class);
}
```

When owner mode is enabled:

- owned records are auto-assigned to the resolved owner by default
- owner-scoped reads fail fast if no owner is resolved
- intentional global records should be created inside `OwnerContext::withOwner(null, ...)`

## First smoke test

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Product;

OwnerContext::withOwner($team, function (): void {
    Product::query()->create([
        'name' => 'Starter Product',
        'slug' => 'starter-product',
        'price' => 1999,
    ]);
});
```

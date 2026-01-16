---
title: Installation
---

# Installation

## Composer Installation

```bash
composer require aiarmada/products
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=products-config
```

## Run Migrations

```bash
php artisan migrate
```

This creates the following tables (with configurable prefix `products_`):

| Table | Purpose |
|-------|---------|
| `products_products` | Main products table |
| `products_variants` | Product variants |
| `products_options` | Option types (Size, Color) |
| `products_option_values` | Option values (S, M, L, Red, Blue) |
| `products_option_value_variant` | Pivot: variant ↔ option values |
| `products_categories` | Product categories (nested) |
| `products_category_product` | Pivot: product ↔ categories |
| `products_collections` | Product collections |
| `products_collection_product` | Pivot: product ↔ collections |
| `products_attributes` | Custom attribute definitions |
| `products_attribute_groups` | Attribute groupings |
| `products_attribute_sets` | Sets of attribute groups |
| `products_attribute_values` | Actual attribute values |
| `products_attribute_attribute_group` | Pivot: attribute ↔ groups |

## Configuration

After publishing, edit `config/products.php`:

```php
return [
    // Database settings
    'database' => [
        'table_prefix' => 'products_',
        'tables' => [
            // Override specific table names if needed
        ],
        'json_column_type' => 'json', // or 'text' for MySQL < 5.7
    ],

    // Default currency
    'default_currency' => 'MYR',

    // Variant generation
    'variants' => [
        'auto_generate_sku' => true,
        'sku_separator' => '-',
    ],

    // Media collections
    'media' => [
        'collections' => ['gallery', 'hero', 'icon', 'banner', 'videos', 'documents'],
        'conversions' => [
            'thumb' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 400, 'height' => 400],
            'large' => ['width' => 800, 'height' => 800],
        ],
    ],

    // Owner mode (multi-tenancy)
    'owner_mode' => 'enabled',
];
```

## Service Provider

The package auto-registers via Laravel's package discovery. For manual registration:

```php
// config/app.php
'providers' => [
    // ...
    AIArmada\Products\ProductsServiceProvider::class,
],
```

## Multi-Tenancy Setup

Products uses `commerce-support` for owner scoping. Configure your owner resolver:

```php
// AppServiceProvider.php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

public function register(): void
{
    $this->app->bind(OwnerResolverInterface::class, YourOwnerResolver::class);
}
```

All product-related models will automatically scope to the resolved owner.

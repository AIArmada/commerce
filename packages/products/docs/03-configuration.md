---
title: Configuration
---

# Configuration

## Full Configuration Reference

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */

    'database' => [
        // Prefix for all product tables
        'table_prefix' => 'products_',

        // Override specific table names
        'tables' => [
            'products' => 'products_products',
            'variants' => 'products_variants',
            'options' => 'products_options',
            'option_values' => 'products_option_values',
            'option_value_variant' => 'products_option_value_variant',
            'categories' => 'products_categories',
            'category_product' => 'products_category_product',
            'collections' => 'products_collections',
            'collection_product' => 'products_collection_product',
            'attributes' => 'products_attributes',
            'attribute_groups' => 'products_attribute_groups',
            'attribute_sets' => 'products_attribute_sets',
            'attribute_values' => 'products_attribute_values',
            'attribute_attribute_group' => 'products_attribute_attribute_group',
        ],

        // JSON column type (use 'text' for older MySQL)
        'json_column_type' => 'json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    */

    'default_currency' => 'MYR',

    /*
    |--------------------------------------------------------------------------
    | Variant Generation
    |--------------------------------------------------------------------------
    */

    'variants' => [
        // Auto-generate SKU for variants
        'auto_generate_sku' => true,

        // SKU separator between product SKU and option values
        'sku_separator' => '-',
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Configuration
    |--------------------------------------------------------------------------
    */

    'media' => [
        // Available media collections
        'collections' => [
            'gallery',      // Product image gallery
            'hero',         // Hero/featured images
            'icon',         // Icons/thumbnails
            'banner',       // Banner images
            'videos',       // Product videos
            'documents',    // Downloadable documents
        ],

        // Image conversions
        'conversions' => [
            'thumb' => [
                'width' => 150,
                'height' => 150,
            ],
            'medium' => [
                'width' => 400,
                'height' => 400,
            ],
            'large' => [
                'width' => 800,
                'height' => 800,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner Mode (Multi-tenancy)
    |--------------------------------------------------------------------------
    */

    'owner_mode' => 'enabled', // 'enabled' or 'disabled'
];
```

## Environment Variables

```env
# Optional: Override default currency
PRODUCTS_DEFAULT_CURRENCY=MYR

# Optional: Disable owner scoping
PRODUCTS_OWNER_MODE=enabled
```

## Table Name Customization

Override any table name in the configuration:

```php
'tables' => [
    'products' => 'my_custom_products_table',
],
```

Models use `getTable()` method to resolve table names dynamically:

```php
// In Product model
public function getTable(): string
{
    $tables = config('products.database.tables', []);
    $prefix = config('products.database.table_prefix', 'products_');

    return $tables['products'] ?? $prefix.'products';
}
```

## Media Conversions

Add custom conversions in your model or configuration:

```php
// Custom conversion in Product model
public function registerMediaConversions(Media $media = null): void
{
    $conversions = config('products.media.conversions', []);

    foreach ($conversions as $name => $dimensions) {
        $this->addMediaConversion($name)
            ->fit(Fit::Contain, $dimensions['width'], $dimensions['height'])
            ->nonQueued();
    }

    // Add custom conversion
    $this->addMediaConversion('webp')
        ->format('webp')
        ->nonQueued();
}
```

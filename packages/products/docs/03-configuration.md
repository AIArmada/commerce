---
title: Configuration
---

# Configuration

## Published config shape

```php
return [
    'database' => [
        'table_prefix' => 'product_',
        'json_column_type' => env('PRODUCTS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => [
            'products' => 'products',
            'variants' => 'product_variants',
            'options' => 'product_options',
            'option_values' => 'product_option_values',
            'variant_options' => 'product_variant_options',
            'categories' => 'product_categories',
            'category_product' => 'category_product',
            'collections' => 'product_collections',
            'collection_product' => 'collection_product',
            'attributes' => 'product_attributes',
            'attribute_groups' => 'product_attribute_groups',
            'attribute_values' => 'product_attribute_values',
            'attribute_sets' => 'product_attribute_sets',
            'attribute_attribute_group' => 'product_attribute_attribute_group',
            'attribute_attribute_set' => 'product_attribute_attribute_set',
            'attribute_group_attribute_set' => 'product_attribute_group_attribute_set',
        ],
    ],

    'defaults' => [
        'currency' => 'MYR',
        'store_money_in_cents' => true,
    ],

    'features' => [
        'owner' => [
            'enabled' => true,
            'include_global' => false,
            'auto_assign_on_create' => true,
        ],
        'variants' => [
            'sku_pattern' => '{parent_sku}-{option_codes}',
        ],
    ],

    'media' => [
        'collections' => [
            'gallery' => [...],
            'hero' => [...],
            'icon' => [...],
            'banner' => [...],
            'videos' => [...],
            'documents' => [...],
            'variant_images' => [...],
        ],
        'conversions' => [
            'thumbnail' => [...],
            'card' => [...],
            'detail' => [...],
            'zoom' => [...],
            'webp-card' => [...],
        ],
    ],

    'seo' => [
        'slug_max_length' => 100,
    ],
];
```

## Key settings

### Database

- `database.table_prefix` is the fallback prefix used by model `getTable()` methods.
- `database.tables.*` lets you override specific table names.
- `database.json_column_type` is used for JSON-capable migrations and supports older database engines via `text`.

### Defaults

- `defaults.currency` is the fallback currency used by money helpers.
- `defaults.store_money_in_cents` controls whether raw stored values are minor units.

### Owner behavior

- `features.owner.enabled` toggles owner enforcement for package models.
- `features.owner.include_global` controls whether owner-scoped reads may include global rows.
- `features.owner.auto_assign_on_create` controls whether owned rows inherit the current owner automatically.

### Variant behavior

- `features.variants.sku_pattern` is used by `Variant::generateSku()`.

### Media

The package reads collection limits and mime rules from `media.collections.*`, and image conversion sizes from `media.conversions.*`.

### SEO

- `seo.slug_max_length` is used by product and category slug generation.

## Environment variables

Only the JSON column type is environment-driven by default:

```env
PRODUCTS_JSON_COLUMN_TYPE=json
COMMERCE_JSON_COLUMN_TYPE=json
```

If you want different owner or currency defaults, publish the config and change the values directly.

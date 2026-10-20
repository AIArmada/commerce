---
title: Configuration
---

# Configuration

## Published config shape

```php
return [
    'database' => [
        'table_prefix' => 'product_',
        'json_column_type' => env('PRODUCTS_JSON_COLUMN_TYPE', 'jsonb'),
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
    ],

    'features' => [
        'owner' => [
            'enabled' => env('PRODUCTS_OWNER_ENABLED', true),
            'include_global' => false,
            'auto_assign_on_create' => true,
        ],
        'variants' => [
            'sku_pattern' => '{parent_sku}-{option_codes}',
            'max_generated' => 200,
            'queue_threshold' => 50,
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
- Migrations use the explicit `database.tables.*` names. The prefix remains a runtime fallback for installations that remap or omit a table entry.

### Defaults

- `defaults.currency` is the fallback currency used by money helpers.
- Product, variant, compare, and cost prices are always stored and exchanged as integer minor units.

### Owner behavior

- `features.owner.enabled` toggles owner enforcement for package models and defaults to secure `true` (override with `PRODUCTS_OWNER_ENABLED=false` only for an explicitly global installation).
- `features.owner.include_global` controls whether owner-scoped reads and writes may include global rows.
- `features.owner.auto_assign_on_create` controls whether owned rows inherit the current owner automatically.
- Catalog parent references (`Option.product_id`, `OptionValue.option_id`, `Variant.product_id`, `AttributeValue.attribute_id`, and the `AttributeValue` attributable tuple) are validated against the current owner context on create. With `include_global` off, tenant contexts cannot attach children to global parents or reference global attribute definitions; with it on, global parents and definitions are accepted. Parent and definition identity is immutable afterwards regardless of this flag.

### Variant behavior

- `features.variants.sku_pattern` is used by `Variant::generateSku()` and by matrix generation for newly inserted variants. With the built-in default (`{parent_sku}-{option_codes}`), generated SKUs append a bounded deterministic suffix derived from the variant's combination key so ambiguous option-code boundaries cannot collapse distinct combinations. The complete SKU is also bounded to the 255-character `sku` column: the readable prefix is trimmed (reserving the separators and the collision suffix) while the identity suffix is preserved, so collision handling and regeneration stay within the same bound. Any other pattern is treated as custom and used verbatim; it must produce a unique SKU per combination, and a candidate that collapses two combinations, exceeds the 255-character column, or is already held by another record fails generation before any write.
- `features.variants.max_generated` caps one matrix generation request at 200 variants.
- `features.variants.queue_threshold` queues generation above 50 combinations. Generation is atomic (one transaction per run), and variants are matched by stored combination key rather than SKU text, so retries never insert duplicates and distinct combinations never merge.

### Variant generation

Variant generation is driven by the `VariantGeneratorInterface` contract. The default implementation is `MatrixVariantGenerator`, which builds a cartesian-product matrix from a product's options.

To register a custom generator, bind your implementation in a service provider:

```php
use AIArmada\Products\Contracts\VariantGeneratorInterface;
use App\Services\CustomVariantGenerator;

$this->app->bind(VariantGeneratorInterface::class, CustomVariantGenerator::class);
```

The `GenerateVariants` action resolves the bound implementation and delegates generation to it.

### Media

The package reads collection limits and mime rules from `media.collections.*`, and image conversion sizes from `media.conversions.*`. The `hero`, `icon`, and `banner` collections each default to a single file; change their `limit` values in published config when a different cardinality is required.

### SEO

- `seo.slug_max_length` is used by product and category slug generation.
- Product slug and SKU identity are enforced by the `(owner_type, owner_id, identity)` tuple. Development/test migrations install separate partial unique indexes for owner-scoped and global rows; no derived scope column participates in identity.

## Environment variables

Only the JSON column type is environment-driven by default:

```env
PRODUCTS_JSON_COLUMN_TYPE=json
COMMERCE_JSON_COLUMN_TYPE=json
```

If you want different owner or currency defaults, publish the config and change the values directly.

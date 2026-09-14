---
title: Configuration
---

# Configuration

## Published config

```php
return [
    'features' => [
        'collections' => true,
        'attributes' => true,
    ],
    'import' => [
        'max_rows' => 1000,
        'max_file_kb' => 10240,
    ],
];
```

## Navigation Configuration

Configure navigation group and resource sort order:

```php
'navigation' => [
    'group' => 'Catalog',
    'resources' => [
        'products' => 1,
        'categories' => 2,
        'collections' => 3,
        'attributes' => 40,
        'attribute_groups' => 41,
        'attribute_sets' => 42,
    ],
],
```

## Feature flags

### `features.collections`

Controls whether `CollectionResource` is registered.

### `features.attributes`

Controls whether `AttributeResource` is registered.

## Import limits

### `import.max_rows`

Maximum CSV rows accepted per product import. Larger files are rejected before any row is written.

### `import.max_file_kb`

Maximum upload size (kilobytes) for the product CSV import file.

## What is not configurable here

This package does not currently expose config-driven resource overrides, table polling, or navigation-group customization. Those details are defined in the shipped resource and page classes.

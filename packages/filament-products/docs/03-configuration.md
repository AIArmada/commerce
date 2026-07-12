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

## What is not configurable here

This package does not currently expose config-driven resource overrides, table polling, or navigation-group customization. Those details are defined in the shipped resource and page classes.

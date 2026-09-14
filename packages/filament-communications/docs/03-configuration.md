---
title: Configuration
---

# Configuration

## Navigation

```php
return [
    'navigation' => [
        'group' => 'Communications',
        'sort' => 80,
    ],
];
```

## Navigation order

All resources share the base `navigation.sort` value. To pin a deterministic
sidebar order, set per-resource offsets (added to the base sort, default `0`):

```php
return [
    'navigation' => [
        'sort' => 80,
        'offsets' => [
            'communications' => 0,
            'deliveries' => 1,
            'threads' => 2,
            'templates' => 3,
            'preferences' => 4,
            'suppressions' => 5,
            'batches' => 6,
        ],
    ],
];
```

## Widgets

```php
return [
    'widgets' => [
        'delivery_overview' => [
            'enabled' => true,
        ],
    ],
];
```

Disable the delivery status overview widget with
`FILAMENT_COMMUNICATIONS_WIDGET_DELIVERY_OVERVIEW=false`. Totals are cached
per owner for 60 seconds and the widget polls at the same interval.

## Resources

```php
return [
    'resources' => [
        'communications' => [
            'enabled' => true,
        ],
        'deliveries' => [
            'enabled' => true,
        ],
        'threads' => [
            'enabled' => true,
        ],
        'templates' => [
            'enabled' => true,
        ],
        'preferences' => [
            'enabled' => true,
        ],
        'suppressions' => [
            'enabled' => true,
        ],
        'batches' => [
            'enabled' => true,
        ],
    ],
];
```

Each resource reads `getNavigationGroup()` from config, never the static `$navigationGroup` property. This allows runtime overrides through the `CommerceNavigation` engine from `commerce-support`.

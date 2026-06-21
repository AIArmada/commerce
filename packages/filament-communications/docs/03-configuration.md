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

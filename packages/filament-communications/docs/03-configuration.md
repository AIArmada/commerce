---
title: Configuration
---

# Configuration

## Navigation

```php
'navigation' => [
    'group' => 'Communications',
    'sort' => 80,
],
```

## Navigation order

All resources share the base `navigation.sort` value. Each resource also reads an
optional per-resource offset that is added to the base sort, so sidebar order
stays deterministic. The `offsets` map is **not** present in the published config
and is not required — every offset defaults to `0`. Add it to
`config/filament-communications.php` only when you need a pinned order:

```php
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
```

## Widgets

```php
'widgets' => [
    'delivery_overview' => [
        'enabled' => true,
    ],
],
```

Disable the delivery status overview widget with
`FILAMENT_COMMUNICATIONS_WIDGET_DELIVERY_OVERVIEW=false`. Totals are cached
per owner for 60 seconds and the widget polls at the same interval.

## Resources

```php
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
```

Each resource reads `getNavigationGroup()` from config, never the static `$navigationGroup` property. This allows runtime overrides through the `CommerceNavigation` engine from `commerce-support`.

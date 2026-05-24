---
title: Configuration
---

# Configuration

The package exposes a single `filament-events.php` config file.

## Full configuration

```php
return [
    'navigation' => [
        'group' => 'Events',
        'resources' => [
            'series' => 1,
            'events' => 2,
            'occurrences' => 3,
            'venues' => 4,
            'registrations' => 5,
        ],
    ],
];
```

## Navigation group

```php
'navigation' => [
    'group' => 'Events',
],
```

Set this to another group name if you want the resources to appear somewhere else in your panel navigation.

## Navigation sort order

```php
'navigation' => [
    'resources' => [
        'series' => 1,
        'events' => 2,
        'occurrences' => 3,
        'venues' => 4,
        'registrations' => 5,
    ],
],
```

Lower numbers appear earlier in the navigation.

## Owner scoping

This plugin does not define its own owner-scoping config keys.

Instead, the resources rely on the core [`aiarmada/events`](../../events/docs/03-configuration.md) owner-scoping behavior through `OwnerUiScope`. That means the Filament resources follow the same owner visibility rules as the underlying event models.

## Commerce relationships

`RegistrationResource` and `OccurrenceResource` expose commerce links such as products, variants, orders, order items, and customers when those integrations are configured in the core `events` package.

See [`aiarmada/events` configuration](../../events/docs/03-configuration.md) for the underlying model integration keys.

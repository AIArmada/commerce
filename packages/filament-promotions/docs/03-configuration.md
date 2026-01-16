---
title: Configuration
---

# Configuration

The filament-promotions plugin provides configuration for navigation, table behavior, and feature toggles.

## Full Configuration

```php
// config/filament-promotions.php
return [

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation_group' => 'Marketing',

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */

    'resources' => [
        'navigation_sort' => [
            'promotions' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */

    'tables' => [
        'poll' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */

    'features' => [
        'widgets' => true,
    ],

];
```

## Navigation Configuration

### Navigation Group

Control which navigation group the promotions resource appears under:

```php
'navigation_group' => 'Marketing',
```

Set to `null` to display in the root navigation.

### Navigation Sort

Control the order of the resource in navigation:

```php
'resources' => [
    'navigation_sort' => [
        'promotions' => 10, // Lower = higher in list
    ],
],
```

## Table Configuration

### Polling

Enable real-time table updates:

```php
'tables' => [
    'poll' => '30s', // Refresh every 30 seconds
],
```

Set to `null` to disable polling.

## Feature Toggles

### Widgets

Enable or disable the stats widget:

```php
'features' => [
    'widgets' => true,
],
```

## Owner Scoping

Owner scoping is configured in the core promotions package:

```php
// config/promotions.php
'features' => [
    'owner' => [
        'enabled' => true,
        'include_global' => true,
    ],
],
```

The Filament resource respects these settings automatically.

## Extending Configuration

To add custom configuration values, publish the config and add your keys:

```php
// config/filament-promotions.php
return [
    // ... existing config

    'custom' => [
        'show_expired' => false,
        'default_type' => 'percentage',
    ],
];
```

Access in code:

```php
$showExpired = config('filament-promotions.custom.show_expired');
```

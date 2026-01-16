---
title: Configuration
---

# Configuration

All configuration is in `config/filament-shipping.php`.

## Navigation

```php
'navigation' => [
    // Navigation group name
    'group' => 'Shipping',
    
    // Sort order within navigation
    'sort' => 50,
],
```

## Table Settings

```php
// Polling interval for auto-refresh (e.g., '30s', '1m', null to disable)
'table_poll_interval' => '30s',
```

## Shipping Methods

Define available shipping methods for dropdowns:

```php
'shipping_methods' => [
    'standard' => 'Standard Shipping',
    'express' => 'Express Shipping',
    'overnight' => 'Overnight',
    'pickup' => 'Store Pickup',
],
```

## Carriers

Configure carrier options for the UI:

```php
'carriers' => [
    'manual' => ['name' => 'Manual'],
    'poslaju' => ['name' => 'Pos Laju'],
    'jnt' => ['name' => 'J&T Express'],
    'dhl' => ['name' => 'DHL'],
    'fedex' => ['name' => 'FedEx'],
],
```

If empty, carriers are loaded from `config/shipping.php` drivers.

## Features

Toggle features on/off:

```php
'features' => [
    // Show fulfillment queue page
    'fulfillment_queue' => true,
    
    // Show manifest page
    'manifest_page' => true,
    
    // Show shipping dashboard
    'dashboard' => true,
],
```

## Fulfillment Queue

Settings for the fulfillment queue page:

```php
'fulfillment' => [
    // Hours after which order is marked urgent
    'urgent_threshold_hours' => 48,
    
    // Hours after which order is considered "old"
    'old_threshold_hours' => 24,
],
```

## Complete Example

```php
<?php

return [
    'navigation' => [
        'group' => 'Shipping',
        'sort' => 50,
    ],

    'table_poll_interval' => '30s',

    'shipping_methods' => [
        'standard' => 'Standard Shipping',
        'express' => 'Express Shipping',
        'overnight' => 'Overnight',
        'pickup' => 'Store Pickup',
    ],

    'carriers' => [
        'manual' => ['name' => 'Manual'],
        'poslaju' => ['name' => 'Pos Laju'],
        'jnt' => ['name' => 'J&T Express'],
    ],

    'features' => [
        'fulfillment_queue' => true,
        'manifest_page' => true,
        'dashboard' => true,
    ],

    'fulfillment' => [
        'urgent_threshold_hours' => 48,
        'old_threshold_hours' => 24,
    ],
];
```

## Plugin-Level Configuration

You can also configure features via the plugin in your panel provider:

```php
use AIArmada\FilamentShipping\FilamentShippingPlugin;

FilamentShippingPlugin::make()
    ->navigationGroup('Operations')
    ->navigationSort(20)
    ->tablePollInterval('1m')
    ->enableFulfillmentQueue(false)
    ->enableManifestPage(true)
    ->enableDashboard(true);
```

Plugin configuration takes precedence over config file settings.

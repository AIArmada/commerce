---
title: Configuration
---

# Configuration

All configuration is in `config/filament-shipping.php`.

## Navigation

```php
'navigation' => [
    'group' => 'Shipping',
    'sort' => 50,
],

'pages' => [
    'navigation_sort' => [
        'dashboard' => 0,
        'fulfillment_queue' => 1,
        'manifest' => 5,
    ],
],

'resources' => [
    'navigation_sort' => [
        'shipments' => 1,
        'zones' => 2,
        'rates' => 3,
        'returns' => 3,
    ],
],
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
    'enable_fulfillment_queue' => true,
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
        'enable_fulfillment_queue' => true,
    ],

    'fulfillment' => [
        'urgent_threshold_hours' => 48,
        'old_threshold_hours' => 24,
    ],

    'pages' => [
        'navigation_sort' => [
            'dashboard' => 0,
            'fulfillment_queue' => 1,
            'manifest' => 5,
        ],
    ],

    'resources' => [
        'navigation_sort' => [
            'shipments' => 1,
            'zones' => 2,
            'rates' => 3,
            'returns' => 3,
        ],
    ],
];
```

## Plugin-Level Configuration

You can also configure features via the plugin in your panel provider:

```php
use AIArmada\FilamentShipping\FilamentShippingPlugin;

FilamentShippingPlugin::make()
    ->shipmentResource()
    ->shippingZoneResource()
    ->shippingRateResource()
    ->returnAuthorizationResource()
    ->shippingDashboard()
    ->fulfillmentQueue()
    ->manifestPage()
    ->dashboardWidgets();
```

Navigation group and sort settings are read from `config/filament-shipping.php`.

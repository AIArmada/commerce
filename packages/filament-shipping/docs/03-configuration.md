---
title: Configuration
---

# Configuration

All configuration is in `config/filament-shipping.php`.

## Navigation

```php
'navigation' => [
    'group' => 'Shipping',
    'sort' => 40,
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
    'standard' => 'Standard',
    'express' => 'Express',
    'overnight' => 'Overnight',
    'pickup' => 'Self Pickup',
],
```

## Carriers

The shipped config ships an empty `carriers` array:

```php
'carriers' => [
    // Will use shipping.drivers if empty
],
```

Note: no code in this package reads `filament-shipping.carriers`. Carrier
options come from `config/shipping.php` `drivers` instead, so populating this
key has no effect.

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
    'shipping_methods' => [
        'standard' => 'Standard',
        'express' => 'Express',
        'overnight' => 'Overnight',
        'pickup' => 'Self Pickup',
    ],

    'carriers' => [
        // Will use shipping.drivers if empty
    ],

    'features' => [
        'enable_fulfillment_queue' => true,
    ],

    'fulfillment' => [
        'urgent_threshold_hours' => 48,
        'old_threshold_hours' => 24,
    ],

    'navigation' => [
        'group' => 'Shipping',
        'sort' => 40,
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

# Filament J&T Express Plugin

A Filament admin plugin to manage J&T Express shipping orders and tracking.

## Installation

```bash
composer require aiarmada/filament-jnt
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=filament-jnt-config
```

## Configuration

Add the plugin to your Filament panel:

```php
use AIArmada\FilamentJnt\FilamentJntPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            FilamentJntPlugin::make(),
        ]);
}
```

## Features

- 📦 **Shipping Orders** - View and manage J&T Express orders
- 📍 **Tracking Events** - Monitor real-time tracking status updates
- 🔔 **Webhook Logs** - Debug incoming webhook notifications
- 🔍 **Global Search** - Search across orders, tracking numbers, and statuses
- 🔄 **Auto-polling** - Real-time updates without page refresh

## Configuration Options

```php
// config/filament-jnt.php

return [
    'navigation_group' => 'J&T Express',
    'navigation_badge_color' => 'primary',
    'polling_interval' => '30s',

    'resources' => [
        'navigation_sort' => [
            'orders' => 10,
            'tracking_events' => 20,
            'webhook_logs' => 30,
        ],
    ],

    'tables' => [
        'datetime_format' => 'Y-m-d H:i:s',
    ],
];
```

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament 4.2+
- aiarmada/jnt package

## License

MIT License

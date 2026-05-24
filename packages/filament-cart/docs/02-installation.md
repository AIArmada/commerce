---
title: Installation
---

# Installation

Install Filament Cart with Composer:

```bash
composer require aiarmada/filament-cart
```

Publish and run migrations according to your application workflow.

## Tables

Filament Cart creates normalized read models for carts:

| Table | Purpose |
| --- | --- |
| `cart_snapshots` | Normalized cart snapshots |
| `cart_snapshot_items` | Snapshot item rows |
| `cart_snapshot_conditions` | Snapshot condition rows |

The package does not create local metrics, recovery, alert rule, or alert log tables.

## Register the plugin

Register the plugin in your Filament panel provider:

```php
use AIArmada\FilamentCart\FilamentCartPlugin;

$panel->plugins([
    FilamentCartPlugin::make(),
]);
```

## Optional Signals integration

For analytics, reports, and alerts, install Signals and Filament Signals:

```bash
composer require aiarmada/signals aiarmada/filament-signals
```

Enable integrations explicitly in `config/signals.php`:

```php
'integrations' => [
    'cart' => [
        'enabled' => true,
    ],

    'filament_cart' => [
        'enabled' => true,
    ],
],
```

## Commands

Filament Cart ships one operational command:

```bash
php artisan cart:mark-abandoned
php artisan cart:mark-abandoned --minutes=45
php artisan cart:mark-abandoned --dry-run
```

Schedule Signals commands separately when Signals is installed:

```php
Schedule::command('signals:process-alerts')->everyFifteenMinutes();
```

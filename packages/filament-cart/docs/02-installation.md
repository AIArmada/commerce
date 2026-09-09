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

The core Cart package owns the normalized read models. Install and publish the
core migrations before using these Filament resources:

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

The core Cart package ships the operational command:

```bash
php artisan cart:clear-abandoned --mark-only
php artisan cart:clear-abandoned --mark-only --minutes=45
php artisan cart:clear-abandoned --mark-only --dry-run
```

Use `--all-owners --confirm-all-owners` only for an intentional multi-owner
mutation; run a dry-run first.

Schedule Signals commands separately when Signals is installed:

```php
Schedule::command('signals:process-alerts')->everyFifteenMinutes();
```

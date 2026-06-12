---
title: Filament Contacting Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 10+
- `aiarmada/contacting` (with migrations run)
- `filament/filament` ^5.6.7

## Install via Composer

```bash
composer require aiarmada/filament-contacting
```

In a monorepo, ensure the package is autoloaded:

```json
{
    "autoload": {
        "psr-4": {
            "AIArmada\\FilamentContacting\\": "packages/filament-contacting/src"
        }
    }
}
```

## Publish Config

```bash
php artisan vendor:publish --tag=filament-contacting-config
```

## Register Plugin

Add the plugin to your Panel provider:

```php
use AiArmada\FilamentContacting\FilamentContactingPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentContactingPlugin::make(),
        ]);
}
```

## Prerequisites

Ensure `aiarmada/contacting` migrations have run before using the UI:

```bash
php artisan migrate
```
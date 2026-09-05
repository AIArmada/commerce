---
title: Installation
---

# Installation

## Requirements

- PHP 8.4 or newer
- Laravel 11 or newer
- Filament v5
- `aiarmada/persons`

Install the package with Composer:

```bash
composer require aiarmada/filament-persons
```

The service provider and configuration file are registered automatically.

## Register the plugin

Add the plugin to the Filament panel that should expose the resources:

```php
use AIArmada\FilamentPersons\FilamentPersonsPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentPersonsPlugin::make(),
        ]);
}
```

Install and migrate the domain package before using the resources:

```bash
php artisan migrate
```

See the [persons installation guide](../../persons/docs/02-installation.md)
for the domain package setup.

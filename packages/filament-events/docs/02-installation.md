---
title: Installation
---

## Install

```bash
composer require aiarmada/filament-events
```

## Publish configuration

```bash
php artisan vendor:publish --provider="AIArmada\FilamentEvents\FilamentEventsServiceProvider" --tag="config"
```

## Register the plugin

Add the plugin to your Filament panel configuration:

```php
use AIArmada\FilamentEvents\FilamentEventsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentEventsPlugin::make(),
        ]);
}
```

## Configuration

Navigation labels and resource registration are configured in `config/filament-events.php` after publishing the package config.

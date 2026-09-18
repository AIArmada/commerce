---
title: Installation
---

# Installation

## Install the package

```bash
composer require aiarmada/filament-links
```

## Register the plugin

```php
use AIArmada\FilamentLinks\FilamentLinksPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(FilamentLinksPlugin::make());
}
```

## Publish the config

```bash
php artisan vendor:publish --tag=filament-links-config
```

## Read next

- [Configuration](03-configuration.md)
- [Usage](04-usage.md)

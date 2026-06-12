---
title: Installation
---

## Installation

```bash
composer require aiarmada/filament-commerce-support
```

### Register the plugin

Add the plugin to your Filament panel:

```php
use AIArmada\FilamentCommerceSupport\FilamentCommerceSupportPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(FilamentCommerceSupportPlugin::make());
}
```

### Publish config (optional)

```bash
php artisan vendor:publish --tag=filament-commerce-support-config
```

### Run settings migration

The `spatie/laravel-settings` migration creates the `settings` table. If it hasn't been published yet:

```bash
php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag="migrations"
php artisan migrate
```

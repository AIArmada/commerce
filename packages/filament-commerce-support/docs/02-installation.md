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

The plugin registers the **Settings → Navigation** manager page plus the
`currencies`, `languages`, and `timezones` reference-data resources
automatically (each can be omitted via `resources.<name>.enabled`). No
manual `$panel->resources()` calls are needed.

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

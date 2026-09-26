---
title: Filament Organizations Installation
---

## Install

```bash
composer require aiarmada/filament-organizations
```

Register the plugin on each panel that should expose organizations:

```php
use AIArmada\FilamentOrganizations\FilamentOrganizationsPlugin;

$panel->plugins([
    FilamentOrganizationsPlugin::make(),
]);
```

## Publish configuration (optional)

```bash
php artisan vendor:publish --tag=filament-organizations-config
```

This creates `config/filament-organizations.php`.

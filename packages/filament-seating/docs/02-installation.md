---
title: Installation
---

## Install

```bash
composer require aiarmada/filament-seating
```

## Register Plugin

```php
// In a panel provider:
use AIArmada\FilamentSeating\FilamentSeatingPlugin;

$panel->plugins([
    FilamentSeatingPlugin::make(),
]);
```

## Publish Config (optional)

```bash
php artisan vendor:publish --tag=filament-seating-config
```

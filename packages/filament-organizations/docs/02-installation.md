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

---
title: Troubleshooting
---

# Troubleshooting

## Resources do not appear

Confirm that the plugin is registered on the intended panel and that the
resource's `resources.enabled` flag is not `false`:

```php
use AIArmada\FilamentPersons\FilamentPersonsPlugin;

->plugins([
    FilamentPersonsPlugin::make(),
])
```

Clear cached panel components after changing configuration:

```bash
php artisan filament:clear-cached-components
php artisan config:clear
```

## Relationship options are empty

Run the `aiarmada/persons` migrations and verify that the referenced domain
records exist. Title country fields are shown only when the persons model
resolver has a country model configured.

## Unexpected tenant behavior

The persons package is intentionally not tenant-owned. Its resources do not
apply owner scoping. Tenant-specific involvement or assignment records should
be owned by the package that defines that tenant boundary.

For model, migration, and morph-map issues, consult the [persons
troubleshooting guide](../../persons/docs/99-troubleshooting.md).

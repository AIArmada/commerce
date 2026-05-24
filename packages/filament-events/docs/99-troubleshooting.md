---
title: Troubleshooting
---

# Troubleshooting

## The Events navigation group does not appear

### Check plugin registration

Make sure the plugin is registered in your Filament panel provider:

```php
use AIArmada\FilamentEvents\FilamentEventsPlugin;

->plugins([
    FilamentEventsPlugin::make(),
])
```

### Clear cached metadata

```bash
php artisan optimize:clear
```

## The resources appear but the tables are empty

### Check the core events package migrations

`filament-events` depends on the tables from `aiarmada/events`.

```bash
php artisan migrate
```

### Check owner scoping

The resources use owner-scoped queries by default. If your panel resolves a different owner than the records were created under, the tables will look empty even though rows exist.

Review the core owner config in `config/events.php` and the application's owner resolver setup.

## Relationship selectors are missing products, orders, or customers

Those selectors depend on the integration model configuration from the core `events` package.

Review the `events.integrations.*` config in the core package docs:

- product model
- variant model
- customer model
- order model
- order item model

## Check-in or cancel actions fail

The Filament actions delegate to the core `RegistrationService`, so failures usually come from the underlying registration lifecycle rules rather than the Filament layer.

Check:

- the registration status
- whether the occurrence is valid for the requested transition
- any owner-scoping mismatch between the current admin context and the registration record

## Need deeper domain examples?

Use the core package docs for the underlying event lifecycle:

- [Events overview](../../events/docs/01-overview.md)
- [Events configuration](../../events/docs/03-configuration.md)
- [Events usage](../../events/docs/04-usage.md)

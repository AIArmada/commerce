---
title: Troubleshooting
---

## Common Issues

### Resources not appearing in navigation

Check that the resource is enabled in `config/filament-events.php` under `resources.enabled`. If a key is set to `false`, the resource is not registered.

### Model not found in resource table

The Filament resources apply `OwnerUiScope::apply(..., includeGlobal: false)` by default. If the records are global (no owner), they are intentionally hidden. To include global records, you would need to modify the resource's `getEloquentQuery()`.

### Check-in action not visible

The check-in action only appears for passes with `issued` or `active` status. Ensure the pass has been issued and the registration is confirmed.

### "Model not found" relation manager

Relation managers use Eloquent relationships defined in `aiarmada/events` models. If a relationship is missing, check that the core package models define the expected relationship methods.

### Plugin not loading

Ensure the plugin is registered in your panel configuration:

```php
->plugins([
    FilamentEventsPlugin::make(),
])
```

### Custom navigation group not working

Set the navigation group via config:

```php
// config/filament-events.php
'navigation' => [
    'group' => 'My Events',
],
```

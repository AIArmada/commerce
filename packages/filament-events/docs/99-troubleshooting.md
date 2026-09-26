---
title: Troubleshooting
---

## Common Issues

### Resources not appearing in navigation

Check that the resource is enabled in `config/filament-events.php` under `resources.enabled`. If a key is set to `false`, the resource is not registered.

### Model not found in resource table

The Filament resources apply `OwnerUiScope::apply(..., includeGlobal: false)` by default. If the records are global (no owner), they are intentionally hidden. To include global records, you would need to modify the resource's `getEloquentQuery()`.

### Check-in action not visible

The check-in action is gated on `Pass::isValid()`. The pass status must not be `used`, `cancelled`, `revoked`, `voided`, or `expired`, and the linked registration status must not be `refund_pending`, `refunded`, `cancelled`, `canceled`, `rejected`, or `expired`. A `pending`, `issued`, or `activated` pass with a healthy registration is check-in eligible.

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

---
title: Troubleshooting
---

## Common Issues

### Resources not appearing in navigation

Check that the resource is enabled in `config/filament-events.php` under `resources.enabled`. If a key is set to `false`, the resource is not registered.

### Model not found in resource table

Event-bound resources apply `OwnerUiScope::apply(..., includeGlobal: false)`
by default. If the records are global (no owner), they are intentionally
hidden. To include global records, you would need to modify the resource's
`getEloquentQuery()`.

Venue, venue-space, taxonomy, and term resources are global catalogs and are
not owner scoped — if they are empty, check that records exist at all rather
than the owner context.

### Check-in action not visible

The check-in action is gated on `Pass::isValid()`. The pass status must not be `used`, `cancelled`, `revoked`, `voided`, or `expired`, and the linked registration status must not be `refund_pending`, `refunded`, `cancelled`, `canceled`, `rejected`, or `expired`. A `pending`, `issued`, or `activated` pass with a healthy registration is check-in eligible.

### "Model not found" relation manager

Relation managers use Eloquent relationships defined in the resolved
`aiarmada/events` models. If a relationship is missing, check that the
configured model (via `events.models.*` or the package default) defines the
expected relationship methods.

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

Or per resource under `resources.navigation_group`.

### Lifecycle action fails with "Unknown lifecycle target type"

Record actions pass scalar target types (`'event'`, `'occurrence'`,
`'session'`) to `EventLifecycleWorkflow`. A failure here usually means a
custom configuration resolved a model that is not one of the core Catalog
models — custom `events.models.*` classes must extend the Catalog event,
occurrence, and session models for lifecycle actions to work.

### Form extension fields missing

`resources.event_form_extensions` replaces the default list entirely. If you
override it, re-add
`AIArmada\FilamentEvents\Extensions\DefaultEventMediaExtension::class` to keep
the media uploads. Extensions that do not implement the matching contract
(`EventFormExtension`, `EventOccurrenceFormExtension`, `TermFormExtension`)
are skipped.

---
title: Configuration
---

## Configuration file

The `config/filament-events.php` file controls plugin behavior.

### Navigation

```php
'navigation' => [
    'group' => 'Events',
],
```

Customize the navigation group label for all event resources and pages.

### Resource toggles

```php
'resources' => [
    'enabled' => [
        'event' => true,
        'occurrence' => true,
        'session' => true,
        'venue' => true,
        'registration' => true,
        'ticket_type' => true,
        'attendance' => true,
    ],
],
```

Each resource can be individually disabled by setting its key to `false`. The resource will not be registered in the Filament panel or appear in navigation.

### Plugin customization

```php
FilamentEventsPlugin::make()
    ->navigationGroup('My Events')
    ->resources([
        'registration' => false, // disable registrations resource
    ]);
```

The plugin class provides fluent methods to override config values at runtime.

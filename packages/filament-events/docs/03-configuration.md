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
        'registration_participant' => true,
        'ticket_type' => true,
        'attendance' => true,
        'change_log' => true,
    ],
    'navigation_sort' => [
        'event' => 1,
        'occurrence' => 2,
        'session' => 3,
        'venue' => 4,
        'registration' => 10,
        'ticket_type' => 11,
        'registration_participant' => 11,
        'attendance' => 12,
        'change_log' => 99,
    ],
],
```

Each resource can be individually disabled by setting its key to `false`. The resource will not be registered in the Filament panel or appear in navigation.

### Panel registration

```php
FilamentEventsPlugin::make();
```

The plugin reads resource toggles and navigation settings from `config/filament-events.php`.

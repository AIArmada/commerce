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

Customize the navigation group label for all event resources and pages, or
override individual resources such as venues and venue spaces:

```php
'resources' => [
    'navigation_group' => [
        'venue' => 'Places',
        'venue_space' => 'Places',
    ],
],
```

### Resource toggles

```php
'resources' => [
    'enabled' => [
        'event' => true,
        'occurrence' => true,
        'session' => true,
        'venue' => true,
        'venue_space' => true,
        'registration' => true,
        'registration_participant' => true,
        'attendance' => true,
        'change_log' => true,
        'event_template' => true,
        'event_taxonomy' => true,
        'event_term' => true,
    ],
    'navigation_sort' => [
        'event' => 1,
        'occurrence' => 2,
        'session' => 3,
        'venue' => 4,
        'venue_space' => 5,
        'registration' => 10,
        'registration_participant' => 11,
        'attendance' => 12,
        'event_taxonomy' => 20,
        'event_term' => 21,
        'change_log' => 99,
        'event_template' => 98,
    ],
],
```

Each resource can be individually disabled by setting its key to `false`.
The resource will not be registered in the Filament panel or appear in
navigation.

> **info**
> Ticket type and pass administration live in `aiarmada/filament-ticketing`;
> this plugin does not register a ticket type resource.

### Relation manager extensions

Append application-owned relation managers to the event, occurrence, session,
venue, or venue space resources:

```php
'resources' => [
    'event_relation_managers' => [
        App\Filament\Resources\Events\SpeakersRelationManager::class,
    ],
    'occurrence_relation_managers' => [],
    'session_relation_managers' => [],
    'venue_relation_managers' => [],
    'venue_space_relation_managers' => [],
],
```

Each configured class must extend
`Filament\Resources\RelationManagers\RelationManager`. Invalid entries are
skipped.

### Panel registration

```php
FilamentEventsPlugin::make();
```

The plugin reads resource toggles and navigation settings from
`config/filament-events.php`.

### Event form extensions

The event resource accepts form extensions without coupling the package to
application-specific fields such as media conversions or custom metadata. It
ships with one default,
`AIArmada\FilamentEvents\Extensions\DefaultEventMediaExtension` (cover,
poster, and gallery uploads backed by the `events` package media profile):

```php
'resources' => [
    'event_form_extensions' => [
        App\Filament\Resources\Events\CustomEventExtension::class,
    ],
],
```

Each extension must implement
`AIArmada\FilamentEvents\Contracts\EventFormExtension` and return Filament
schema components from `components()`. Setting the list replaces the default
entirely — re-add `DefaultEventMediaExtension::class` if you still want media.
The default renders nothing when the resolved event model does not implement
spatie's `HasMedia`, so custom event models without media stay safe.

### Occurrence form extensions

The occurrence resource supports the same seam through
`resources.occurrence_form_extensions`, with extensions implementing
`AIArmada\FilamentEvents\Contracts\EventOccurrenceFormExtension`. It ships
with no defaults:

```php
'resources' => [
    'occurrence_form_extensions' => [
        App\Filament\Resources\EventOccurrences\CustomOccurrenceExtension::class,
    ],
],
```

### Term form extensions

The term resource accepts form extensions the same way, for
application-specific fields such as taxonomy metadata. It ships with no
defaults:

```php
'resources' => [
    'term_form_extensions' => [
        App\Filament\Resources\EventTerms\CustomTermExtension::class,
    ],
],
```

Each extension must implement
`AIArmada\FilamentEvents\Contracts\TermFormExtension` and return Filament
schema components from `components()`. Invalid entries are skipped.

## Model resolution

Form and table option queries resolve models through
`AIArmada\Events\Platform\ModelResolver`, so overriding `events.models.*` in
the core package config changes which classes the admin surfaces use. Keep
custom models compatible with the relationships and state casts the
resources expect (see `../events/docs/03-configuration.md`).

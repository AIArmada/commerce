---
title: Configuration
---

# Configuration

`filament-ticketing` publishes `config/filament-ticketing.php` for navigation, resource toggles, and ticketable type registration.

## Package Config

```php
return [
    'navigation' => [
        'group' => 'Ticketing',
    ],
    'resources' => [
        'enabled' => [
            'ticket_type' => true,
            'pass' => true,
            'pass_holder' => true,
            'pass_transfer' => true,
        ],
        'navigation_sort' => [
            'ticket_type' => 1,
            'pass' => 2,
            'pass_holder' => 3,
            'pass_transfer' => 4,
        ],
    ],
    'ticketable_types' => [
        // \App\Models\Workshop::class,
    ],
    'allowed_ticketable_types' => [
        // Restrict to specific ticketable types (whitelist). Empty = all registered allowed.
    ],
];
```

## Navigation

```php
'navigation' => [
    'group' => 'Ticketing',
],
```

| Key | Description |
|-----|-------------|
| `navigation.group` | Navigation group label for all ticketing resources |

Resources use `getNavigationGroup()` reading from config:

```php
public static function getNavigationGroup(): string | UnitEnum | null
{
    return config('filament-ticketing.navigation.group');
}
```

This allows runtime overrides via the `CommerceNavigation` engine from `commerce-support`.

## Resources

### Enabling / Disabling

```php
'resources' => [
    'enabled' => [
        'ticket_type' => true,
        'pass' => true,
        'pass_holder' => true,
        'pass_transfer' => true,
    ],
],
```

Set any resource to `false` to hide it from the panel navigation.

### Navigation Sort

```php
'resources' => [
    'navigation_sort' => [
        'ticket_type' => 1,
        'pass' => 2,
        'pass_holder' => 3,
        'pass_transfer' => 4,
    ],
],
```

Lower numbers appear first in the navigation group.

## Ticketable Types

### Registration

```php
'ticketable_types' => [
    \App\Models\Workshop::class,
    \App\Models\Event::class,
    \App\Models\CourseSession::class,
],
```

Each class must implement `AIArmada\Ticketing\Contracts\TicketableInterface`.

You can also register types programmatically:

```php
use AIArmada\FilamentTicketing\Support\TicketableTypeRegistry;

app(TicketableTypeRegistry::class)->register(Workshop::class);
```

### Allowed Types (Whitelist)

```php
'allowed_ticketable_types' => [
    // \App\Models\Workshop::class,
],
```

- When non-empty, only these types are available in resource dropdowns
- When empty, all registered types are allowed
- This is a UI filter, not a security boundary — validate server-side

## Where Configuration Happens

This package is configured through these surfaces:

### Panel Plugin Registration

Register the plugin in each Filament panel that should expose the ticketing resources:

```php
use AIArmada\FilamentTicketing\FilamentTicketingPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        FilamentTicketingPlugin::make(),
    ]);
}
```

### Core Package Configuration

Owner context comes from `commerce-support`; ticketable UI registration lives in this package's config. Ticketing-domain behavior is configured by `config/ticketing.php`.

Important keys to understand before using the Filament plugin:

- `ticketing.database.*` — Table names
- `ticketing.features.auto_issue_passes` — Auto-issue behavior
- `ticketing.transfers.*` — Transfer limits

When owner mode is enabled, the Filament resources inherit those scoping rules.

### Resource and Widget Extension

Customization happens by extending or replacing the package resources in your application:

- `TicketTypeResource`
- `PassResource`
- `PassHolderResource`
- `PassTransferResource`

## Owner-Scoping Behavior

This package does not resolve tenant or owner context itself. It expects the application to provide owner context before resource queries run. The package then applies owner-safe query scoping through shared helpers.

This means:

- Resource list queries are owner-scoped
- Relationship select queries are owner-scoped
- Submitted IDs must still be revalidated by the owning action or service before mutation

## Publishing Config

Publish the package config when you need to customize navigation, resource availability, or ticketable types:

```bash
php artisan vendor:publish --tag=filament-ticketing-config
```

If you need different forms, tables, or widgets, extend the resources in your application or replace them at the panel level.

## Read next

- [Installation](02-installation.md)
- [Usage](04-usage.md)
- [Troubleshooting](99-troubleshooting.md)

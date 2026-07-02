---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament 5.6+
- `aiarmada/ticketing`
- `aiarmada/commerce-support`

## Composer Installation

```bash
composer require aiarmada/filament-ticketing
```

The package auto-discovers via Laravel's package discovery.

## Register the Plugin

Add the plugin to your Filament panel:

```php
use AIArmada\FilamentTicketing\FilamentTicketingPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentTicketingPlugin::make(),
        ]);
}
```

## Register Ticketable Types

Ticketable models must be registered so the admin UI can populate ticketable type dropdowns and resolve relationship selects.

### Via Service Provider

```php
use AIArmada\FilamentTicketing\Support\TicketableTypeRegistry;
use App\Models\Workshop;

public function boot(): void
{
    app(TicketableTypeRegistry::class)->register(Workshop::class);
}
```

### Via Config

Add classes to the published config:

```php
// config/filament-ticketing.php
'ticketable_types' => [
    \App\Models\Workshop::class,
    \App\Models\Event::class,
],
```

Or restrict to specific types:

```php
'allowed_ticketable_types' => [
    \App\Models\Workshop::class,
    // Empty = all registered types are allowed
],
```

## Publish Config

```bash
php artisan vendor:publish --provider="AIArmada\FilamentTicketing\FilamentTicketingServiceProvider" --tag="filament-ticketing-config"
```

This creates `config/filament-ticketing.php`.

## Verification

Verify the plugin is registered:

```bash
php artisan filament:assets
```

Then check that the Ticketing navigation group appears in your Filament panel with the four resources.

## Configuration Recommendations

### Production

```php
// config/filament-ticketing.php
'resources' => [
    'enabled' => [
        'ticket_type' => true,
        'pass' => true,
        'pass_holder' => true,
        'pass_transfer' => true,
    ],
],
'ticketable_types' => [
    \App\Models\Workshop::class,
    \App\Models\Event::class,
],
```

### Development & Testing

Disable resources you don't need:

```php
'resources' => [
    'enabled' => [
        'pass_holder' => false, // Hide if not used
        'pass_transfer' => false, // Hide transfer log
    ],
],
```

## Pending actions

After installation:

1. Run migrations from the core `aiarmada/ticketing` package: `php artisan migrate`
2. Register your ticketable models (see above)
3. Configure navigation group and sort order

## Read next

- [Configuration](03-configuration.md) — Configure the plugin
- [Usage](04-usage.md) — Start using the plugin

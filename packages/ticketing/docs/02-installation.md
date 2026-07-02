---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 11+
- `aiarmada/commerce-support`
- `spatie/laravel-data`
- `spatie/laravel-model-states`
- `spatie/laravel-package-tools`

## Composer Installation

```bash
composer require aiarmada/ticketing
```

The package auto-discovers via Laravel's package discovery. If you disable discovery, register it manually:

```php
// config/app.php
'providers' => [
    AIArmada\Ticketing\TicketingServiceProvider::class,
],
```

## Run Migrations

```bash
php artisan migrate
```

This creates the following tables:

| Table | Description |
|-------|-------------|
| `ticket_ticket_types` | Ticket type configurations |
| `ticket_ticket_type_components` | Ticket pricing components |
| `ticket_ticket_type_products` | Bundle products linked to ticket types |
| `ticket_passes` | Issued passes with state and holder info |
| `ticket_pass_holders` | Pass holder records |
| `ticket_pass_transfers` | Pass transfer audit log |

Table names can be overridden via config or environment variables.

## Publish Config

```bash
php artisan vendor:publish --provider="AIArmada\Ticketing\TicketingServiceProvider" --tag="ticketing-config"
```

This creates `config/ticketing.php` with all available options.

## Scheduled Tasks

If you use auto-expiry, add to your `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('ticketing:expire-passes')->hourly();
```

## Optional Dependencies

These packages are detected at runtime — no hard coupling required:

| Package | Purpose |
|---------|---------|
| `aiarmada/inventory` | Stock tracking and allocation for quotas |
| `aiarmada/cart` | Add ticket types to shopping cart |
| `aiarmada/products` | Ticket-bundled products/variants |
| `aiarmada/orders` | Automatic pass issuance on order paid |
| `aiarmada/checkout` | Checkout step registration |
| `aiarmada/customers` | Pass holder resolution to Customer |

## Verification

Verify the package is working:

```bash
php artisan tinker --execute 'app(\AIArmada\Ticketing\Actions\EnsureTicketTypeAction::class)::class;'
```

Or check that migrations ran:

```bash
php artisan migrate:status | grep ticket_
```

## Configuration Recommendations

### Production

```php
// config/ticketing.php
'features' => [
    'auto_issue_passes' => true, // Auto-issue on order paid
],
'transfers' => [
    'bulk_max_size' => 100,
    'expiry_grace_period' => 0,
],
```

### Development & Testing

```php
// config/ticketing.php
'features' => [
    'auto_issue_passes' => false, // Manually issue during testing
],
```

## Read next

- [Configuration](03-configuration.md) — Configure the package
- [Usage](04-usage.md) — Start using the package

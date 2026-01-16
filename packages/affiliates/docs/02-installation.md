---
title: Installation
---

# Installation

## Requirements

- PHP 8.4 or higher
- Laravel 11.x or higher
- Database with UUID support (MySQL 8+, PostgreSQL, SQLite)

## Install via Composer

```bash
composer require aiarmada/affiliates
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=affiliates-config
```

This creates `config/affiliates.php` with all available options.

## Run Migrations

```bash
php artisan vendor:publish --tag=affiliates-migrations
php artisan migrate
```

The package includes 33 migrations creating all necessary tables with proper indexes.

## Optional: Filament Admin Panel

For a full admin interface:

```bash
composer require aiarmada/filament-affiliates
```

Register the plugin in your Filament panel provider:

```php
use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentAffiliatesPlugin::make(),
        ]);
}
```

## Optional: Cart Integration

If using `aiarmada/cart`, the integration is automatic. The package registers a manager proxy that adds fluent methods to the Cart facade:

```php
Cart::attachAffiliate('CODE123');
Cart::hasAffiliate();
Cart::getAffiliate();
Cart::recordAffiliateConversion([...]);
```

## Optional: Voucher Integration

If using `aiarmada/vouchers`, affiliates are automatically attached when vouchers with affiliate metadata are applied:

```php
// Voucher with affiliate code in metadata
$voucher->metadata = ['affiliate_code' => 'PARTNER42'];
```

## Environment Variables

Key environment variables (all optional with sensible defaults):

```env
# Multi-tenancy
AFFILIATES_OWNER_ENABLED=false

# Currency
AFFILIATES_DEFAULT_CURRENCY=USD

# Cookie tracking
AFFILIATES_COOKIES_ENABLED=true
AFFILIATES_COOKIE_NAME=affiliate_session
AFFILIATES_COOKIE_TTL_MINUTES=43200

# Commission defaults
AFFILIATES_DEFAULT_COMMISSION_RATE=1000
AFFILIATES_AUTO_APPROVE=false

# Payout settings
AFFILIATES_PAYOUT_MINIMUM_AMOUNT=5000
AFFILIATES_PAYOUT_MATURITY_DAYS=30

# Fraud detection
AFFILIATES_FRAUD_ENABLED=true

# API
AFFILIATES_API_ENABLED=false
```

## Verify Installation

Run the following to verify the package is properly installed:

```bash
php artisan affiliates:aggregate-daily-stats --help
```

If you see the command help output, the installation is complete.

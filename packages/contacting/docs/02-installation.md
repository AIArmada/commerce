---
title: Contacting Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 10+
- `aiarmada/commerce-support`

## Install via Composer

```bash
composer require aiarmada/contacting
```

If you are developing in a monorepo with path repository, ensure the package is autoloaded:

```json
{
    "autoload": {
        "psr-4": {
            "AIArmada\\Contacting\\": "packages/contacting/src",
            "AIArmada\\Contacting\\Database\\Factories\\": "packages/contacting/database/factories"
        }
    }
}
```

## Publish Config

```bash
php artisan vendor:publish --tag=contacting-config
```

## Publish and Run Migrations

```bash
php artisan vendor:publish --tag=contacting-migrations
php artisan migrate
```

If the package discovers migrations automatically, you can skip publishing and just run:

```bash
php artisan migrate
```

## Owner Scoping (Optional)

If you use `aiarmada/commerce-support` with owner scoping, enable it in your `.env`:

```env
CONTACTING_OWNER_ENABLED=true
CONTACTING_OWNER_AUTO_ASSIGN=true
```

See [Configuration](03-configuration.md) for details.
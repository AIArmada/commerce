---
title: Seating Installation
---

## Requirements

- PHP 8.4+
- Laravel 12.x
- `aiarmada/commerce-support`

## Installation

```bash
composer require aiarmada/seating
```

The package registers itself via `SeatingServiceProvider` and publishes its migrations and config automatically.

## Publishing

```bash
# Config
php artisan vendor:publish --tag=seating-config

# Migrations (auto-discovered, but can be published if needed)
php artisan vendor:publish --tag=seating-migrations
```

## Run Migrations

```bash
php artisan migrate
```

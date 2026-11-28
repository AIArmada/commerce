---
title: Installation
---

# Installation

## Requirements

- PHP 8.5+
- Laravel 13.x
- `aiarmada/commerce-support` (same version)

## Database compatibility

The preference owner/channel/category lookup index is non-unique and uses the
complete values on PostgreSQL and SQLite. Thread external identity uniqueness
uses owner and global partial unique indexes on PostgreSQL and SQLite; the
thread migration fails fast on unsupported database drivers instead of
silently dropping per-partition uniqueness.

## Install

```bash
composer require aiarmada/communications
```

## Publish migrations

```bash
php artisan vendor:publish --provider="AIArmada\Communications\CommunicationsServiceProvider" --tag="communications-migrations"
php artisan migrate
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --provider="AIArmada\Communications\CommunicationsServiceProvider" --tag="communications-config"
```

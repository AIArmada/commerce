---
title: Installation
---

# Installation

## Install the package

```bash
composer require aiarmada/links
```

## Publish the config

```bash
php artisan vendor:publish --tag=links-config
```

## Run the migrations

```bash
php artisan migrate
```

This creates the `tracked_links` and `tracked_link_clicks` tables (remappable in config), including the subject morphs, destination parameters, and per-link signature flags.

## Schedule pruning (optional)

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('links:prune-clicks')->daily();
```

## Read next

- [Configuration](03-configuration.md)
- [Usage](04-usage.md)

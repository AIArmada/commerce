---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 13.x
- `aiarmada/commerce-support` (same version)

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

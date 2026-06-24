---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 13+
- `aiarmada/commerce-support` at the same version

## Install

```bash
composer require aiarmada/moderation
```

## Run migrations

The package service provider loads its migrations automatically.

```bash
php artisan migrate
```

## Publish configuration

```bash
php artisan vendor:publish --provider="AIArmada\Moderation\ModerationServiceProvider" --tag="moderation-config"
```

---
title: Persons Installation
---

## Requirements

- PHP 8.4+
- Laravel 11+
- `aiarmada/commerce-support`

## Install

```bash
composer require aiarmada/persons
```

Run the migrations to create the identity tables:

```bash
php artisan migrate
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag="persons-config"
```

## Optional integrations

### Addressing (country relations)

```bash
composer require aiarmada/addressing
PERSONS_ADDRESSING_ENABLED=true
```

Set `PERSONS_MODEL_COUNTRY` to your `AddressCountry` class. Country relations (`nationality`, title/issuer country) are then wired at the application layer on a Person subclass.

## Morph alias registration

In `AppServiceProvider::boot()`:

```php
persons_register_morph_map('person');
```

This stores short stable type strings (e.g. `person`) in the polymorphic assignment columns instead of FQCNs.

## Seed reference data

```bash
php artisan persons:seed-languages
php artisan db:seed --class="AIArmada\Persons\Database\Seeders\TitleCategorySeeder"
php artisan db:seed --class="AIArmada\Persons\Database\Seeders\TitleSeeder"
```

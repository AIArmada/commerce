---
title: Persons Configuration
---

## Configuration file

Publish the config with:

```bash
php artisan vendor:publish --tag="persons-config"
```

## Table names

All table names are configurable and default to unprefixed names. Override per table via env or by editing the published config:

```php
'database' => [
    'table_prefix' => '',
    'tables' => [
        'persons' => env('PERSONS_TABLE_PERSONS', 'persons'),
        'person_names' => env('PERSONS_TABLE_PERSON_NAMES', 'person_names'),
        // ...
    ],
],
```

## JSON column type

`persons.bio` uses the shared `commerce_json_column_type()` helper. Override globally or per-package:

```bash
COMMERCE_JSON_COLUMN_TYPE=json      # all packages
PERSONS_JSON_COLUMN_TYPE=json       # this package only (default: jsonb)
```

## Model overrides

The host application may subclass package models. `Person` is resolved through `AIArmada\Persons\Support\ModelResolver`:

```php
'models' => [
    'person' => env('PERSONS_MODEL_PERSON', \AIArmada\Persons\Models\Person::class),
    'country' => env('PERSONS_MODEL_COUNTRY', \AIArmada\Addressing\Models\AddressCountry::class),
    'institution' => env('PERSONS_MODEL_INSTITUTION'), // no default — application-specific
],
```

Leave `institution` unset when there is no host institution model; `affiliations.institution_id` then stores as a plain nullable UUID.

## Optional integrations

```php
'integrations' => [
    'addressing' => ['enabled' => (bool) env('PERSONS_ADDRESSING_ENABLED', false)],
],
```

- **addressing** — enables nationality/title/issuer country relations. Requires `aiarmada/addressing`.

### Media

`spatie/laravel-medialibrary` is not required by the base package. Install it when you need media collections on your Person subclass.

```bash
composer require spatie/laravel-medialibrary
```

## Wiring events integration

The events package references persons only through its polymorphic `involveable`. Register the alias and the person class from the application's `AppServiceProvider::boot()`:

```php
use AIArmada\Persons\Models\Person;
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::morphMap(['person' => Person::class]);
```

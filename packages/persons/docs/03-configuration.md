---
title: Persons Configuration
---

## Configuration file

Publish the config with:

```bash
php artisan vendor:publish --tag="persons-config"
```

## Table names

All table names are configurable. They default to the value of
`PERSONS_TABLE_PREFIX` (empty by default), and each table can be overridden
individually:

```php
'database' => [
    'table_prefix' => '',
    'tables' => [
        'persons' => env('PERSONS_TABLE_PERSONS', $tablePrefix . 'persons'),
        'person_names' => env('PERSONS_TABLE_PERSON_NAMES', $tablePrefix . 'person_names'),
        // ...
    ],
],
```

The published config uses `PERSONS_TABLE_PREFIX` as the fallback for every table,
while the per-table variables take precedence.

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
    'country' => env('PERSONS_MODEL_COUNTRY', class_exists(\AIArmada\Addressing\Models\AddressCountry::class)
        ? \AIArmada\Addressing\Models\AddressCountry::class
        : null),
    'institution' => env('PERSONS_MODEL_INSTITUTION'),
],
```

Leave `institution` unset when there is no host institution model. An
`institution_id` value is rejected until a valid Eloquent institution model is
configured; null remains valid.

## Optional integrations

```php
'integrations' => [
    'addressing' => [
        'enabled' => (bool) env('PERSONS_ADDRESSING_ENABLED', class_exists(\AIArmada\Addressing\Models\AddressCountry::class)),
    ],
],
```

- **addressing** — enables nationality/title/issuer country relations. It
  defaults on when `AddressCountry` is available and can be disabled with
  `PERSONS_ADDRESSING_ENABLED=false`.

When addressing is disabled, country pointers must remain null and any
configured country relation is fail-closed.

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

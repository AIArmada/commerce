---
title: Persons Troubleshooting
---

## Country relation returns null despite `country_id` being set

The package does not hard-reference `AddressCountry`. Ensure:
1. `aiarmada/addressing` is installed
2. `PERSONS_ADDRESSING_ENABLED=true` is set
3. Your `Person` subclass defines the `country()` / `nationality()` relation (see usage docs)

## Formatted name is empty

Ensure the person's `name` column is not null — it's the fallback display value. Title-active query filters `status = 'active'`; verify the assignment is active.

## Polymorphic morph columns store FQCN instead of short alias

Register the morph map in `AppServiceProvider::boot()`:

```php
persons_register_morph_map('person');
```

Or manually:

```php
use AIArmada\Persons\Models\Person;
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::morphMap(['person' => Person::class]);
```

## Table name collisions with other packages

Each table name is configurable via `persons.database.tables.*` or env vars (`PERSONS_TABLE_PERSONS`, etc.). Set a unique prefix:

```php
// config/persons.php
'table_prefix' => 'myapp_',
```

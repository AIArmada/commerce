---
title: Persons Usage
---

## Creating a person

```php
use AIArmada\Persons\Models\Person;

$person = Person::create([
    'name' => 'Ahmad Rahman',
    'family_name' => 'Rahman',
    'gender' => 'male',
]);
```

## Multi-context names

```php
use AIArmada\Persons\Enums\PersonNameType;
use AIArmada\Persons\Models\PersonName;

$person->names()->create([
    'name_type' => PersonNameType::Display,
    'full_name' => 'Ahmad Rahman',
    'language_code' => 'en',
    'is_primary' => true,
]);

$person->names()->create([
    'name_type' => PersonNameType::Religious,
    'full_name' => 'أحمد بن عبد الرحمن',
    'language_code' => 'ar',
]);

$person->names()->create([
    'name_type' => PersonNameType::Nickname,
    'full_name' => 'Mat',
    'language_code' => 'ms',
]);
```

## Titles

```php
use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Models\Title;

// Find a title definition
$prof = Title::where('name', 'Prof')->first();

// Assign title to a person
$person->titleAssignments()->create([
    'title_id' => $prof->id,
    'status' => AssignmentStatus::Active,
]);

// Formatted name reads ordered titles automatically
echo $person->formatted_name; // "Prof Ahmad Rahman"
```

### Any model can receive titles

Use the `HasTitles` trait on any model:

```php
use AIArmada\Persons\Traits\HasTitles;

class Institution extends Model
{
    use HasTitles;

    // now $institution->titleAssignments works
}
```

## Credentials

```php
use AIArmada\Persons\Models\CredentialDefinition;

$phd = CredentialDefinition::firstOrCreate([
    'name' => 'Doctor of Philosophy',
    'short_form' => 'PhD',
    'credential_type' => 'academic_degree',
]);

$person->credentialAssignments()->create([
    'credential_id' => $phd->id,
    'date_obtained' => '2020-06-15',
]);
```

## Affiliations

```php
use AIArmada\Persons\Enums\AffiliationType;

// institution_id is a loose UUID — resolve to your app's institution model
$affiliation = $person->affiliations()->create([
    'institution_id' => $institutionId,
    'affiliation_type' => AffiliationType::Employee,
    'is_primary' => true,
]);

$affiliation->roles()->create([
    'role_name' => 'Senior Lecturer',
    'department' => 'Faculty of Engineering',
    'is_current' => true,
]);
```

## Wiring country relations

The package stores `country_id` / `nationality_country_id` as loose UUIDs. Add relations on your app's Person subclass:

When `persons.models.country` is configured, the `Title` model exposes the same
optional country relation and the Filament title resource displays it as a
Country column.

```php
use AIArmada\Addressing\Models\AddressCountry;

public function nationality(): BelongsTo
{
    return $this->belongsTo(AddressCountry::class, 'nationality_country_id');
}
```

## Wiring language relations

`language_code` columns store ISO 639-1 codes (`en`, `ms`, `ar`). A `languages` lookup table with bundled ISO 639-1 data is provided by `aiarmada/commerce-support`.

Seed it once after migration:

```bash
php artisan commerce:seed-languages
```

Or from a seeder:

```php
$this->call(\AIArmada\CommerceSupport\Database\Seeders\LanguageSeeder::class);
```

To wire a `BelongsTo`, extend the package model and reference your own Language model:

```php
public function language(): BelongsTo
{
    return $this->belongsTo(Language::class, 'language_code', 'code');
}
```

## Wiring events integration

In your `AppServiceProvider::boot()`:

```php
persons_register_morph_map('person');
```

Then in your `Event` model, the existing `persons()` relation works through the events package's `involveable` morph — no changes needed on the events side. The morph map entry ensures `event_involvements.involveable_type` stores `'person'`.

## Formatted name output

```php
$person->formatted_name; // "Datuk Dr. Ahmad Rahman, PhD"
```

Composition logic:
1. Active `before_name` titles grouped by `title.category.sort_order`, then sorted by `title.sort_order` → space-separated prefix
2. Person's `name` field
3. Active `after_name` titles grouped by `title.category.sort_order`, then sorted by `title.sort_order` → comma-separated suffix

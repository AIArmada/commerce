---
title: Persons Usage
---

## Creating a person

```php
use AIArmada\Persons\Models\Person;
use AIArmada\Persons\Enums\PersonStatus;

$person = Person::create([
    'name' => 'Ahmad Rahman',
    'family_name' => 'Rahman',
    'gender' => 'male',
    'status' => PersonStatus::Active,
]);

// `slug` and `searchable_name` are generated on save when omitted.
echo $person->slug;
echo $person->searchable_name;
```

Use transitionStatus() for lifecycle changes so published_at is set only for
PersonStatus::Published and cleared for other statuses.

The model also enforces this mapping when a status is saved directly, so
Filament and direct Eloquent saves cannot leave a published person without a
published_at timestamp.

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

For reusable orchestration, use the additive core assignment actions:

```php
use AIArmada\Persons\Actions\AssignCredentialAction;
use AIArmada\Persons\Actions\AssignTitleAction;

$titleAssignment = app(AssignTitleAction::class)->execute($person, $prof->id);
$credentialAssignment = app(AssignCredentialAction::class)->execute($person, $phd->id);
```

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

For lists, eager-load `titleAssignments.title.category` (the Filament person
resource does this). The formatted-name accessor is pure and never replaces
the model's full assignment relation with its filtered display collection.

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

// institution_id is validated against the configured institution model
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

### Primary identity records

`PersonName::is_primary` is exclusive within `(person_id, name_type,
language_code)`. `Affiliation::is_primary` is exclusive within its person.
Saving a primary record locks the parent and clears the matching siblings,
including writes made through Filament relation managers. The database partial
unique backstop is scheduled for the next permitted index migration.

## Linking a customer profile

The customers package owns the tenant-scoped commercial profile. Link it to an
existing shared person explicitly; do not create a person as an implicit side
effect of checkout or import flows:

```php
use AIArmada\Customers\Actions\LinkCustomerToPerson;

$customer = app(LinkCustomerToPerson::class)->execute($customer, $person);
```

The action validates the customer in the current owner context. `person_id`
is a nullable, indexed UUID link without a database foreign-key constraint.

## Wiring country relations

The package stores `country_id` / `nationality_country_id` as loose UUIDs
and validates them through the configured model resolver. Configure the
country model and enable the addressing integration before writing country
pointers:

When `persons.models.country` is configured, the `Title` model exposes the same
optional country relation and the Filament title resource displays it as a
Country column.

When `persons.models.institution` is configured, `Affiliation`, `TitleIssuer`, and
`CredentialAssignment` expose institution relations. Supplying a non-null institution
ID without that configuration is rejected.

The core models expose the resolved country relations directly:

```php
$person->nationalityCountry;
$title->country;
$titleIssuer->country;
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

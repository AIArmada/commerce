---
title: Filament Addressing Address Forms
---

## What The Schema Gives You

`AddressFormSchema::make()` returns a complete address field set driven by the
country address profiles in `aiarmada/addressing`:

- `country_code` select (live; changing it resets state and area picks)
- `label`, `line1`, `line2`, `line3`, free-text `city`, `postcode`
- Optional canonical `city_id` lookup, narrowed to the selected country and state; free-text city remains independent
- Latitude/longitude, Google place/feature/CID/entity identifiers, and optional Google Maps/Waze URLs
- `state_id` select with a per-country label, visible only when the country has states; it lists only the provider state-kind roots (4 nations for GB, 10 counties for LT) so every option resolves to an area parent, falling back to all states for provider-less countries
- One `area_assignments.{role}` select per hierarchy role the selected country defines

Role selects only appear for the selected country, only offer areas under the
selected parent (state or parent role), and clear their children automatically
when a parent changes. Selections are validated twice: by form rules at input
time and by `AddressAssignmentWriter` at save time.

The canonical city selector searches on demand and only returns cities in the
selected country and state. Its submitted ID is revalidated server-side;
changing country or state clears the selection. It does not replace free-text
`city`, which remains useful for unseeded locality names. Coordinates are
bounded to valid latitude/longitude ranges.

Region-parented levels narrow to a selected intermediate level when the stored
links prove the narrowing is structural: Malaysia's subdivisions list only the
selected district's rows once a district is picked, and fall back to
state-wide options without one (so district-less states like KL keep working).
Changing an earlier level clears the narrowed selections below it.

## Embed The Schema

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use AIArmada\FilamentAddressing\Schemas\AddressFormSchema;
use Filament\Schemas\Schema;

final class VenueResource
{
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ...AddressFormSchema::make(),
        ]);
    }
}
```

For prefixed fields (for example a shipping address next to a billing address):

```php
...AddressFormSchema::make(prefix: 'shipping_')
```

On an edit form, pass the record so area selects pre-fill from its assignments:

```php
...AddressFormSchema::make(record: $record)
```

## Save The Address And Assignments Atomically

Area selects are `dehydrated(false)`: they never reach `$form->getState()`.
Read them from raw state with `extractAreaAssignments()` and pass them to the
canonical `AddressWriter`, which saves both in one transaction:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueResource\Pages;

use AIArmada\Addressing\Contracts\AddressWriter;
use AIArmada\Addressing\Support\ModelResolver;
use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\FilamentAddressing\Schemas\AddressFormSchema;
use Filament\Resources\Pages\CreateRecord;

final class CreateVenueAddress extends CreateRecord
{
    protected function handleRecordCreation(array $data): Address
    {
        $rawState = $this->form->getRawState();
        $state = is_array($rawState) ? $rawState : $rawState->toArray();

        $id = app(AddressWriter::class)->execute(
            $data,
            AddressFormSchema::extractAreaAssignments($state),
        );

        return ModelResolver::addressClass()::query()->findOrFail($id);
    }
}
```

For prefixed schemas, pass the matching `prefix:` to `extractAreaAssignments()`
and map the dehydrated address fields to their canonical names. On edit pages,
pass the persisted address ID as `recordId:` to the same writer.

> **warning**
> Calling `getState()` instead of `getRawState()` silently drops every area
> selection. The sync then deletes all of the address assignments.

## Validation

Three layers protect the write path:

1. `StateBelongsToCountry` and `AddressAreasBelongToCountry` rules on the form
   reject areas outside the selected country before submit.
2. `AddressAssignmentWriter` re-checks country, active flag, role
   definition, type/level match, and parent linkage inside the transaction, so
   stale or tampered IDs throw a `ValidationException` instead of persisting.
3. `AddressOwnerGuard` inside the sync aborts writes to addresses outside the
   current owner scope.

Reuse the same rules when building a custom (non-schema) form:

```php
use AIArmada\FilamentAddressing\Rules\AddressAreasBelongToCountry;
use AIArmada\FilamentAddressing\Rules\StateBelongsToCountry;

Select::make('state_id')->rules([new StateBelongsToCountry($countryCode)]);
Select::make('district_id')->rules([new AddressAreasBelongToCountry($countryCode)]);
```

## Owner Scoping

Reference data (countries, states, areas) is global; addresses are
owner-scoped. Keep custom address forms behind the same guards the stock
resource uses: an owner-scoped `getEloquentQuery()`, `OwnerUiScope` on
pickers and row actions, and no reliance on ambient web auth inside queued
follow-up work. See [Usage](./04-usage.md) for the resource wiring.

## Seeding Prerequisite

Selects only offer data that exists. A country with no seeded states hides the
state select; a role with no seeded areas offers no options. Seed countries,
states, and areas before exposing the form — see the addressing
[Usage](../../addressing/docs/04-usage.md) and
[Installation](../../addressing/docs/02-installation.md) guides.

## Limitations

- `postcode` remains free text and optional, but known country profiles apply
  the core `ValidPostalCode` format rule to non-empty values. Countries without
  a profile remain unconstrained, and postcode-to-area autofill is not wired
  into the schema.
- Free-text `city` and canonical `city_id` are independent. The canonical
  selector is optional and requires a matching seeded City row.

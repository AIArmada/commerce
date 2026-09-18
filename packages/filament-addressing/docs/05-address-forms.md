---
title: Filament Addressing Address Forms
---

## What The Schema Gives You

`AddressFormSchema::make()` returns a complete address field set driven by the
country address profiles in `aiarmada/addressing`:

- `country_code` select (live; changing it resets state and area picks)
- `label`, `line1`, `line2`, free-text `city`, `postcode`
- `state_id` select with a per-country label, visible only when the country has states
- One `area_assignments.{role}` select per hierarchy role the selected country defines

Role selects only appear for the selected country, only offer areas under the
selected parent (state or parent role), and clear their children automatically
when a parent changes. Selections are validated twice: by form rules at input
time and by `SyncAddressAreaAssignmentsAction` at save time.

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

## Save Assignments After The Record

Area selects are `dehydrated(false)`: they never reach `$form->getState()`.
Read them from raw state with `extractAreaAssignments()` and sync them in the
same transaction as the address save:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueResource\Pages;

use AIArmada\Addressing\Actions\SyncAddressAreaAssignmentsAction;
use AIArmada\Addressing\Models\Address;
use AIArmada\FilamentAddressing\Schemas\AddressFormSchema;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

final class CreateVenueAddress extends CreateRecord
{
    protected function handleRecordCreation(array $data): Address
    {
        return DB::transaction(function () use ($data): Address {
            $address = new Address;
            $address->fill($data);
            $address->save();

            $rawState = $this->form->getRawState();
            $state = is_array($rawState) ? $rawState : $rawState->toArray();

            app(SyncAddressAreaAssignmentsAction::class)->execute(
                $address,
                AddressFormSchema::extractAreaAssignments($state, prefix: 'shipping_'),
                $address->state_id,
                ['source' => 'venue-form'],
            );

            return $address;
        });
    }
}
```

Omit `prefix:` when the schema was built without one. The same pattern applies
on edit pages: update the record, then execute the sync with the fresh
`state_id`.

:::warning
Calling `getState()` instead of `getRawState()` silently drops every area
selection. The sync then deletes all of the address assignments.
:::

## Validation

Three layers protect the write path:

1. `StateBelongsToCountry` and `AddressAreasBelongToCountry` rules on the form
   reject areas outside the selected country before submit.
2. `SyncAddressAreaAssignmentsAction` re-checks country, active flag, role
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

- `postcode` is free text. No per-country format validation or
  postcode-to-area autofill is wired into the schema today.
- `city` stays free text even when a country also defines city areas; the two
  fields are independent.

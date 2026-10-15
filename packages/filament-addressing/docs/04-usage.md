---
title: Filament Addressing Usage
---

## Register The Plugin

```php
<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use AIArmada\FilamentAddressing\FilamentAddressingPlugin;
use Filament\Panel;
use Filament\PanelProvider;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                FilamentAddressingPlugin::make(),
            ]);
    }
}
```

## Manage Countries

Countries are seeded by `aiarmada/addressing`.

The country resource is read-only by default and is intended for browsing/searching ISO 3166-1 country/territory data.

To enable safe editing, set `resources.countries.read_only=false` and `features.country_editing=true`.
The edit form keeps identity fields locked and only exposes safe display metadata.

Search examples:

- `MY`
- `Malaysia`
- `MYS`
- `+60`

Open any country to see its related states in the States section below the country details.

Address and snapshot resources use the current owner context. Reference-data
resources such as countries, states, cities, areas, and postcodes remain
global. Global address work must enter explicit global context in the owning
application.

Resource enablement and read-only checks stay in this adapter because they
describe Filament UI policy. Core model-class resolution belongs to
`addressing\Support\ModelResolver`; the adapter does not duplicate that
domain resolver.

## Manage Areas

Areas are user/importer-owned reference data.

Use the Area resource for:

- states
- federal territories
- provinces
- prefectures
- districts
- cities
- mukim
- villages
- neighbourhoods

Do not assume every country has a state. Use `type` and `level` to represent the local hierarchy.

The Areas list links to Create when the resource is writable and the current
user is authorized. Area detail pages link to Edit under the same policy. Set
`resources.areas.read_only=false` to enable manual area changes. Provider-owned
area identity, hierarchy, and coordinates remain protected in the ordinary
edit form; only active status can be changed, and a later provider seed may
restore provider-managed values. Inactive areas remain in existing references
and are marked in the detail view. When a country profile defines one hierarchy,
the form selects it automatically; when it defines parallel hierarchies, choose
the labeled hierarchy explicitly for a parent relationship. Countries without
a profile accept a custom hierarchy type.

Area details expose aliases, roles, incoming and outgoing typed area
relationships, canonical state mappings, child areas, and postcode coverage.
Provider roles, relationships, state mappings, and coverage are inspection
surfaces; update them through their core provider/import workflows. Manual
aliases can be added, edited, or deleted from the detail page when area writes
are enabled. Provider-owned aliases are read-only. The Source Payload section
shows the stored payload and metadata when `features.show_source_payload` is
enabled.

## Protected geography identities

Reference identity is now maintained through core import and seed workflows.
This is a breaking behavior change for operators who previously edited
identity fields in the panel. Filament forms reject edits to provider area
source/source ID, state code/country, city name/country/state, and postcode
country/code. To correct imported geography identity, update the authoritative
provider/source data and rerun its core seed/import workflow (`SeedAddressStatesAction`,
`SeedAddressCitiesAction`, `ImportAddressAreasAction`, or `ImportPostalCodesAction`
as appropriate), then review references to any newly keyed rows. In the panel,
state names are editable only when the state has a stable code; code-less state
names are protected because the seed workflow identifies them by country and
name. State coordinates and city coordinates remain editable. Provider-managed
area hierarchy and coordinates stay protected from incidental form submissions.

Postcode country/code is the row identity. For a genuinely corrected postcode,
import the corrected country/code through core postcode import, reattach the
manual coverage to that row, and deactivate the old row after verifying no
workflow still references it. Postcode active status and metadata remain
editable, and manual coverage is managed separately from provider-owned links.
The admin form edits metadata as JSON and accepts an object, array, or `null`;
use `null` to clear the stored metadata.

## Import Areas

Use the built-in Filament import surface if enabled.

Required CSV columns:

```csv
country_code,type,name,source,source_id
MY,state,Selangor,app.malaysia,MY-10
```

Recommended columns (JSON values are encoded as JSON text):

```csv
country_code,type,level,name,native_name,code,parent_source_id,relationship_type,hierarchy_type,source,source_id,latitude,longitude,metadata,source_payload
MY,state,1,Selangor,Selangor,10,,contains,administrative,app.malaysia,MY-10,3.0738,101.5183,"{""source"":""import""}","{""dataset"":""official""}"
MY,district,2,Petaling,Petaling,PETALING,MY-10,contains,administrative,app.malaysia,MY-10-PETALING,,,
MY,locality,2,Bangsar,Bangsar,BANGSAR,MY-10,contains,postal,app.malaysia,MY-10-BANGSAR,,,
```

The importer calls the core `ImportAddressAreasAction`; it does not insert rows
directly. It checks the areas read-only setting and create/update policy again
for each imported row. `relationship_type` and `hierarchy_type` describe the
row's source-owned parent edge. Export includes the scalar area fields and the
matching parent edge for that row. It is not a full graph backup: aliases,
roles, independent cross-hierarchy links, state mappings, and postcode links
are not included in this row export. Provider-owned values continue to be
managed through the core provider/import workflows.

The `parent_source_id` column is country-scoped and cycle-safe. The UI hides parent choices that would create a loop, and the importer rejects the same loop server-side.

For Malaysia, the area resource shows the shared State / Federal Territory root and the two provider branches: postal/address localities and administrative/land districts and subdivisions. Use the Roles filter to distinguish `postal_locality`, `administrative_district`, and `administrative_subdivision` records. Kuala Lumpur, Putrajaya, and Labuan do not need separate postal-town records.

Alternate names are managed as plain aliases without locale metadata.

Address-form area selectors are defined by the selected country's profile, active area type/level, and typed containment. Roles are optional search metadata, not a prerequisite for an area to be selectable. Changing country clears every selected area assignment before save.

## Manage Postcodes

The Postcodes resource searches postcode records and shows their served-area
coverage. A postcode may serve multiple localities or administrative areas;
coverage remains separate from the geographic hierarchy and does not require
a postal-town entity. Its identity is country plus code. Edits can change
active status, metadata, and manual coverage only. Provider-owned coverage and
manual links carrying source IDs are preserved.

Postcode import/export is enabled with `features.postal_code_import` and
`features.postal_code_export`. The grouped JSON `coverage` column round-trips
multiple source groups, link source/source ID, area source/source ID,
relationship type, primary status, postcode active status, and nullable
metadata. A sample row is:

```csv
country_code,code,is_active,metadata,coverage
MY,47500,1,"{""verified"":true}","[{""source"":""my.provider"",""source_id"":""MY-47500:petaling"",""area_source"":""app.malaysia"",""area_source_id"":""MY-10-PETALING"",""relationship_type"":""served_by"",""is_primary"":true}]"
```

Import validates every referenced area in the postcode's country and rechecks
read-only plus create/update policy per row. Declared `(source, source_id)`
groups are replaced together; other groups remain. `coverage: []` means
"leave coverage unchanged," so it cannot clear unknown groups. For a single
source-backed coverage edge, the legacy flat `source`, `source_id`,
`area_source`, `area_source_id`, `relationship_type`, and `is_primary` columns
remain supported. Flat imports use the existing core postal-code import
contract and do not promise active-state round-trip.

The postcode's root `metadata` field is separate from each coverage edge's
source and relationship provenance.

In grouped JSON mode, omitted `metadata` and `is_active` columns preserve the
existing row values; an explicit JSON `null` clears metadata.

## Reuse Address Form Schema

Downstream Filament resources can reuse the address form schema.

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

For prefixed fields:

```php
...AddressFormSchema::make(prefix: 'shipping_')
```

For the full write path — saving assignments after the record, validation
layers, owner scoping, and limitations — see
[Address Forms](./05-address-forms.md).

## Use Address Relation Manager

If a model uses the core `HasAddresses` trait, add the relation manager manually.

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueResource;

use AIArmada\FilamentAddressing\RelationManagers\AddressesRelationManager;

final class VenueResource
{
    public static function getRelations(): array
    {
        return [
            AddressesRelationManager::class,
        ];
    }
}
```

The relation manager is opt-in. It is never auto-attached to other resources.

When owner mode is enabled, the attach record picker only offers addresses
owned by the current owner. Authorized users can edit an attachment's type,
label, and primary flag or detach that exact row; handlers revalidate both the
address and parent owner before changing the pivot. Duplicate address/type
attachments are rejected.

## Enable Central Address Resource

Only enable this for trusted admin panels.

```php
'resources' => [
    'addresses' => [
        'enabled' => true,
        'read_only' => false,
        'model' => \AIArmada\Addressing\Models\Address::class,
    ],
],
```

If `features.address_export` is enabled, the central Address resource also shows a built-in export action.

> **warning**
> If addresses are owner-scoped, verify `getEloquentQuery()` and action handlers are owner-safe before enabling this resource.

## Snapshot Resource

Snapshots are historical records.

Keep them read-only.

Use them to inspect:

- order billing/shipping snapshots
- shipment origin/destination snapshots
- event location snapshots

Snapshots remain immutable and read-only. Address detail shows the live
canonical location references and area assignments. Snapshot detail shows the
copied historical address strings, metadata, and saved navigation links; it
does not resolve current canonical IDs or assignments. Navigation links are
generated by core `BuildAddressNavigationLinksAction` when the address is
saved.
Central Address and Snapshot resources are disabled by default and should only
be enabled for a panel with the appropriate owner-safe access.

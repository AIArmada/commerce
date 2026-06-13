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

## Import Areas

Use the built-in Filament import surface if enabled.

Required CSV columns:

```csv
country_code,type,name,source,source_id
MY,state,Selangor,app.malaysia,MY-10
```

Recommended columns:

```csv
country_code,type,level,name,native_name,code,parent_source_id,source,source_id,latitude,longitude,metadata
MY,state,1,Selangor,Selangor,10,,app.malaysia,MY-10,3.0738,101.5183,"{""source"":""legacy""}"
MY,district,2,Petaling,Petaling,PETALING,MY-10,app.malaysia,MY-10-PETALING,,
MY,city,3,Shah Alam,Shah Alam,SHAH-ALAM,MY-10-PETALING,app.malaysia,MY-10-PETALING-SHAH-ALAM,,
```

The importer must call the core `ImportAddressAreasAction`. It must not insert rows directly.

The `parent_source_id` column is country-scoped and cycle-safe. The UI hides parent choices that would create a loop, and the importer rejects the same loop server-side.

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

## Enable Central Address Resource

Only enable this for trusted admin panels.

```php
'addresses' => [
    'enabled' => true,
    'read_only' => false,
    'model' => \AIArmada\Addressing\Models\Address::class,
],
```

If `features.address_export` is enabled, the central Address resource also shows a built-in export action.

:::warning
If addresses are owner-scoped, verify `getEloquentQuery()` and action handlers are owner-safe before enabling this resource.
:::

## Snapshot Resource

Snapshots are historical records.

Keep them read-only.

Use them to inspect:

- order billing/shipping snapshots
- shipment origin/destination snapshots
- event location snapshots

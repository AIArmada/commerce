# Filament Addressing Package Implementation Instruction

This document is the complete implementation brief for creating `aiarmada/filament-addressing`.

The package is a Filament v5 adapter for `aiarmada/addressing`. It must provide admin UI resources, reusable Filament schema/table components, optional relation managers, and safe import/export surfaces for address reference data. It must not own the address domain, migrations, data model, country data, area import contract, or address business logic.

## 0. Non-Negotiable Rules

Follow `AGENTS.md` first. When this instruction conflicts with `AGENTS.md`, `AGENTS.md` wins.

Hard requirements:

- Target PHP 8.4+.
- Use Filament v5 APIs.
- This package is an adapter, not a domain owner.
- Do not create address-domain tables in this package.
- Do not duplicate models from `aiarmada/addressing`.
- Do not add database migrations unless a future task explicitly asks for UI-only package state. Version 1 must have no migrations.
- Do not add DB foreign-key constraints or cascades anywhere.
- Do not use `SoftDeletes`.
- Do not add repo-wide formatting or style-only changes.
- Do not run Pint/Pest/PHPStan repo-wide.
- Use package-scoped verification only.
- Use built-in Filament Import/Export actions only.
- Filament tenancy is not a security boundary. Every resource query and action must be owner-safe when displaying owner-scoped address records.
- Country and area reference data are global reference data by default.
- `Address`, `AddressSnapshot`, and polymorphic addressable records may be owner-scoped by the core package; central UI for those records must be disabled by default and guarded.

## 1. Package Name and Boundary

Package path:

```txt
packages/filament-addressing
```

Composer package:

```txt
aiarmada/filament-addressing
```

Namespace:

```php
AIArmada\FilamentAddressing
```

Depends on:

```txt
aiarmada/addressing
filament/filament
```

Do not hardcode unrelated packages. If optional integrations are needed, use `class_exists()` checks and config gates.

### This Package Owns

- Filament plugin registration.
- Filament resources for address reference/admin data.
- Reusable Filament form schemas for AddressData/Address models.
- Reusable table columns for addresses/countries/areas.
- Optional relation managers for embedding address management in other Filament resources.
- Filament import/export classes that call core `addressing` actions or safely map to core models.
- Documentation for using the UI adapter.

### This Package Does Not Own

- `Address`, `AddressCountry`, `AddressArea`, `AddressSnapshot` models.
- Address migrations.
- Country dataset.
- Address area import contract.
- Address formatting/normalization/geocoding logic.
- Tenant ownership primitives.
- MajlisIlmu venue/event logic.
- Commerce customer/order logic.

When behavior needs domain logic, call a core `aiarmada/addressing` Action/Service. Do not reimplement it in Filament classes.

## 2. Required File Tree

Create this structure exactly unless sibling package conventions require minor naming alignment:

```txt
packages/filament-addressing/
├── CONTEXT.md
├── composer.json
├── config/
│   └── filament-addressing.php
├── docs/
│   ├── 01-overview.md
│   ├── 02-installation.md
│   ├── 03-configuration.md
│   ├── 04-usage.md
│   └── 99-troubleshooting.md
├── src/
│   ├── FilamentAddressingServiceProvider.php
│   ├── FilamentAddressingPlugin.php
│   ├── Resources/
│   │   ├── AddressCountryResource.php
│   │   ├── AddressCountryResource/
│   │   │   ├── Pages/
│   │   │   │   ├── ListAddressCountries.php
│   │   │   │   ├── ViewAddressCountry.php
│   │   │   │   └── EditAddressCountry.php
│   │   ├── AddressAreaResource.php
│   │   ├── AddressAreaResource/
│   │   │   ├── Pages/
│   │   │   │   ├── ListAddressAreas.php
│   │   │   │   ├── CreateAddressArea.php
│   │   │   │   ├── ViewAddressArea.php
│   │   │   │   └── EditAddressArea.php
│   │   ├── AddressResource.php
│   │   ├── AddressResource/
│   │   │   ├── Pages/
│   │   │   │   ├── ListAddresses.php
│   │   │   │   ├── ViewAddress.php
│   │   │   │   └── EditAddress.php
│   │   ├── AddressSnapshotResource.php
│   │   └── AddressSnapshotResource/
│   │       ├── Pages/
│   │       │   ├── ListAddressSnapshots.php
│   │       │   └── ViewAddressSnapshot.php
│   ├── Schemas/
│   │   ├── AddressFormSchema.php
│   │   ├── AddressAreaFormSchema.php
│   │   ├── AddressCountryFormSchema.php
│   │   └── AddressInfolistSchema.php
│   ├── Tables/
│   │   ├── AddressTable.php
│   │   ├── AddressAreaTable.php
│   │   ├── AddressCountryTable.php
│   │   └── AddressSnapshotTable.php
│   ├── RelationManagers/
│   │   └── AddressesRelationManager.php
│   ├── Imports/
│   │   └── AddressAreaImporter.php
│   ├── Exports/
│   │   ├── AddressCountryExporter.php
│   │   ├── AddressAreaExporter.php
│   │   └── AddressExporter.php
│   └── Support/
│       ├── ResolvesAddressingResources.php
│       └── GuardsAddressingUi.php
└── tests/
    ├── Feature/
    │   ├── AddressCountryResourceTest.php
    │   ├── AddressAreaResourceTest.php
    │   ├── AddressResourceDisabledByDefaultTest.php
    │   ├── AddressSnapshotReadOnlyResourceTest.php
    │   └── FilamentAddressingPluginTest.php
    └── TestCase.php
```

If installed package conventions use `app/Filament`-style stubs, still keep package source under `src/`.

## 3. Composer Configuration

Create `packages/filament-addressing/composer.json`:

```json
{
  "name": "aiarmada/filament-addressing",
  "description": "Filament v5 adapter for aiarmada/addressing.",
  "type": "library",
  "license": "proprietary",
  "autoload": {
    "psr-4": {
      "AIArmada\\FilamentAddressing\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "AIArmada\\FilamentAddressing\\Tests\\": "tests/"
    }
  },
  "require": {
    "php": "^8.4",
    "aiarmada/addressing": "*",
    "filament/filament": "^5.0"
  },
  "extra": {
    "laravel": {
      "providers": [
        "AIArmada\\FilamentAddressing\\FilamentAddressingServiceProvider"
      ]
    }
  }
}
```

If the monorepo uses path repositories or different version constraints, match sibling package conventions.

## 4. Config File

Create `config/filament-addressing.php`.

Order sections exactly as Filament package config rules require: Navigation -> Tables -> Features -> Resources.

```php
<?php

declare(strict_types=1);

return [
    'navigation' => [
        'enabled' => true,
        'group' => 'Addressing',
        'sort' => 80,
        'icons' => [
            'countries' => 'heroicon-o-globe-alt',
            'areas' => 'heroicon-o-map',
            'addresses' => 'heroicon-o-map-pin',
            'snapshots' => 'heroicon-o-document-text',
        ],
    ],

    'tables' => [
        'default_pagination' => 25,
        'search_debounce' => '500ms',
    ],

    'features' => [
        'country_editing' => false,
        'area_import' => true,
        'area_export' => true,
        'address_export' => false,
        'show_provider_payload' => false,
        'show_source_payload' => false,
    ],

    'resources' => [
        'countries' => [
            'enabled' => true,
            'read_only' => true,
            'model' => \AIArmada\Addressing\Models\AddressCountry::class,
        ],

        'areas' => [
            'enabled' => true,
            'read_only' => false,
            'model' => \AIArmada\Addressing\Models\AddressArea::class,
        ],

        'addresses' => [
            'enabled' => false,
            'read_only' => false,
            'model' => \AIArmada\Addressing\Models\Address::class,
        ],

        'snapshots' => [
            'enabled' => false,
            'read_only' => true,
            'model' => \AIArmada\Addressing\Models\AddressSnapshot::class,
        ],
    ],
];
```

Rules:

- Do not add unused keys.
- Do not use env vars unless the value is secret or deployment-specific. These are not secrets.
- Keep country editing off by default because countries are package-seeded reference data.
- Keep central addresses/snapshots resources disabled by default because owner and historical semantics are risky.
- If a config key is added, implement at least one read and a test for it.

## 5. Service Provider

Create `FilamentAddressingServiceProvider`.

Responsibilities:

- Merge config.
- Publish config with tag `filament-addressing-config`.
- Register no migrations.
- Register no routes unless required by Filament conventions.
- Register plugin support classes.
- Do not enumerate downstream packages.
- Do not perform runtime state mutation in static properties.

Example shape:

```php
<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing;

use Illuminate\Support\ServiceProvider;

final class FilamentAddressingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/filament-addressing.php',
            'filament-addressing',
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/filament-addressing.php' => config_path('filament-addressing.php'),
        ], 'filament-addressing-config');
    }
}
```

## 6. Filament Plugin

Create `FilamentAddressingPlugin`.

Goals:

- Provide `FilamentAddressingPlugin::make()`.
- Register only enabled resources.
- Allow users to register it in their Panel provider.
- Do not register disabled resources.
- Do not override application-level navigation without config.

Example:

```php
<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing;

use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use AIArmada\FilamentAddressing\Resources\AddressCountryResource;
use AIArmada\FilamentAddressing\Resources\AddressResource;
use AIArmada\FilamentAddressing\Resources\AddressSnapshotResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentAddressingPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-addressing';
    }

    public function register(Panel $panel): void
    {
        $resources = [];

        if (config('filament-addressing.resources.countries.enabled')) {
            $resources[] = AddressCountryResource::class;
        }

        if (config('filament-addressing.resources.areas.enabled')) {
            $resources[] = AddressAreaResource::class;
        }

        if (config('filament-addressing.resources.addresses.enabled')) {
            $resources[] = AddressResource::class;
        }

        if (config('filament-addressing.resources.snapshots.enabled')) {
            $resources[] = AddressSnapshotResource::class;
        }

        $panel->resources($resources);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): self
    {
        return new self();
    }
}
```

Before shipping, verify the exact Filament v5 plugin method signatures in the installed package.

## 7. Resource Policy

### AddressCountryResource

Purpose:

- Browse ISO 3166-1 country/territory records from core `addressing`.
- Search by ISO2, ISO3, numeric code, name, official name, native name, phone code, currency code.
- View metadata.
- Editing disabled by default.
- If `country_editing=true`, allow editing only safe display fields/metadata, never UUID or ISO2.

Required pages:

- List
- View
- Edit only if config allows; route may exist but action must abort/disable when editing is off.

Default table columns:

- ISO2
- ISO3
- Name
- Official name
- Entity type
- Is independent
- Currency code
- Phone code
- Default locale
- Updated at

Filters:

- Entity type
- Is independent
- Region
- Currency code
- Timezone maybe optional if model supports it

Bulk actions:

- Export only.
- No delete.
- No bulk delete.

Header actions:

- Export.
- Optional `Seed Countries` action only if core exposes an idempotent `SeedAddressCountriesAction` or command-safe Action. Prefer keeping country seeding in the core package command/docs.

### AddressAreaResource

Purpose:

- Manage user/importer-provided administrative/locality data.
- Support states, federal territories, provinces, prefectures, districts, cities, mukim, villages, neighbourhoods, etc.
- Use global reference data unless core package config later supports owner-scoped areas.

Required pages:

- List
- Create
- View
- Edit

Default table columns:

- Country code / country name
- Type
- Level
- Name
- Native name
- Code
- Parent
- Source
- Source ID
- Synced at
- Updated at

Filters:

- Country
- Type
- Level
- Source
- Has parent

Form fields:

- Country select from `AddressCountry`
- Parent select filtered by selected country
- Type select/free text hybrid
- Level numeric
- Name
- Native name
- Code
- Slug if model exposes it
- Latitude/longitude
- Source/source_id/source_payload readonly if imported
- Metadata key-value/json editor if available and safe

Rules:

- Parent options must be country-scoped.
- Do not allow selecting the current record as its own parent.
- Do not allow obvious parent cycles if core action provides validation. If no core validation exists, add UI guard plus server action validation.
- Create/update must call core actions if available. If no action exists, use model save with validation matching core docs.
- No DB constraints or cascades.

Import:

- Use Filament built-in ImportAction only.
- Importer maps CSV columns into core `AddressAreaSource`/`AddressAreaData` pipeline if available.
- Never directly insert rows while bypassing `ImportAddressAreasAction`.
- Support dry-run only if Filament import supports preview cleanly or via a separate action calling core dry-run.

Export:

- Use Filament built-in ExportAction only.
- Export fields: country_code, type, level, name, native_name, code, parent_source_id, source, source_id, latitude, longitude, synced_at.

### AddressResource

Default:

- Disabled.

Purpose if enabled:

- Central admin/search surface for actual address records.
- Useful for debugging and global admin only.
- Should not become the main place where customer/venue/order addresses are edited. Owning packages should manage their own address forms/relation managers.

Pages:

- List
- View
- Edit only if resource config is not read-only

Security:

- If core `Address` uses `HasOwner`, query must be owner-scoped.
- If no owner context and owner mode is enabled, fail closed or show no records unless explicit global context is allowed by core.
- Do not expose cross-tenant addresses.
- Validate any inbound addressable/area IDs through core ownership/write guard where available.

Default table columns:

- Label/name
- Line1
- City
- State
- Postcode
- Country code
- Latitude/longitude indicator
- Validation status
- Provider
- Updated at

Form:

- Reuse `AddressFormSchema`.
- Include country select and free-text address fields.
- Area ID fields optional and dependent on country.
- Provider payload hidden unless config allows it.

Actions:

- No delete by default unless core behavior defines safe delete.
- Export disabled by default.

### AddressSnapshotResource

Default:

- Disabled and read-only.

Purpose:

- Historical inspection only.
- Never edit snapshots through generic UI.
- Never delete snapshots through generic UI.

Pages:

- List
- View

Table columns:

- Snapshotable type/id
- Reason
- Formatted address
- Country
- State
- City
- Postcode
- Created at

Actions:

- View only.
- Export may be disabled by default.
- No edit, no create, no delete.

## 8. Reusable Schema Components

Create reusable components so downstream packages can embed address UI without copying fields.

### AddressFormSchema

Provides field groups for creating/editing addresses.

Method proposal:

```php
public static function make(string $prefix = ''): array;
```

Support prefixes:

- no prefix for `Address` model fields
- `billing_`
- `shipping_`
- `origin_`
- `destination_`

Fields:

- `line1`
- `line2`
- `city`
- `district`
- `state`
- `postcode`
- `country_code`
- optional area selectors
- optional latitude/longitude
- optional formatted address

Country field:

- Select using `AddressCountry` model
- Searchable
- Preload only if country count is small enough; 249 records is okay
- Save ISO2 country code, not full display name
- Display as `MY — Malaysia`

Admin area fields:

- Optional.
- Do not force state/city for all countries.
- Labels should be generic by default:
  - `State / Region`
  - `District`
  - `City / Locality`
- If core country formatting metadata provides labels, use it.

### AddressInfolistSchema

Display:

- Formatted address
- Country
- Administrative areas
- Coordinates
- Validation status
- Provider/source data if enabled

### AddressTable

Reusable columns for models that expose address-like fields or relation.

Must not assume every owning model has the same columns. Allow callbacks or prefix.

## 9. Reusable Relation Manager

Create `AddressesRelationManager`.

Purpose:

- Owning packages can attach it to their own Filament resources.
- Example: CustomerResource, InstitutionResource, VenueResource.
- Uses core `HasAddresses` relationship.

Rules:

- Do not auto-register this relation manager into other packages.
- Document how to add it manually.
- Respect owner scope from the parent resource/model.
- If parent model is owner-scoped, create addresses under the same owner context.
- Validate submitted address IDs on attach/update.
- Use core actions when available:
  - `CreateAddressForAddressableAction`
  - `SetPrimaryAddressAction`
  - `UpdateAddressAction`
  - `DetachAddressAction`
- If those actions do not exist yet, state in the implementation checklist whether to add them to core or use the model relationship carefully. Prefer adding reusable actions to core if multiple packages will use them.

Relation manager columns:

- Type
- Label
- Line1
- City
- State
- Postcode
- Country code
- Is primary
- Updated at

Relation manager actions:

- Create address
- Edit address
- Set primary
- Detach only if safe
- No destructive delete unless explicitly enabled and tested

## 10. Imports and Exports

### Import Path

Use Filament built-in ImportAction and importer classes only.

Do not create custom upload controllers.

`AddressAreaImporter` must:

- Accept CSV headers:
  - country_code
  - type
  - level
  - name
  - native_name
  - code
  - parent_source_id
  - source
  - source_id
  - latitude
  - longitude
  - metadata
- Validate required fields:
  - country_code
  - type
  - name
  - source
  - source_id
- Convert rows into core `AddressAreaData`.
- Call core `ImportAddressAreasAction`.
- Record row failures clearly.
- Not bypass core importer pipeline.

If core importer only accepts a source object, create a small internal source adapter from Filament import rows.

### Export Path

Use Filament ExportAction and exporter classes only.

Exporter must not expose sensitive owner data by default.

Country export:

- ISO2
- ISO3
- numeric code
- name
- official name
- native name
- entity type
- is independent
- phone code
- currency code
- default locale
- region/subregion

Area export:

- country_code
- type
- level
- name
- native_name
- code
- parent_source_id if derivable
- source
- source_id
- latitude
- longitude

Address export:

- disabled by default.
- If enabled, owner-scoped only.

## 11. Multitenancy and Security

Reference data:

- `AddressCountry` and `AddressArea` are global reference data by default.
- Global data can be viewed/managed only by admin panels where the app has authorized the resource.
- Do not assume the package controls authorization policy.

Address records:

- `Address`, `AddressSnapshot`, and addressables may be tenant-owned depending on core package config.
- If central address/snapshot resources are enabled:
  - use owner-safe queries
  - validate IDs in action handlers
  - do not expose cross-tenant records
  - do not use Filament form option scoping as the only security layer

Implementation rule:

- Every `getEloquentQuery()` must be reviewed for ownership.
- If model uses `HasOwner`, rely on the core owner scope.
- If action accepts IDs, resolve through owner-safe guard/action where available.
- If the package cannot safely determine ownership, disable the action by default and document why.

Testing requirement:

- Add at least one test proving disabled central address resource is not registered by default.
- If address resource is enabled and core owner scoping is available, add a cross-owner regression test.

## 12. Authorization

Do not invent a custom role system.

Use standard Filament/Laravel policy checks:

- `viewAny`
- `view`
- `create`
- `update`
- `delete`
- `deleteAny`
- `restore` not relevant; no SoftDeletes
- `forceDelete` not relevant

Default behavior:

- Countries read-only.
- Snapshots read-only.
- Address deletes disabled unless core supports safe delete and policies allow it.
- Areas can be created/edited if policy allows and config resource is not read-only.

If no policies exist, follow sibling package convention. Do not silently grant dangerous destructive actions.

## 13. Multi-Agent Work Plan

Agents must choose one task group only unless asked to coordinate. Do not overlap files.

### Agent A — Package Skeleton and Context

Owns:

- `packages/filament-addressing/composer.json`
- `packages/filament-addressing/CONTEXT.md`
- `packages/filament-addressing/src/FilamentAddressingServiceProvider.php`
- `packages/filament-addressing/src/FilamentAddressingPlugin.php`
- `packages/filament-addressing/config/filament-addressing.php`

Checklist:

- [ ] Read root `AGENTS.md`.
- [ ] Read `CONTEXT-MAP.md`.
- [ ] Read `packages/addressing/CONTEXT.md`.
- [ ] Read sibling `filament-*` package contexts and configs.
- [ ] Create package composer file following sibling conventions.
- [ ] Create config in required section order: Navigation, Tables, Features, Resources.
- [ ] Create service provider.
- [ ] Create plugin and resource registration by config.
- [ ] Do not create migrations.
- [ ] Do not modify resource classes owned by other agents.
- [ ] Add/update package docs only if this agent changes public install/config behavior.

Verification:

- [ ] `composer dump-autoload` if package discovery requires it.
- [ ] `./vendor/bin/phpstan analyse packages/filament-addressing/src --level=6`
- [ ] `./vendor/bin/pint packages/filament-addressing/src/FilamentAddressingServiceProvider.php packages/filament-addressing/src/FilamentAddressingPlugin.php`

### Agent B — Country and Area Resources

Owns:

- `src/Resources/AddressCountryResource.php`
- `src/Resources/AddressCountryResource/**`
- `src/Resources/AddressAreaResource.php`
- `src/Resources/AddressAreaResource/**`
- `src/Schemas/AddressCountryFormSchema.php`
- `src/Schemas/AddressAreaFormSchema.php`
- `src/Tables/AddressCountryTable.php`
- `src/Tables/AddressAreaTable.php`
- related tests

Checklist:

- [ ] Read `packages/addressing/docs/05-country-data.md` if present.
- [ ] Build Country resource as read-only by default.
- [ ] Build Area resource as manageable reference data.
- [ ] Country table supports search/filter for ISO/name/entity type.
- [ ] Area table supports search/filter for country/type/source.
- [ ] Area parent select is scoped to selected country.
- [ ] No delete/bulk delete by default for countries.
- [ ] No DB logic copied from core.
- [ ] All resource methods use Filament v5 signatures.
- [ ] Navigation group/sort/icons read config.
- [ ] Tests cover resource registration and read-only behavior.

Verification:

- [ ] `./vendor/bin/pest --parallel packages/filament-addressing/tests/Feature/AddressCountryResourceTest.php`
- [ ] `./vendor/bin/pest --parallel packages/filament-addressing/tests/Feature/AddressAreaResourceTest.php`
- [ ] `./vendor/bin/phpstan analyse packages/filament-addressing/src --level=6`

### Agent C — Address and Snapshot Resources

Owns:

- `src/Resources/AddressResource.php`
- `src/Resources/AddressResource/**`
- `src/Resources/AddressSnapshotResource.php`
- `src/Resources/AddressSnapshotResource/**`
- `src/Schemas/AddressFormSchema.php`
- `src/Schemas/AddressInfolistSchema.php`
- `src/Tables/AddressTable.php`
- `src/Tables/AddressSnapshotTable.php`
- related tests

Checklist:

- [ ] Confirm central AddressResource is disabled by default.
- [ ] Confirm SnapshotResource is disabled and read-only by default.
- [ ] If enabled, AddressResource uses owner-safe query behavior.
- [ ] SnapshotResource has no create/edit/delete actions.
- [ ] Address form stores ISO2 `country_code`, not full country name.
- [ ] Area fields are optional.
- [ ] Provider payload hidden unless config enables it.
- [ ] No direct cross-tenant attach/update paths.
- [ ] Tests prove disabled-by-default behavior.

Verification:

- [ ] `./vendor/bin/pest --parallel packages/filament-addressing/tests/Feature/AddressResourceDisabledByDefaultTest.php`
- [ ] `./vendor/bin/pest --parallel packages/filament-addressing/tests/Feature/AddressSnapshotReadOnlyResourceTest.php`
- [ ] `./vendor/bin/phpstan analyse packages/filament-addressing/src --level=6`

### Agent D — Relation Manager and Reusable Components

Owns:

- `src/RelationManagers/AddressesRelationManager.php`
- `src/Support/ResolvesAddressingResources.php`
- `src/Support/GuardsAddressingUi.php`
- reusable schema/table helper changes not owned by Agent B/C
- related tests

Checklist:

- [ ] Build relation manager for parent models using core `HasAddresses`.
- [ ] Do not auto-register relation manager anywhere.
- [ ] Create/update/detach uses core actions if available.
- [ ] Set primary action is explicit.
- [ ] Owner context inherited from parent if applicable.
- [ ] Document how downstream resources opt in manually.
- [ ] Tests cover relation manager form schema generation at minimum.

Verification:

- [ ] `./vendor/bin/pest --parallel packages/filament-addressing/tests/Feature`
- [ ] `./vendor/bin/phpstan analyse packages/filament-addressing/src --level=6`

### Agent E — Import/Export

Owns:

- `src/Imports/AddressAreaImporter.php`
- `src/Exports/AddressCountryExporter.php`
- `src/Exports/AddressAreaExporter.php`
- `src/Exports/AddressExporter.php`
- import/export tests
- usage docs for import/export

Checklist:

- [ ] Use Filament built-in ImportAction and ExportAction only.
- [ ] Importer maps CSV rows to core AddressAreaData/AddressAreaSource contract.
- [ ] Importer calls core ImportAddressAreasAction.
- [ ] Importer requires source/source_id/country_code/type/name.
- [ ] Exporters do not expose provider/source payloads unless config allows.
- [ ] Address export disabled by default.
- [ ] Tests cover importer validation for missing source_id and country_code.

Verification:

- [ ] `./vendor/bin/pest --parallel packages/filament-addressing/tests/Feature`
- [ ] `./vendor/bin/phpstan analyse packages/filament-addressing/src --level=6`

### Agent F — Documentation and Final QC

Owns:

- `docs/*.md`
- `CONTEXT.md` docs links
- final checklist updates
- no production source files unless fixing docs-only typos in examples

Checklist:

- [ ] Required docs exist with YAML frontmatter title.
- [ ] Docs cross-link to core `addressing` docs.
- [ ] Docs say Filament package is adapter only.
- [ ] Docs include install, plugin registration, config, usage, relation manager, import/export, troubleshooting.
- [ ] Examples are copy-paste ready with namespaces/imports.
- [ ] Docs mention country/area/address/snapshot defaults.
- [ ] Docs mention owner-safety and read-only snapshots.
- [ ] Final verification commands are listed.

Verification:

- [ ] Check markdown links manually.
- [ ] `rg -n -- "constrained\(|cascadeOnDelete\(" packages/filament-addressing`
- [ ] `rg -n -- "SoftDeletes" packages/filament-addressing`
- [ ] `./vendor/bin/pest --parallel packages/filament-addressing/tests`
- [ ] `./vendor/bin/phpstan analyse packages/filament-addressing/src --level=6`

## 14. Testing Requirements

Minimum tests:

- Plugin registers only enabled resources.
- Countries resource enabled by default.
- Areas resource enabled by default.
- Address resource disabled by default.
- Snapshot resource disabled by default.
- Country resource does not expose delete/bulk delete.
- Snapshot resource is read-only.
- Area resource parent options are scoped by country.
- Area import validates required fields.
- AddressFormSchema maps country select to ISO2 country code.
- Config toggles work.
- No migrations are published/loaded by this package.

Commands:

```bash
./vendor/bin/pest --parallel packages/filament-addressing/tests
./vendor/bin/phpstan analyse packages/filament-addressing/src --level=6
./vendor/bin/pint packages/filament-addressing/src packages/filament-addressing/tests
rg -n -- "constrained\(|cascadeOnDelete\(" packages/filament-addressing
rg -n -- "SoftDeletes" packages/filament-addressing
```

Run only package-scoped commands.

## 15. Success Criteria

The implementation is complete when:

- `aiarmada/filament-addressing` installs as a standalone adapter package.
- It depends on `aiarmada/addressing` but does not own address domain logic.
- A Filament Panel can register `FilamentAddressingPlugin::make()`.
- Country and Area resources are available by default.
- Address and Snapshot resources are disabled by default.
- Countries are read-only by default.
- Snapshots are always read-only unless a later explicit instruction changes this.
- Areas can be managed/imported/exported safely.
- Reusable address form/schema components are available to downstream packages.
- Relation manager exists but is opt-in.
- Tests pass with `--parallel`.
- PHPStan level 6 passes for package source.
- Docs are complete and cross-linked.
- No repo-wide style churn is introduced.

## 16. Anti-Patterns to Avoid

Do not:

- Put Filament resources inside the core `addressing` package.
- Copy `Address`, `AddressCountry`, `AddressArea`, or `AddressSnapshot` models.
- Add migrations to this adapter package.
- Make central AddressResource enabled by default.
- Edit snapshots.
- Let Filament form scoping be the only security check.
- Hardcode `MY` or Malaysia-specific assumptions.
- Hardcode `state` as the only first-level area label.
- Insert address areas directly from importer without core action/contract.
- Add custom import/export systems when Filament built-ins exist.
- Use static mutable state that leaks under Octane.
- Run Pint/Pest/PHPStan repo-wide.
- Add config keys that are never read.

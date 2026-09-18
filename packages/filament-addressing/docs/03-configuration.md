---
title: Filament Addressing Configuration
---

## Config File

The config file is:

```txt
config/filament-addressing.php
```

Sections are ordered as:

1. Navigation
2. Tables
3. Features
4. Resources

## Navigation

```php
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
```

Use this to control menu visibility, grouping, ordering, and icons.

The `enabled` flag controls whether the adapter registers the resource with the Filament panel. The `sort` value is the base order for country, state, city, area, postcode, address, and snapshot resources.
Each resource reads its icon from `navigation.icons.*`.

## Tables

```php
```

## Features

```php
'features' => [
    'country_editing' => false,
    'area_import' => true,
    'area_export' => true,
    'address_export' => false,
    'show_provider_payload' => false,
    'show_source_payload' => false,
    'postal_code_import' => false,
    'postal_code_export' => false,
    'gap_actions' => true,
],
```

### Country Editing

Keep disabled unless you want to allow safe editing of seeded ISO country/territory display metadata.

Country editing only becomes available when both `resources.countries.read_only` is `false` and `features.country_editing` is `true`.

Do not edit ISO2 or ISO3 values from the UI.

### Address Export

Keep disabled unless you want the central Address resource to expose a built-in export action.

Address exports honor the configured address model and are scoped to the current owner, matching the resource list query.

### Postcode Import And Export

Both default to disabled. Enable them to show import/export header actions on the postcode list. The importer delegates to the core `ImportPostalCodesAction`; expected columns are `country_code`, `code`, `source`, `source_id`, plus optional `area_source`, `area_source_id`, `relationship_type`, `is_primary`, and `metadata` (JSON).

### Source And Provider Payloads

Payloads can be large and may contain provider-specific data. Keep hidden unless an admin/debug panel needs them.

### Gap Actions

Enabled by default. Disable to hide the match-to-area and ignore row actions (plus bulk ignore) on the resolution gap list. The actions are also hidden when `resources.resolution_gaps.read_only` is `true`.

## Resources

```php
'countries' => [
    'enabled' => true,
    'read_only' => true,
    'model' => \AIArmada\Addressing\Models\AddressCountry::class,
],
```

```php
'states' => [
    'enabled' => true,
    'read_only' => false,
    'model' => \AIArmada\Addressing\Models\State::class,
],

'cities' => [
    'enabled' => true,
    'read_only' => false,
    'model' => \AIArmada\Addressing\Models\City::class,
],
```

```php
'areas' => [
    'enabled' => true,
    'read_only' => false,
    'model' => \AIArmada\Addressing\Models\AddressArea::class,
],

'postal_codes' => [
    'enabled' => true,
    'read_only' => false,
    'model' => \AIArmada\Addressing\Models\PostalCode::class,
],

'resolution_gaps' => [
    'enabled' => true,
    'read_only' => false,
    'model' => \AIArmada\Addressing\Models\ResolutionGap::class,
],
```

```php
'addresses' => [
    'enabled' => false,
    'read_only' => false,
    'model' => \AIArmada\Addressing\Models\Address::class,
],
```

```php
'snapshots' => [
    'enabled' => false,
    'read_only' => true,
    'model' => \AIArmada\Addressing\Models\AddressSnapshot::class,
],
```

:::danger
Do not enable `addresses` or `snapshots` in a tenant-aware application until owner-safe queries and policies have been verified.
:::

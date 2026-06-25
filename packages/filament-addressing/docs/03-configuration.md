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

The `enabled` flag hides the adapter resources from Filament navigation while leaving the pages available by direct URL. The `sort` value is the base order for the country, area, address, and snapshot resources.
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
],
```

### Country Editing

Keep disabled unless you want to allow safe editing of seeded ISO country/territory display metadata.

Country editing only becomes available when both `resources.countries.read_only` is `false` and `features.country_editing` is `true`.

Do not edit ISO2 or ISO3 values from the UI.

### Address Export

Keep disabled unless you want the central Address resource to expose a built-in export action.

### Source And Provider Payloads

Payloads can be large and may contain provider-specific data. Keep hidden unless an admin/debug panel needs them.

## Resources

```php
'countries' => [
    'enabled' => true,
    'read_only' => true,
    'model' => \AIArmada\Addressing\Models\AddressCountry::class,
],
```

```php
'areas' => [
    'enabled' => true,
    'read_only' => false,
    'model' => \AIArmada\Addressing\Models\AddressArea::class,
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

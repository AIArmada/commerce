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

Use this to control menu grouping and icons.

## Tables

```php
'tables' => [
    'default_pagination' => 25,
    'search_debounce' => '500ms',
],
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

Keep disabled unless you know you want to edit seeded ISO country/territory display metadata.

Do not edit ISO2 or ISO3 values from the UI.

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

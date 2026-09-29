---
title: Configuration
---

# Configuration

`config/filament-promotions.php` only covers navigation. That is the full
shipped file:

```php
// config/filament-promotions.php
return [

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        'group' => 'Marketing'
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */

    'resources' => [
        'navigation_sort' => [
            'promotions' => 10,
        ],
    ],
];
```

Section order per the package convention is Navigation → Resources. There is no
`tables` or `features` key in the shipped config, and no code in this package
reads one.

## Navigation Configuration

### Navigation Group

Control which navigation group the promotions resource appears under:

```php
'navigation' => [
        'group' => 'Marketing'
    ],
```

Set to `null` to display in the root navigation.

### Navigation Sort

Control the order of the resource in navigation:

```php
'resources' => [
    'navigation_sort' => [
        'promotions' => 10, // Lower = higher in list
    ],
],
```

## Table polling

There is no config key for table polling. `PromotionResource` is `final`, so
build your own resource and disable polling in its table definition:

```php
public static function table(Table $table): Table
{
    return PromotionsTable::configure($table)->poll(null);
}
```

## Widgets

`PromotionStatsWidget` is registered by `FilamentPromotionsPlugin`
unconditionally; there is no feature toggle for it.

## Owner Scoping

Owner scoping is configured in the core promotions package:

```php
// config/promotions.php
'owner' => [
    'enabled' => true,
    'include_global' => true,
],
```

The Filament resource respects these settings automatically.

## Extending Configuration

The package exposes no custom-config extension points. Published keys outside
`navigation.group` and `resources.navigation_sort.promotions` are not read by
any code in this package, so adding your own keys only helps code you write
yourself:

```php
// config/filament-promotions.php
$showExpired = config('filament-promotions.custom.show_expired');
```

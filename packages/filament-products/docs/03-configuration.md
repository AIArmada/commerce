---
title: Configuration
---

# Configuration

## Full Configuration Reference

```php
<?php

// config/filament-products.php

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        // Navigation group for all product resources
        'group' => 'Catalog',

        // Sort order within navigation
        'sort' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */

    'tables' => [
        // Table polling interval (null to disable)
        'poll' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */

    'features' => [
        // Enable import/export functionality
        'import_export' => true,

        // Enable bulk editing page
        'bulk_edit' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */

    'resources' => [
        'product' => [
            'class' => \AIArmada\FilamentProducts\Resources\ProductResource::class,
        ],
        'category' => [
            'class' => \AIArmada\FilamentProducts\Resources\CategoryResource::class,
        ],
        'collection' => [
            'class' => \AIArmada\FilamentProducts\Resources\CollectionResource::class,
        ],
        'attribute' => [
            'class' => \AIArmada\FilamentProducts\Resources\AttributeResource::class,
        ],
        'attribute_group' => [
            'class' => \AIArmada\FilamentProducts\Resources\AttributeGroupResource::class,
        ],
        'attribute_set' => [
            'class' => \AIArmada\FilamentProducts\Resources\AttributeSetResource::class,
        ],
    ],
];
```

## Customizing Resources

### Override Resource Class

To customize a resource, extend the base class and update the config:

```php
// app/Filament/Resources/CustomProductResource.php
namespace App\Filament\Resources;

use AIArmada\FilamentProducts\Resources\ProductResource;

class CustomProductResource extends ProductResource
{
    public static function form(Form $form): Form
    {
        return parent::form($form)
            ->schema([
                // Add custom fields
            ]);
    }
}
```

```php
// config/filament-products.php
'resources' => [
    'product' => [
        'class' => \App\Filament\Resources\CustomProductResource::class,
    ],
],
```

### Disable Specific Resources

To disable a resource, set the class to `null`:

```php
'resources' => [
    'attribute_set' => [
        'class' => null,  // Disabled
    ],
],
```

## Feature Toggles

### Disable Import/Export

```php
'features' => [
    'import_export' => false,
],
```

This hides the Import/Export page from navigation.

### Disable Bulk Edit

```php
'features' => [
    'bulk_edit' => false,
],
```

This hides the Bulk Edit page from navigation.

## Table Polling

Enable auto-refresh for tables:

```php
'tables' => [
    'poll' => '30s',  // Refresh every 30 seconds
],
```

## Navigation Customization

### Via Config

```php
'navigation' => [
    'group' => 'Products',
    'sort' => 5,
],
```

### Via Plugin

```php
FilamentProductsPlugin::make()
    ->navigationGroup('Inventory')
    ->navigationSort(20);
```

### Per-Resource Override

Override in individual resources:

```php
class CustomProductResource extends ProductResource
{
    protected static ?string $navigationGroup = 'My Custom Group';
    protected static ?int $navigationSort = 1;
}
```

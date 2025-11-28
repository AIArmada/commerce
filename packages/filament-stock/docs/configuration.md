# Configuration

## Publishing Configuration

```bash
php artisan vendor:publish --tag=filament-stock-config
```

## Configuration Options

```php
// config/filament-stock.php
return [
    /*
    |--------------------------------------------------------------------------
    | Low Stock Threshold
    |--------------------------------------------------------------------------
    |
    | Items with stock at or below this threshold will appear in the
    | Low Stock Alerts widget.
    |
    */
    'low_stock_threshold' => env('FILAMENT_STOCK_LOW_THRESHOLD', 10),

    /*
    |--------------------------------------------------------------------------
    | Stockable Types Registry
    |--------------------------------------------------------------------------
    |
    | Register your stockable model classes here. These will appear in
    | dropdown selectors for creating transactions and filtering.
    |
    | Each entry should be:
    | 'App\Models\Product' => [
    |     'label' => 'Product',
    |     'searchable' => true,      // Enable search in selectors
    |     'label_attribute' => 'name', // Attribute to use as display label
    | ]
    |
    */
    'stockable_types' => [
        // 'App\Models\Product' => [
        //     'label' => 'Product',
        //     'searchable' => true,
        //     'label_attribute' => 'name',
        // ],
        // 'App\Models\Variant' => [
        //     'label' => 'Product Variant',
        //     'searchable' => true,
        //     'label_attribute' => 'sku',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Reservation Extension Minutes
    |--------------------------------------------------------------------------
    |
    | Default number of minutes to extend a reservation when using
    | the ExtendReservationAction.
    |
    */
    'default_extension_minutes' => env('FILAMENT_STOCK_EXTENSION_MINUTES', 30),
];
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `FILAMENT_STOCK_LOW_THRESHOLD` | `10` | Low stock alert threshold |
| `FILAMENT_STOCK_EXTENSION_MINUTES` | `30` | Default reservation extension time |

## Stockable Types Registry

The stockable types registry powers dropdown selectors throughout the plugin.

### Registering Types

Add your stockable models to the config:

```php
'stockable_types' => [
    \App\Models\Product::class => [
        'label' => 'Product',
        'searchable' => true,
        'label_attribute' => 'name',
    ],
],
```

### Runtime Registration

You can also register types at runtime:

```php
use AIArmada\FilamentStock\Support\StockableTypeRegistry;

// In a service provider
app(StockableTypeRegistry::class)->register(
    Product::class,
    'Product',
    'name',
    true
);
```

### Getting Types

```php
$registry = app(StockableTypeRegistry::class);

// Get all types
$types = $registry->getTypes();

// Get options for select
$options = $registry->getSelectOptions();

// Get label for a specific stockable
$label = StockableTypeRegistry::getStockableLabel(Product::class, $productId);
```

# Configuration

Full configuration reference for Filament CHIP.

## Publish Config

```bash
php artisan vendor:publish --tag="filament-chip-config"
```

## Reference

```php
<?php

// config/filament-chip.php

return [
    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | Customize table names for CHIP data storage.
    |
    */
    'tables' => [
        'prefix' => 'chip_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Billable
    |--------------------------------------------------------------------------
    |
    | Configure the billable model and billing portal settings.
    |
    */
    'billable' => [
        'model' => App\Models\User::class,
        'billing_portal' => [
            'path' => 'billing',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Default currency for display formatting.
    |
    */
    'currency' => 'MYR',

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Customize resource classes or disable specific resources.
    |
    */
    'resources' => [
        'purchase' => \AIArmada\FilamentChip\Resources\PurchaseResource::class,
        'payment' => \AIArmada\FilamentChip\Resources\PaymentResource::class,
        'client' => \AIArmada\FilamentChip\Resources\ClientResource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Customize navigation placement and grouping.
    |
    */
    'navigation' => [
        'group' => 'Payments',
        'icon' => 'heroicon-o-credit-card',
    ],
];
```

## Environment Variables

```env
CHIP_BRAND_ID=your-brand-id
CHIP_API_KEY=your-api-key
CHIP_WEBHOOK_SECRET=your-webhook-secret
```

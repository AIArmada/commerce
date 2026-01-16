---
title: Configuration
---

# Configuration

The configuration file is located at `config/pricing.php`.

## Full Configuration

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'tables' => [
            'prices' => 'prices',
            'price_lists' => 'price_lists',
            'price_tiers' => 'price_tiers',
        ],

        'json_column_type' => env('PRICING_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'currency' => 'MYR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features/Behavior
    |--------------------------------------------------------------------------
    */
    'features' => [
        'owner' => [
            'enabled' => env('PRICING_OWNER_ENABLED', false),
            'include_global' => false,
        ],
    ],
];
```

## Configuration Options

### Database

| Key | Default | Description |
|-----|---------|-------------|
| `database.tables.prices` | `'prices'` | Table name for prices |
| `database.tables.price_lists` | `'price_lists'` | Table name for price lists |
| `database.tables.price_tiers` | `'price_tiers'` | Table name for price tiers |
| `database.json_column_type` | `'json'` | JSON column type for your database |

### Defaults

| Key | Default | Description |
|-----|---------|-------------|
| `defaults.currency` | `'MYR'` | Default currency code (ISO 4217) |

### Features

| Key | Default | Description |
|-----|---------|-------------|
| `features.owner.enabled` | `false` | Enable multitenancy/owner scoping |
| `features.owner.include_global` | `false` | Include global (ownerless) records in queries |

## Environment Variables

```bash
# Enable multitenancy
PRICING_OWNER_ENABLED=true

# JSON column type (for MySQL < 8.0 or MariaDB)
PRICING_JSON_COLUMN_TYPE=json
COMMERCE_JSON_COLUMN_TYPE=json
```

## Pricing Settings (Spatie Laravel Settings)

The package includes settings classes for runtime configuration:

### PricingSettings

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `defaultCurrency` | string | `'MYR'` | Default currency code |
| `decimalPlaces` | int | `2` | Decimal places for display |
| `pricesIncludeTax` | bool | `false` | Whether prices include tax |
| `roundingMode` | string | `'half_up'` | Rounding mode ('up', 'down', 'half_up', 'half_down') |
| `minimumOrderValue` | int | `0` | Minimum order value (cents) |
| `maximumOrderValue` | int | `10000000` | Maximum order value (cents) |
| `promotionalPricingEnabled` | bool | `true` | Enable promotional pricing |
| `tieredPricingEnabled` | bool | `true` | Enable tiered pricing |
| `customerGroupPricingEnabled` | bool | `false` | Enable customer group pricing |

### PromotionalPricingSettings

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `flashSalesEnabled` | bool | `true` | Enable flash sales |
| `defaultFlashSaleDurationHours` | int | `24` | Default flash sale duration |
| `maxDiscountPercentage` | int | `90` | Maximum allowed discount % |
| `allowPromotionStacking` | bool | `false` | Allow multiple promotions |
| `maxStackablePromotions` | int | `2` | Max stackable promotions |
| `showOriginalPrice` | bool | `true` | Show strikethrough price |
| `showCountdownTimers` | bool | `true` | Show countdown for time-limited offers |

## Accessing Settings

```php
use AIArmada\Pricing\Settings\PricingSettings;
use AIArmada\Pricing\Settings\PromotionalPricingSettings;

$settings = app(PricingSettings::class);

// Read settings
$currency = $settings->defaultCurrency;
$isTieredEnabled = $settings->tieredPricingEnabled;

// Format amount using settings
$formatted = $settings->formatAmount(1999); // "RM 19.99"

// Get currency symbol
$symbol = $settings->getCurrencySymbol(); // "RM"
```

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
            'auto_assign_on_create' => true,
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

### Defaults

| Key | Default | Description |
|-----|---------|-------------|
| `defaults.currency` | `'MYR'` | Default currency code (ISO 4217) |

### Features

| Key | Default | Description |
|-----|---------|-------------|
| `features.owner.enabled` | `false` | Enable multitenancy/owner scoping |
| `features.owner.include_global` | `false` | Include global (ownerless) records in queries |
| `features.owner.auto_assign_on_create` | `true` | Auto-assign the resolved owner to newly created pricing records |

### Pricing Settings

Runtime settings are stored via Spatie Settings. Defaults from the settings migration:

| Setting | Default | Description |
|---------|---------|-------------|
| `pricing.defaultCurrency` | `'MYR'` | Default currency code (ISO 4217) |
| `pricing.decimalPlaces` | `2` | Price display decimals |
| `pricing.pricesIncludeTax` | `false` | Show prices inclusive of tax |
| `pricing.roundingMode` | `'half_up'` | Rounding mode |
| `pricing.minimumOrderValue` | `0` | Minimum order in minor units |
| `pricing.maximumOrderValue` | `10000000` | Maximum order in minor units |
| `pricing.promotionalPricingEnabled` | `true` | Enable promotional price resolution |
| `pricing.tieredPricingEnabled` | `true` | Enable tiered pricing |
| `pricing.customerGroupPricingEnabled` | `false` | Enable customer group pricing |

## PricingIntegrationRegistrar

The `PricingIntegrationRegistrar` coordinates how downstream packages (cart, checkout, vouchers, promotions) wire into the pricing system. It is registered as a singleton and can be resolved via the container:

```php
use AIArmada\Pricing\Support\PricingIntegrationRegistrar;

$registrar = app(PricingIntegrationRegistrar::class);

// Access the shared calculator
$calculator = $registrar->calculator();
```

The registrar's `boot()` method is called during service provider registration to wire up registered integrations. Downstream packages register their pricing needs through this registrar rather than directly binding to the container or editing the service provider.

## Environment Variables

```bash
# Enable multitenancy
PRICING_OWNER_ENABLED=true
```



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

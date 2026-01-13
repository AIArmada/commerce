---
title: Configuration
---

# Configuration

## Config File

After publishing, edit `config/tax.php`:

```php
return [
    'database' => [
        'tables' => [
            'tax_zones' => 'tax_zones',
            'tax_rates' => 'tax_rates',
            'tax_classes' => 'tax_classes',
            'tax_exemptions' => 'tax_exemptions',
        ],
        'json_column_type' => env('TAX_JSON_COLUMN_TYPE', 'json'),
    ],

    'defaults' => [
        'prices_include_tax' => env('TAX_PRICES_INCLUDE_TAX', false),
        'calculate_tax_on_shipping' => env('TAX_ON_SHIPPING', true),
        'round_at_subtotal' => true,
    ],

    'features' => [
        'enabled' => env('TAX_ENABLED', true),
        'owner' => [
            'enabled' => env('TAX_OWNER_ENABLED', false),
            'include_global' => false,
        ],
        'zone_resolution' => [
            'use_customer_address' => true,
            'address_priority' => 'shipping', // or 'billing'
            'unknown_zone_behavior' => 'default', // 'default', 'zero', or 'error'
            'fallback_zone_id' => null,
        ],
        'exemptions' => [
            'enabled' => true,
        ],
    ],
];
```

## Configuration Options

### Database

| Key | Type | Description |
|-----|------|-------------|
| `tables.*` | string | Custom table names |
| `json_column_type` | string | `json` or `jsonb` for PostgreSQL |

### Defaults

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `prices_include_tax` | bool | `false` | Whether product prices already include tax |
| `calculate_tax_on_shipping` | bool | `true` | Apply tax to shipping costs |
| `round_at_subtotal` | bool | `true` | Round tax at subtotal vs per-line |

### Features

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | `true` | Master on/off switch for tax calculation |
| `owner.enabled` | bool | `false` | Enable multi-tenancy |
| `owner.include_global` | bool | `false` | Include global (ownerless) records |
| `zone_resolution.use_customer_address` | bool | `true` | Auto-detect zone from address |
| `zone_resolution.address_priority` | string | `'shipping'` | Which address to use for zone detection |
| `zone_resolution.unknown_zone_behavior` | string | `'default'` | How to handle unmatched addresses |
| `exemptions.enabled` | bool | `true` | Enable exemption checking |

## Environment Variables

```env
# Master switch
TAX_ENABLED=true

# Pricing model
TAX_PRICES_INCLUDE_TAX=false
TAX_ON_SHIPPING=true

# Multi-tenancy
TAX_OWNER_ENABLED=false

# Database (PostgreSQL users)
TAX_JSON_COLUMN_TYPE=jsonb
```

## Runtime Settings (Spatie)

For settings that should be changeable without deployment:

```php
use AIArmada\Tax\Settings\TaxSettings;

$settings = app(TaxSettings::class);
$settings->enabled = true;
$settings->pricesIncludeTax = false;
$settings->shippingTaxable = true;
$settings->save();
```

Available settings:

### TaxSettings

| Property | Type | Description |
|----------|------|-------------|
| `enabled` | bool | Enable tax calculation |
| `pricesIncludeTax` | bool | Prices include tax |
| `shippingTaxable` | bool | Tax shipping costs |
| `taxBasedOnShippingAddress` | bool | Use shipping address for zone |

### TaxZoneSettings

| Property | Type | Description |
|----------|------|-------------|
| `autoDetectZone` | bool | Auto-detect from address |
| `defaultZoneId` | ?string | Fallback zone UUID |
| `fallbackBehavior` | string | 'default', 'zero', or 'error' |

## Unknown Zone Behavior

When an address doesn't match any zone:

- **`default`** - Use the zone marked as `is_default`, or zero if none
- **`zero`** - Return zero tax
- **`error`** - Throw `TaxZoneNotFoundException`

```php
// Will throw if unknown_zone_behavior = 'error'
try {
    $result = Tax::calculateTax(10000, 'standard', null, [
        'shipping_address' => ['country' => 'XX'],
    ]);
} catch (TaxZoneNotFoundException $e) {
    // Handle unknown zone
}
```

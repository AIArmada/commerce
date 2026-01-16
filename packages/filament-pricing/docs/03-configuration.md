---
title: Configuration
---

# Configuration

The Filament Pricing package relies on configuration from the base `aiarmada/pricing` package.

## Base Package Configuration

See [Pricing Package Configuration](../../pricing/docs/03-configuration.md) for all configuration options.

Key settings that affect the Filament interface:

```php
// config/pricing.php
return [
    'database' => [
        'tables' => [
            'prices' => 'prices',
            'price_lists' => 'price_lists',
            'price_tiers' => 'price_tiers',
        ],
    ],

    'defaults' => [
        'currency' => 'MYR',
    ],

    'features' => [
        'owner' => [
            'enabled' => env('PRICING_OWNER_ENABLED', false),
            'include_global' => false,
        ],
    ],
];
```

## Plugin Customization

The plugin can be customized when registering with the panel:

```php
use AIArmada\FilamentPricing\FilamentPricingPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentPricingPlugin::make(),
        ]);
}
```

## Resource Navigation

Resources use these default navigation settings:

### PriceListResource

| Setting | Value |
|---------|-------|
| Navigation Group | Pricing |
| Navigation Icon | heroicon-o-currency-dollar |
| Navigation Sort | 1 |
| Record Title | name |

### PromotionResource

| Setting | Value |
|---------|-------|
| Navigation Group | Pricing |
| Navigation Icon | heroicon-o-gift |
| Navigation Sort | 2 |
| Record Title | name |
| Navigation Badge | Count of active promotions |

### ManagePricingSettings

| Setting | Value |
|---------|-------|
| Navigation Group | Settings |
| Navigation Icon | heroicon-o-currency-dollar |
| Navigation Sort | 10 |

### PriceSimulator

| Setting | Value |
|---------|-------|
| Navigation Group | Pricing |
| Navigation Icon | heroicon-o-calculator |
| Navigation Sort | 99 |

## Currency Options

The settings page and resources provide these currency options by default:

- MYR - Malaysian Ringgit
- USD - US Dollar
- EUR - Euro
- GBP - British Pound
- SGD - Singapore Dollar
- THB - Thai Baht
- IDR - Indonesian Rupiah
- PHP - Philippine Peso

For price list resources, a smaller subset is shown:

- MYR
- USD
- SGD

## Widget Configuration

The `PricingStatsWidget` uses:

| Setting | Value |
|---------|-------|
| Polling Interval | 30 seconds |

## Multitenancy Settings

When `pricing.features.owner.enabled` is `true`:

1. **Resources** automatically scope queries to current owner
2. **Relation managers** validate foreign keys against owner scope
3. **Price Simulator** scopes product/customer searches to owner
4. **Stats Widget** shows owner-scoped statistics

Configure via environment:

```bash
PRICING_OWNER_ENABLED=true
```

## Extending Resources

You can extend the default resources by creating your own and registering them:

```php
// app/Filament/Resources/CustomPriceListResource.php
namespace App\Filament\Resources;

use AIArmada\FilamentPricing\Resources\PriceListResource;

class CustomPriceListResource extends PriceListResource
{
    // Override methods as needed
    
    public static function form(Schema $schema): Schema
    {
        return parent::form($schema)->schema([
            // Add custom fields
        ]);
    }
}
```

Then register your custom resource instead of using the plugin, or use Filament's resource overriding mechanisms.

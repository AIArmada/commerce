---
title: Configuration
---

# Configuration

The Filament Pricing package relies on configuration from the base `aiarmada/pricing` package.

## Filament Package Configuration

The admin navigation is configured in `config/filament-pricing.php`:

```php
'navigation' => [
    'group' => 'Pricing',
    'settings_group' => 'Settings',
],

'resources' => [
    'navigation_sort' => [
        'price_lists' => 1,
    ],
],

'pages' => [
    'navigation_sort' => [
        'settings' => 10,
        'price_simulator' => 99,
    ],
],
```

## Authorization

Pricing settings are global (shared by every tenant), so the settings page
gates on a single ability:

```php
'authorization' => [
    'settings_ability' => 'pricing.manage-settings',
],
```

Define that ability with a Gate in the host app.

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

`PriceListResource` is declared `final`, so it cannot be subclassed. Register
your own resource instead and decorate the shipped one with Filament's
resource-override mechanism:

```php
// app/Filament/Resources/CustomPriceListResource.php
namespace App\Filament\Resources;

use AIArmada\Pricing\Models\PriceList;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;

class CustomPriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    public static function form(Schema $schema): Schema
    {
        // Build your own schema
    }
}
```

Then register it in your panel provider, or use Filament's resource
overriding mechanisms to layer your own schema on top of the shipped resource.

---
title: Configuration
---

# Configuration

## Full Configuration

```php
<?php

// config/filament-affiliate-network.php

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'Affiliate Network',
        'sort' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'poll_interval' => null, // e.g., '10s' for live updates
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'merchant_portal' => true,
        'affiliate_marketplace' => true,
        'site_verification' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Marketplace
    |--------------------------------------------------------------------------
    */
    'marketplace' => [
        'offers_per_page' => 12,
        'show_commission_rates' => true,
        'show_cookie_duration' => true,
    ],
];
```

## Configuration Options

### Navigation

| Key | Description | Default |
|-----|-------------|---------|
| `group` | Navigation group name | `Affiliate Network` |
| `sort` | Navigation sort order | `50` |

### Tables

| Key | Description | Default |
|-----|-------------|---------|
| `poll_interval` | Auto-refresh interval | `null` (disabled) |

### Features

| Key | Description | Default |
|-----|-------------|---------|
| `merchant_portal` | Enable merchant dashboard | `true` |
| `affiliate_marketplace` | Enable marketplace page | `true` |
| `site_verification` | Enable verification UI | `true` |

### Marketplace

| Key | Description | Default |
|-----|-------------|---------|
| `offers_per_page` | Offers per page | `12` |
| `show_commission_rates` | Show commission in cards | `true` |
| `show_cookie_duration` | Show cookie days in cards | `true` |

## Customizing Resources

### Change Navigation Label

Extend the resource:

```php
namespace App\Filament\Resources;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource as BaseResource;

class AffiliateOfferResource extends BaseResource
{
    protected static ?string $navigationLabel = 'Campaigns';
    
    protected static ?string $modelLabel = 'Campaign';
}
```

### Add Custom Columns

```php
public static function table(Table $table): Table
{
    return parent::table($table)
        ->columns([
            ...parent::getTableColumns(),
            Tables\Columns\TextColumn::make('custom_field'),
        ]);
}
```

### Add Custom Form Fields

```php
public static function form(Schema $schema): Schema
{
    return parent::form($schema)
        ->components([
            ...parent::getFormSchema(),
            Section::make('Custom')
                ->schema([
                    TextInput::make('custom_field'),
                ]),
        ]);
}
```

## Customizing Pages

### Override Marketplace Page

Create your own page:

```php
namespace App\Filament\Pages;

use AIArmada\FilamentAffiliateNetwork\Pages\AffiliateMarketplacePage as BasePage;

class AffiliateMarketplacePage extends BasePage
{
    protected static ?string $title = 'Offer Discovery';
    
    protected function getOffers(): Collection
    {
        return parent::getOffers()
            ->filter(fn ($offer) => $offer->is_featured);
    }
}
```

## Customizing Widgets

### Override Stats Widget

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentAffiliateNetwork\Widgets\NetworkStatsWidget as BaseWidget;

class NetworkStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            ...parent::getStats(),
            Stat::make('Custom Metric', $this->calculateCustomMetric()),
        ];
    }
}
```

## Disabling Components

### Disable Specific Resources

Create a custom plugin:

```php
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;

FilamentAffiliateNetworkPlugin::make()
    ->resources([
        AffiliateSiteResource::class,
        AffiliateOfferResource::class,
        // Omit others to disable
    ]);
```

### Disable Pages

```php
// In config
'features' => [
    'merchant_portal' => false,      // Disable dashboard
    'affiliate_marketplace' => false, // Disable marketplace
],
```

---
title: Configuration
---

# Configuration

## Full Configuration

```php
<?php

return [
    'navigation' => [
        'group' => 'Affiliate Network',
        'sort' => 50,
    ],

    'authorization' => [
        'admin_ability' => 'affiliate-network.admin',
    ],

    'marketplace' => [
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

### Authorization

| Key | Description | Default |
|-----|-------------|---------|
| `admin_ability` | Gate ability required for the network-wide resources, merchant dashboard, and reporting widgets | `affiliate-network.admin` |

The host application must authorize this ability. The guarded surfaces intentionally bypass tenant scopes because they are global network administration surfaces.

```php
Gate::define('affiliate-network.admin', fn (User $user): bool => $user->is_admin);
```

### Marketplace

| Key | Description | Default |
|-----|-------------|---------|
| `show_commission_rates` | Show commission rates on marketplace cards | `true` |
| `show_cookie_duration` | Show cookie duration on marketplace cards | `true` |

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

### Use a Custom Plugin Class

`FilamentAffiliateNetworkPlugin` registers a fixed set of resources/pages/widgets. To disable components, extend the plugin and register only the components you want:

```php
use AIArmada\FilamentAffiliateNetwork\FilamentAffiliateNetworkPlugin;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;
use Filament\Panel;

final class CustomAffiliateNetworkPlugin extends FilamentAffiliateNetworkPlugin
{
    public function register(Panel $panel): void
    {
        $panel->resources([
            AffiliateSiteResource::class,
            AffiliateOfferResource::class,
        ]);
    }
}
```

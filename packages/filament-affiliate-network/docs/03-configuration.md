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
| `show_commission_rates` | Reserved; **not read by the current code** | `true` |
| `show_cookie_duration` | Reserved; **not read by the current code** | `true` |

There is no marketplace page in this package, so nothing consumes these keys
today. Treat them as placeholders.

## Customizing Resources

### Change Navigation Label

Every shipped resource is `final`, so neither `getNavigationLabel()` nor the
`$navigationLabel` property can be overridden by subclassing. The extension
seams are the schema and table classes under
`Resources/<Resource>/{Schemas,Tables}/` — write your own `Resource` and point
`form()` / `table()` at them. See [Usage](04-usage.md#extending-resources) for a
worked example.

### Add Custom Columns

`getTableColumns()` is a v3-era hook and no longer exists in Filament v5. Build
the column list explicitly:

```php
public static function table(Table $table): Table
{
    return $table->columns([
        Tables\Columns\TextColumn::make('name'),
        Tables\Columns\TextColumn::make('custom_field'),
    ]);
}
```

### Add Custom Form Fields

`getFormSchema()` is likewise gone. Compose components on the `Schema`:

```php
public static function form(Schema $schema): Schema
{
    return $schema
        ->components([
            Section::make('Custom')
                ->schema([
                    TextInput::make('custom_field'),
                ]),
        ]);
}
```

## Customizing Pages

### Override the Merchant Dashboard View

`MerchantDashboardPage` is `final`, but its Blade view is publishable:

```bash
php artisan vendor:publish --tag=filament-affiliate-network-views
```

## Customizing Widgets

### Replace the Stats Widget

`NetworkStatsWidget` is `final`. Write your own `StatsOverviewWidget` and
register it on the panel:

```php
namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NetworkStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Custom Metric', $this->calculateCustomMetric()),
        ];
    }
}
```

## Disabling Components

### Use a Custom Plugin Class

`FilamentAffiliateNetworkPlugin` is `final` and has no `resources()` /
`pages()` / `widgets()` methods — it registers a fixed set. To change what the
panel gets, register components explicitly in your `PanelProvider` instead of
the plugin:

```php
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;

public function panel(Panel $panel): Panel
{
    return $panel
        ->resources([
            AffiliateSiteResource::class,
            AffiliateOfferResource::class,
        ]);
}
```

---
title: Configuration
---

# Configuration

## Full configuration

```php
<?php

declare(strict_types=1);

return [
    'navigation' => [
        'group' => 'Affiliate Network',
        'sort' => 50,
    ],

    'authorization' => [
        'admin_ability' => 'affiliate-network.admin',
    ],
];
```

## Options

### Navigation

| Key | Description | Default |
|-----|-------------|---------|
| `group` | Navigation group name | `Affiliate Network` |
| `sort` | Base navigation sort order | `50` |

Resource sorts are offset from `sort`: sites `+0`, offers `+1`, categories
`+2`, applications `+3`, statements `+4`. Every resource reads
`getNavigationGroup()` / `getNavigationSort()` from config (never a static
property), which lets `CommerceNavigation` override them at runtime via
`commerce-support.filament.navigation.items.{FQCN}`.

### Authorization

| Key | Description | Default |
|-----|-------------|---------|
| `admin_ability` | Gate ability required for resources, merchant dashboard, and network widgets | `affiliate-network.admin` |

```php
use Illuminate\Support\Facades\Gate;

Gate::define('affiliate-network.admin', fn (User $user): bool => $user->is_admin);
```

`NetworkAdminAccess::allows()` reads this key. `MerchantStatementResource` and
the two widgets also bypass tenant scope because they are network-wide admin
surfaces.

## Customizing resources

Every shipped resource is `final`; override `getNavigationLabel()` by writing
your own resource and reusing the shipped schema/table classes:

```php
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Schemas\AffiliateOfferForm;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Tables\AffiliateOffersTable;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AffiliateCampaignResource extends Resource
{
    protected static ?string $model = AffiliateOffer::class;

    protected static ?string $navigationLabel = 'Campaigns';

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group');
    }

    public static function form(Schema $schema): Schema
    {
        return AffiliateOfferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateOffersTable::configure($table);
    }
}
```

> **tip**
> In Filament v5 (as used by this package), `form()`, `table()`, and
> `infolist()` are **static** methods. The v3 hooks `getFormSchema()` and
> `getTableColumns()` no longer exist — compose components in a
> `Schemas\XForm` / `Tables\XTable` class and return it from the resource.

## Customizing pages and widgets

`MerchantDashboardPage`, `NetworkStatsWidget`, and `TopOffersWidget` are
`final`. Build your own and reuse `Support\NetworkStatsAggregator`:

```php
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkStatsAggregator;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NetworkStatsWidget extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return NetworkAdminAccess::allows();
    }

    protected function getStats(): array
    {
        $stats = NetworkStatsAggregator::aggregate();

        return [
            Stat::make('Active sites', number_format($stats['activeSites'])),
        ];
    }
}
```

## Disabling components

`FilamentAffiliateNetworkPlugin` registers a fixed set and exposes no
`resources()` / `pages()` / `widgets()` methods. Register components explicitly
on the panel instead:

```php
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;

public function panel(Panel $panel): Panel
{
    return $panel->resources([
        AffiliateSiteResource::class,
        AffiliateOfferResource::class,
    ]);
}
```

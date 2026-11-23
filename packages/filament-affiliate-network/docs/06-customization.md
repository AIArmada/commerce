---
title: Customization
---

# Customization

Extend the plugin by writing your own resources/pages/widgets and reusing the
shipped schema and table classes. Every shipped component is `final`.

## Resources are final

`AffiliateSiteResource`, `AffiliateOfferResource`,
`AffiliateOfferCategoryResource`, `AffiliateOfferApplicationResource`, and
`MerchantStatementResource` are `final`. In Filament v5 (as used here),
`form()`, `table()`, and `infolist()` are **static** methods;
`getFormSchema()` and `getTableColumns()` no longer exist.

Write your own resource and point it at the shipped classes:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\CouponsRelationManager;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Schemas\AffiliateOfferForm;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Tables\AffiliateOffersTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AffiliateCampaignResource extends Resource
{
    protected static ?string $model = AffiliateOffer::class;

    protected static ?string $navigationLabel = 'Campaigns';

    protected static ?string $modelLabel = 'Campaign';

    protected static ?string $pluralModelLabel = 'Campaigns';

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

    public static function getRelations(): array
    {
        return [
            \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\CreativesRelationManager::class,
            \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\LinksRelationManager::class,
            \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\LegsRelationManager::class,
            \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\InvitationsRelationManager::class,
            \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\DealsRelationManager::class,
            \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\CouponsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\AffiliateCampaignResource\Pages\ListCampaigns::route('/'),
            'edit' => \App\Filament\Resources\AffiliateCampaignResource\Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
```

Register on the panel:

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            \AIarmada\FilamentAffiliateNetwork\FilamentAffiliateNetworkPlugin::make(),
        ])
        ->resources([
            \App\Filament\Resources\AffiliateCampaignResource::class,
        ]);
}
```

## Pages and widgets

`MerchantDashboardPage`, `NetworkStatsWidget`, and `TopOffersWidget` are `final`.
Build standalone components instead.

### Custom merchant page

```php
<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use Filament\Pages\Page;

class MerchantInsights extends Page
{
    protected static ?string $navigationLabel = 'Merchant Insights';

    public static function canAccess(): bool
    {
        return NetworkAdminAccess::allows();
    }

    protected function getHeaderWidgets(): array
    {
        return [\App\Filament\Widgets\RevenueChartWidget::class];
    }
}
```

### Custom stats widget

```php
<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkStatsAggregator;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NetworkStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return NetworkAdminAccess::allows();
    }

    protected function getStats(): array
    {
        $stats = NetworkStatsAggregator::aggregate();

        return [
            Stat::make('Active sites', number_format($stats['activeSites']))
                ->description('Verified merchant sites')
                ->icon('heroicon-o-globe-alt'),

            Stat::make('Total revenue', $stats['revenueFormatted'])
                ->description('Tracked revenue')
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
```

### Chart widget

```php
<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ConversionsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Conversions (Last 30 Days)';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $data = collect(range(29, 0))->map(function (int $daysAgo): array {
            $date = Carbon::today()->subDays($daysAgo);

            return [
                'date' => $date->format('M d'),
                'conversions' => AffiliateOfferLink::query()
                    ->whereDate('updated_at', $date)
                    ->sum('conversions'),
            ];
        });

        return [
            'datasets' => [[
                'label' => 'Conversions',
                'data' => $data->pluck('conversions')->all(),
                'borderColor' => '#6366f1',
                'fill' => false,
            ]],
            'labels' => $data->pluck('date')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

## Views

```bash
php artisan vendor:publish --tag=filament-affiliate-network-views
```

The only shipped view is `pages/merchant-dashboard.blade.php`. There is no
marketplace view to override.

## Authorization

Five policies are auto-bound by the service provider:
`AffiliateSitePolicy`, `AffiliateOfferPolicy`,
`AffiliateOfferCategoryPolicy`, `AffiliateOfferApplicationPolicy`,
`MerchantStatementPolicy`. Override with your own:

```php
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use Illuminate\Support\Facades\Gate;

Gate::policy(AffiliateOffer::class, \App\Policies\AffiliateOfferPolicy::class);
```

Network-wide surfaces still gate on `NetworkAdminAccess::allows()`; keep that
check in any replacement page/widget.

## Multi-panel setup

```php
// MerchantPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('merchant')
        ->path('merchant')
        ->plugins([
            \AIarmada\FilamentAffiliateNetwork\FilamentAffiliateNetworkPlugin::make(),
        ]);
}
```

A custom resource set requires your own plugin; the shipped plugin is `final`
and registers a fixed set.

## Conditional registration

```php
<?php

declare(strict_types=1);

namespace App\Filament;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class CustomAffiliateNetworkPlugin implements Plugin
{
    public function getId(): string
    {
        return 'custom-affiliate-network';
    }

    public static function make(): static
    {
        return app(self::class);
    }

    public function register(Panel $panel): void
    {
        $resources = [AffiliateSiteResource::class];

        if (config('app.features.affiliate_network_categories', true)) {
            $resources[] = AffiliateOfferCategoryResource::class;
        }

        $panel->resources($resources);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
```

## Relation managers

All six shipped relation managers are `final`. Write your own in the same shape:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\AffiliateOfferResource\RelationManagers;

use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CreativesRelationManager extends RelationManager
{
    protected static string $relationship = 'creatives';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('type')->badge(),
                TextColumn::make('width')->suffix('px'),
                TextColumn::make('height')->suffix('px'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordTitleAttribute('name');
    }
}
```

> **warning**
> Do not use `Tables\Columns\BadgeColumn` (removed in v4); use
> `TextColumn::make('type')->badge()`. Do not hardcode `->money('USD', divideBy: 100)` —
> use `MoneyFormatter::formatMinor($minor, $currency)`.

### Money formatting in a relation manager

```php
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use Filament\Tables\Columns\TextColumn;

TextColumn::make('payout_minor')
    ->label('Payout')
    ->formatStateUsing(fn (NetworkConversionLeg $record): string => MoneyFormatter::formatMinor(
        $record->payout_minor,
        $record->commission_currency,
    ));
```

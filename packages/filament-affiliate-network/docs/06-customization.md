---
title: Customization
---

# Customization

Extend and customize the plugin to fit your application.

## Extending Resources

> **warning:**
> Every shipped resource is `final` — `AffiliateSiteResource`,
> `AffiliateOfferResource`, `AffiliateOfferCategoryResource`, and
> `AffiliateOfferApplicationResource` cannot be extended. To change behaviour,
> register your own resource pointing at the same model, or configure
> navigation at runtime through
> `commerce-support.filament.navigation.items.{FQCN}`. The `extends BaseResource`
> pattern below does not compile.

```php
<?php

namespace App\Filament\Resources;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AffiliateCampaignResource extends Resource
{
    protected static ?string $model = AffiliateOffer::class;

    protected static ?string $navigationLabel = 'Campaigns';
    protected static ?string $modelLabel = 'Campaign';
    protected static ?string $pluralModelLabel = 'Campaigns';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                // Add custom columns
                Tables\Columns\TextColumn::make('rate_base_bp'),
            ]);
    }

    public function getRelations(): array
    {
        return [
            // Add relation managers
            RelationManagers\LinksRelationManager::class,
            RelationManagers\LegsRelationManager::class,
        ];
    }
}
```

> **tip:**
> In Filament v5, `table()`/`form()`/`infolist()` are **instance** methods on
> the resource, not `static` overrides. `getTableColumns()` and
> `getFormSchema()` no longer exist — columns live in a
> `Tables\XTable` class and schema in a `Schemas\XForm` class, returned by
> `table(Table $table)` / `form(Schema $schema)`.

### Register Custom Resource

In your panel provider:

```php
use AIArmada\FilamentAffiliateNetwork\FilamentAffiliateNetworkPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentAffiliateNetworkPlugin::make(),
        ])
        ->resources([
            \App\Filament\Resources\AffiliateCampaignResource::class,
        ]);
}
```

---

## Extending Pages

### Custom Merchant Dashboard

`MerchantDashboardPage` is `final` and cannot be extended. Build a standalone
page instead:

```php
<?php

namespace App\Filament\Pages;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Filament\Pages\Page;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MerchantInsights extends Page
{
    protected static ?string $navigationLabel = 'Merchant Insights';

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\CustomNetworkStatsWidget::class,
            \App\Filament\Widgets\RevenueChartWidget::class,
        ];
    }
}
```

---

## Extending Widgets

### Custom Stats Widget

`NetworkStatsWidget` is `final`. Write your own and reuse the shared aggregator:

```php
<?php

namespace App\Filament\Widgets;

use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkStatsAggregator;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
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
        $aggregated = NetworkStatsAggregator::aggregate();

        return [
            Stat::make('Active Sites', number_format($aggregated['activeSites']))
                ->description('Verified merchant sites')
                ->icon('heroicon-o-globe-alt'),

            // Add custom metric
            Stat::make('Total Revenue', $aggregated['revenueFormatted'])
                ->description('Tracked revenue')
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
```

> **tip:**
> Format money with `AIArmada\CommerceSupport\Support\MoneyFormatter::formatMinor($minor, $currency)`
> rather than dividing by 100 yourself — the minor-unit scale is not always
> 100 (MYR is 100, but JPY and KWD are not).

### Add Chart Widget

```php
<?php

namespace App\Filament\Widgets;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ConversionsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Conversions (Last 30 Days)';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';
    
    protected function getData(): array
    {
        $data = collect(range(29, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);
            
            return [
                'date' => $date->format('M d'),
                'conversions' => AffiliateOfferLink::whereDate('updated_at', $date)
                    ->sum('conversions'),
            ];
        });
        
        return [
            'datasets' => [
                [
                    'label' => 'Conversions',
                    'data' => $data->pluck('conversions')->toArray(),
                    'borderColor' => '#6366f1',
                    'fill' => false,
                ],
            ],
            'labels' => $data->pluck('date')->toArray(),
        ];
    }
    
    protected function getType(): string
    {
        return 'line';
    }
}
```

---

## Custom Views

### Publish Views

```bash
php artisan vendor:publish --tag=filament-affiliate-network-views
```

The only shipped view is `pages/merchant-dashboard.blade.php`; it publishes to
`resources/views/vendor/filament-affiliate-network/pages/merchant-dashboard.blade.php`.

There is no marketplace view to override — the package has no marketplace page.

---

## Authorization

### Resource Authorization

The package ships four policies, bound in
`FilamentAffiliateNetworkServiceProvider::packageBooted()`:
`AffiliateSitePolicy`, `AffiliateOfferPolicy`, `AffiliateOfferCategoryPolicy`,
`AffiliateOfferApplicationPolicy`. Filament calls them automatically, so you
do not re-declare `canViewAny()` on a resource.

```php
<?php

namespace App\Policies;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use App\Models\User;

class AffiliateOfferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-offers');
    }

    public function create(User $user): bool
    {
        return $user->can('create-offers');
    }

    public function update(User $user, AffiliateOffer $offer): bool
    {
        return $user->can('edit-offers');
    }

    public function delete(User $user, AffiliateOffer $offer): bool
    {
        return $user->can('delete-offers');
    }
}
```

Register your own with `Gate::policy(AffiliateOffer::class, YourPolicy::class)`.

### Widget Authorization

```php
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use Filament\Widgets\Widget;

class MyWidget extends Widget
{
    public static function canView(): bool
    {
        return NetworkAdminAccess::allows();
    }
}
```

### Page Authorization

```php
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use Filament\Pages\Page;

class MerchantInsights extends Page
{
    public static function canAccess(): bool
    {
        return NetworkAdminAccess::allows();
    }
}
```

---

## Multi-Panel Setup

### Separate Merchant and Affiliate Panels

```php
// MerchantPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('merchant')
        ->path('merchant')
        ->plugins([
            FilamentAffiliateNetworkPlugin::make(),
        ])
        ->pages([
            MerchantDashboardPage::class,
        ]);
}

// AffiliatePanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('affiliate')
        ->path('affiliate')
        ->pages([
            // Affiliate-specific pages
        ]);
}
```

---

## Conditional Resource Registration

`FilamentAffiliateNetworkPlugin` is `final` — extend `Plugin` and implement
`Plugin::getId()` yourself:

```php
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
        $resources = [
            AffiliateSiteResource::class,
        ];

        // Example: conditionally add category resource
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

> **tip:**
> The shipped plugin is already minimal — it registers 4 resources, 1 page,
> and 2 widgets, all behind `NetworkAdminAccess::allows()`. Reach for a custom
> plugin only when you genuinely need a different resource set.

---

## Adding Relation Managers

Both shipped relation managers are `final`. Write your own in the same shape
(Filament v5 instance `table()`):

```php
<?php

namespace App\Filament\Resources\AffiliateOfferResource\RelationManagers;

use AIArmada\AffiliateNetwork\Resources\AffiliateOfferResource;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CreativesRelationManager extends RelationManager
{
    protected static string $relationship = 'creatives';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('width')
                    ->suffix('px'),
                Tables\Columns\TextColumn::make('height')
                    ->suffix('px'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
```

### Links Relation Manager

```php
<?php

namespace App\Filament\Resources\AffiliateOfferResource\RelationManagers;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OfferLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'links';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('affiliate_id')
                    ->label('Affiliate ID'),
                Tables\Columns\TextColumn::make('link.slug')
                    ->label('Slug')
                    ->copyable(),
                Tables\Columns\TextColumn::make('clicks')
                    ->numeric(),
                Tables\Columns\TextColumn::make('conversions')
                    ->numeric(),
                Tables\Columns\TextColumn::make('revenue')
                    ->formatStateUsing(fn ($state, $record): string => MoneyFormatter::formatMinor(
                        (int) ($state ?? 0),
                        $record->currency ?? config('affiliate-network.currency.default'),
                    )),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ]);
    }
}
```

> **warning:**
> `Tables\Columns\BadgeColumn` was removed in Filament v4. Use
> `TextColumn::make('type')->badge()`. `IconColumn` still exists and is used by
> the shipped tables. Do not hardcode `->money('USD', divideBy: 100)` — it
> assumes a 2-decimal currency and breaks for JPY/KWD.

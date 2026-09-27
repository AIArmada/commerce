---
title: Customization
---

# Customization

Extend and customize the plugin to fit your application.

## Extending Resources

### Override a Resource

Create your own resource extending the base:

```php
<?php

namespace App\Filament\Resources;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource as BaseResource;
use Filament\Tables;
use Filament\Tables\Table;

class AffiliateOfferResource extends BaseResource
{
    protected static ?string $navigationLabel = 'Campaigns';
    protected static ?string $modelLabel = 'Campaign';
    protected static ?string $pluralModelLabel = 'Campaigns';
    
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                // Add custom columns
                Tables\Columns\TextColumn::make('custom_field'),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            // Add relation managers
            RelationManagers\CreativesRelationManager::class,
            RelationManagers\LinksRelationManager::class,
        ];
    }
}
```

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
            \App\Filament\Resources\AffiliateOfferResource::class,
        ]);
}
```

---

## Extending Pages

### Custom Merchant Dashboard

```php
<?php

namespace App\Filament\Pages;

use AIArmada\FilamentAffiliateNetwork\Pages\MerchantDashboardPage as BasePage;

class MerchantDashboardPage extends BasePage
{
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

```php
<?php

namespace App\Filament\Widgets;

use AIArmada\FilamentAffiliateNetwork\Widgets\NetworkStatsWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NetworkStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected function getStats(): array
    {
        $stats = parent::getStats();
        
        // Add custom metric
        $stats[] = Stat::make('Avg. Order Value', '$' . number_format($this->getAverageOrderValue(), 2))
            ->description('Per conversion')
            ->icon('heroicon-o-shopping-cart')
            ->color('info');
            
        return $stats;
    }
    
    private function getAverageOrderValue(): float
    {
        $links = \AIArmada\AffiliateNetwork\Models\AffiliateOfferLink::query();
        $totalRevenue = $links->sum('revenue');
        $totalConversions = $links->sum('conversions');
        
        return $totalConversions > 0 
            ? ($totalRevenue / $totalConversions) / 100 
            : 0;
    }
}
```

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

Files published to `resources/views/vendor/filament-affiliate-network/`.

### Customize Merchant Dashboard View

Edit `resources/views/vendor/filament-affiliate-network/pages/merchant-dashboard.blade.php` to restyle the stats, top offers, and pending applications sections.

---

## Authorization

### Resource Authorization

```php
<?php

namespace App\Filament\Resources;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource as BaseResource;

class AffiliateOfferResource extends BaseResource
{
    public static function canViewAny(): bool
    {
        return auth()->user()->can('view-offers');
    }
    
    public static function canCreate(): bool
    {
        return auth()->user()->can('create-offers');
    }
    
    public static function canEdit($record): bool
    {
        return auth()->user()->can('edit-offers');
    }
    
    public static function canDelete($record): bool
    {
        return auth()->user()->can('delete-offers');
    }
}
```

### Widget Authorization

```php
<?php

namespace App\Filament\Widgets;

use AIArmada\FilamentAffiliateNetwork\Widgets\NetworkStatsWidget as BaseWidget;

class NetworkStatsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()->hasRole(['admin', 'merchant']);
    }
}
```

### Page Authorization

```php
<?php

namespace App\Filament\Pages;

use AIArmada\FilamentAffiliateNetwork\Pages\MerchantDashboardPage as BasePage;

class MerchantDashboardPage extends BasePage
{
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('merchant');
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
        ])
        ->resources([
            AffiliateSiteResource::class,
            AffiliateOfferResource::class,
            AffiliateOfferCategoryResource::class,
            AffiliateOfferApplicationResource::class,
        ]);
}

// AffiliatePanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('affiliate')
        ->path('affiliate')
        ->pages([
            MerchantDashboardPage::class,
            // Affiliate-specific pages
        ]);
}
```

---

## Conditional Resource Registration

```php
// Custom plugin extending base
class CustomAffiliateNetworkPlugin extends FilamentAffiliateNetworkPlugin
{
    public function register(Panel $panel): void
    {
        $resources = [
            AffiliateSiteResource::class,
            AffiliateOfferResource::class,
        ];
        
        // Example: conditionally add category resource
        if (config('app.features.affiliate_network_categories', true)) {
            $resources[] = AffiliateOfferCategoryResource::class;
        }
        
        // Example: conditionally add applications
        if (config('app.features.affiliate_network_applications', true)) {
            $resources[] = AffiliateOfferApplicationResource::class;
        }
        
        $panel->resources($resources);
    }
}
```

---

## Adding Relation Managers

### Creatives Relation Manager

```php
<?php

namespace App\Filament\Resources\AffiliateOfferResource\RelationManagers;

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
                Tables\Columns\BadgeColumn::make('type'),
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

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LinksRelationManager extends RelationManager
{
    protected static string $relationship = 'links';
    
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('link.slug')
                    ->label('Slug')
                    ->copyable(),
                Tables\Columns\TextColumn::make('affiliate.code')
                    ->label('Affiliate'),
                Tables\Columns\TextColumn::make('clicks')
                    ->numeric(),
                Tables\Columns\TextColumn::make('conversions')
                    ->numeric(),
                Tables\Columns\TextColumn::make('revenue')
                    ->money('USD', divideBy: 100),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ]);
    }
}
```

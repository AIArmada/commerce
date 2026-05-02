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

### Custom Marketplace Page

```php
<?php

namespace App\Filament\Pages;

use AIArmada\FilamentAffiliateNetwork\Pages\AffiliateMarketplacePage as BasePage;
use Illuminate\Support\Collection;

class AffiliateMarketplacePage extends BasePage
{
    protected static ?string $title = 'Partner Opportunities';
    protected static ?string $navigationLabel = 'Find Offers';
    
    public function getOffers(): Collection
    {
        // Only show featured offers with high commission
        return parent::getOffers()
            ->filter(fn ($offer) => $offer->is_featured)
            ->filter(fn ($offer) => $offer->commission_rate >= 1000);
    }
}
```

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

### Customize Marketplace View

Edit `resources/views/vendor/filament-affiliate-network/pages/affiliate-marketplace.blade.php`:

```blade
<x-filament-panels::page>
    {{-- Custom header --}}
    <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold">Discover Partner Opportunities</h1>
        <p class="text-gray-500">Browse and apply for affiliate programs</p>
    </div>
    
    {{-- Existing search & filters --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        {{-- Search and filter controls --}}
    </div>
    
    {{-- Custom offer cards --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->getOffers() as $offer)
            <x-filament::section>
                {{-- Custom card layout --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold">{{ $offer->name }}</h3>
                    <p class="text-sm text-gray-600">{{ $offer->site->name }}</p>
                    
                    {{-- Commission badge --}}
                    <x-filament::badge color="success">
                        {{ $offer->commission_type === 'percentage' 
                            ? number_format($offer->commission_rate / 100, 2) . '%'
                            : '$' . number_format($offer->commission_rate / 100, 2) }}
                    </x-filament::badge>
                    
                    {{-- Apply button --}}
                    @if (!$this->hasApplied($offer))
                        <x-filament::button wire:click="applyForOffer('{{ $offer->id }}')">
                            Apply Now
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
```

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
            AffiliateMarketplacePage::class,
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
                Tables\Columns\TextColumn::make('code')
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

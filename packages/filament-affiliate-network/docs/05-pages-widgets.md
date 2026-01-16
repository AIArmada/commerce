---
title: Pages & Widgets
---

# Pages & Widgets

## Pages

### AffiliateMarketplacePage

A discovery page where affiliates browse and apply for offers.

**Features:**
- Search offers by name/description
- Filter by category
- Sort by featured, newest, or commission
- View commission rates and cookie duration
- Apply to offers directly
- Generate links for approved offers

**URL:** `/affiliate-network/marketplace`

**Livewire Properties:**
- `$search` - Search query
- `$categoryFilter` - Selected category ID
- `$sortBy` - Sort option (featured, newest, commission)

**Methods:**

```php
// Get active categories
$this->getCategories();

// Get filtered offers
$this->getOffers();

// Get current user's affiliate
$this->getAffiliate();

// Check if applied to offer
$this->hasApplied($offer);

// Get application status
$this->getApplicationStatus($offer); // pending, approved, rejected, null

// Apply to an offer
$this->applyForOffer($offerId, $reason);

// Generate tracking link (for approved)
$this->generateLink($offerId);
```

**Customization:**

```php
namespace App\Filament\Pages;

use AIArmada\FilamentAffiliateNetwork\Pages\AffiliateMarketplacePage as BasePage;

class AffiliateMarketplacePage extends BasePage
{
    protected static ?string $title = 'Partner Opportunities';
    
    public function getOffers(): Collection
    {
        return parent::getOffers()
            ->filter(fn ($offer) => $offer->commission_rate >= 500);
    }
}
```

---

### MerchantDashboardPage

Analytics dashboard for merchants.

**Features:**
- Site overview
- Offer performance
- Application statistics
- Click/conversion metrics

**URL:** `/affiliate-network/merchant-dashboard`

**Customization:**

Override the view:

```bash
php artisan vendor:publish --tag=filament-affiliate-network-views
```

Edit `resources/views/vendor/filament-affiliate-network/pages/merchant-dashboard.blade.php`.

---

## Widgets

### NetworkStatsWidget

Overview statistics for the entire network.

**Metrics:**
- Active Sites (verified)
- Active Offers (live)
- Pending Applications (awaiting review)
- Total Clicks (network-wide)
- Conversion Rate (clicks to conversions)
- Total Revenue (tracked revenue)

**Sort Order:** 1 (appears first on dashboard)

**Usage:**

Register on your dashboard:

```php
namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use AIArmada\FilamentAffiliateNetwork\Widgets\NetworkStatsWidget;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            NetworkStatsWidget::class,
            // Other widgets...
        ];
    }
}
```

**Customization:**

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentAffiliateNetwork\Widgets\NetworkStatsWidget as BaseWidget;

class NetworkStatsWidget extends BaseWidget
{
    protected static ?int $sort = 5;
    
    protected function getStats(): array
    {
        $stats = parent::getStats();
        
        // Add custom stat
        $stats[] = Stat::make('Custom', $this->customValue())
            ->icon('heroicon-o-star');
            
        return $stats;
    }
}
```

---

### TopOffersWidget

Display top performing offers.

**Features:**
- Top offers by clicks
- Top offers by conversions
- Top offers by revenue

**Customization:**

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentAffiliateNetwork\Widgets\TopOffersWidget as BaseWidget;

class TopOffersWidget extends BaseWidget
{
    protected int $limit = 10; // Show top 10
    
    protected string $sortBy = 'conversions'; // Sort by conversions
}
```

---

## Widget Authorization

Control widget visibility:

```php
use Filament\Widgets\Widget;

class NetworkStatsWidget extends Widget
{
    public static function canView(): bool
    {
        return auth()->user()->hasRole('admin');
    }
}
```

---

## Adding Custom Widgets

Create a new widget:

```php
namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;

class ConversionsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Conversions Over Time';
    
    protected function getData(): array
    {
        $data = AffiliateOfferLink::selectRaw('DATE(created_at) as date, SUM(conversions) as total')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
            
        return [
            'datasets' => [
                [
                    'label' => 'Conversions',
                    'data' => $data->pluck('total'),
                    'borderColor' => '#6366f1',
                ],
            ],
            'labels' => $data->pluck('date')->map(fn ($d) => $d->format('M d')),
        ];
    }
    
    protected function getType(): string
    {
        return 'line';
    }
}
```

Register in plugin or panel:

```php
$panel->widgets([
    ConversionsChartWidget::class,
]);
```

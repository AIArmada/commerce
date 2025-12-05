# Campaign Management

> **Document:** 05-campaign-management.md  
> **Status:** Vision  
> **Priority:** P2

---

## Overview

Campaign management elevates vouchers from isolated discounts to **orchestrated promotional strategies** with measurable outcomes, A/B testing, and automation.

---

## Vision: Campaign-First Promotions

### 5.1 Campaign Structure

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CAMPAIGN HIERARCHY                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Campaign (Parent)                                                   │
│  ├── Name: "Black Friday 2025"                                      │
│  ├── Objective: revenue_increase                                    │
│  ├── Budget: RM50,000                                               │
│  ├── Date Range: Nov 25-30, 2025                                    │
│  │                                                                   │
│  ├── Variant A (50% traffic)                                        │
│  │   └── Voucher: "BF25-A" (25% off, min RM100)                    │
│  │                                                                   │
│  ├── Variant B (50% traffic)                                        │
│  │   └── Voucher: "BF25-B" (RM30 off, min RM150)                   │
│  │                                                                   │
│  └── Analytics                                                       │
│      ├── Impressions: 45,000                                        │
│      ├── Applications: 12,000                                       │
│      ├── Conversions: 3,600                                         │
│      ├── Revenue: RM180,000                                         │
│      └── Winner: Variant A (+15% conversion)                        │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### 5.2 Campaign Types

```php
enum CampaignType: string
{
    case Promotional = 'promotional';   // Time-limited sales
    case Acquisition = 'acquisition';   // New customer incentives
    case Retention = 'retention';       // Win-back campaigns
    case Loyalty = 'loyalty';           // Reward programs
    case Seasonal = 'seasonal';         // Holiday promotions
    case Flash = 'flash';               // Limited-time urgency
    case Referral = 'referral';         // Refer-a-friend
}
```

### 5.3 Campaign Objectives

```php
enum CampaignObjective: string
{
    case RevenueIncrease = 'revenue_increase';
    case OrderVolumeIncrease = 'order_volume_increase';
    case AverageOrderValue = 'aov_increase';
    case NewCustomerAcquisition = 'new_customer';
    case CustomerRetention = 'retention';
    case InventoryClearance = 'inventory_clearance';
    case CategoryGrowth = 'category_growth';
}
```

---

## Database Schema

### Campaigns Table

```php
Schema::create('voucher_campaigns', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    // Identity
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    
    // Type & Objective
    $table->string('type')->default('promotional');
    $table->string('objective')->default('revenue_increase');
    
    // Budget & Limits
    $table->bigInteger('budget_cents')->nullable();
    $table->bigInteger('spent_cents')->default(0);
    $table->integer('max_redemptions')->nullable();
    $table->integer('current_redemptions')->default(0);
    
    // Schedule
    $table->timestamp('starts_at');
    $table->timestamp('ends_at');
    $table->string('timezone')->default('UTC');
    
    // A/B Testing
    $table->boolean('ab_testing_enabled')->default(false);
    $table->string('ab_winner_variant')->nullable();
    $table->timestamp('ab_winner_declared_at')->nullable();
    
    // Status
    $table->string('status')->default('draft'); // draft, scheduled, active, paused, completed, cancelled
    
    // Multi-tenancy
    $table->nullableUuidMorphs('owner');
    
    // Analytics
    $table->jsonb('metrics')->nullable();
    
    $table->timestamps();
    $table->softDeletes();
    
    // Indexes
    $table->index(['status', 'starts_at', 'ends_at']);
    $table->index(['owner_type', 'owner_id', 'status']);
});
```

### Campaign Variants Table

```php
Schema::create('voucher_campaign_variants', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('campaign_id');
    
    // Identity
    $table->string('name');
    $table->char('variant_code', 1); // A, B, C, etc.
    
    // Traffic Allocation
    $table->decimal('traffic_percentage', 5, 2)->default(100);
    
    // Associated Voucher
    $table->foreignUuid('voucher_id')->nullable();
    
    // Metrics
    $table->integer('impressions')->default(0);
    $table->integer('applications')->default(0);
    $table->integer('conversions')->default(0);
    $table->bigInteger('revenue_cents')->default(0);
    
    // Control variant flag
    $table->boolean('is_control')->default(false);
    
    $table->timestamps();
    
    // Unique variant per campaign
    $table->unique(['campaign_id', 'variant_code']);
});
```

### Campaign Events Table

```php
Schema::create('voucher_campaign_events', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('campaign_id');
    $table->foreignUuid('variant_id')->nullable();
    
    // Event Details
    $table->string('event_type'); // impression, application, conversion, abandonment
    $table->string('voucher_code')->nullable();
    
    // Context
    $table->nullableUuidMorphs('user');
    $table->nullableUuidMorphs('cart');
    $table->nullableUuidMorphs('order');
    
    // Attribution
    $table->string('channel')->nullable();
    $table->string('source')->nullable();
    $table->string('medium')->nullable();
    
    // Value
    $table->bigInteger('value_cents')->nullable();
    
    $table->jsonb('metadata')->nullable();
    $table->timestamp('occurred_at');
    
    // Indexes for analytics
    $table->index(['campaign_id', 'event_type', 'occurred_at']);
    $table->index(['variant_id', 'event_type']);
});
```

---

## A/B Testing Framework

### Variant Assignment

```php
class CampaignVariantAssigner
{
    public function assignVariant(Campaign $campaign, ?Model $user = null): CampaignVariant
    {
        // Check for existing assignment (sticky sessions)
        if ($user && $existing = $this->getExistingAssignment($campaign, $user)) {
            return $existing;
        }
        
        // Weighted random selection
        $variants = $campaign->variants()
            ->where('traffic_percentage', '>', 0)
            ->get();
        
        $rand = random_int(0, 10000) / 100; // 0.00 to 100.00
        $cumulative = 0;
        
        foreach ($variants as $variant) {
            $cumulative += $variant->traffic_percentage;
            if ($rand <= $cumulative) {
                $this->recordAssignment($campaign, $variant, $user);
                return $variant;
            }
        }
        
        return $variants->last();
    }
}
```

### Statistical Significance

```php
class ABTestAnalyzer
{
    public function analyze(Campaign $campaign): ABTestResult
    {
        $variants = $campaign->variants;
        $control = $variants->firstWhere('is_control', true);
        
        $results = [];
        
        foreach ($variants as $variant) {
            $conversionRate = $variant->applications > 0
                ? $variant->conversions / $variant->applications
                : 0;
            
            $results[$variant->variant_code] = [
                'variant' => $variant,
                'conversion_rate' => $conversionRate,
                'sample_size' => $variant->applications,
                'revenue_per_conversion' => $variant->conversions > 0
                    ? $variant->revenue_cents / $variant->conversions
                    : 0,
            ];
        }
        
        // Calculate statistical significance
        if ($control) {
            foreach ($results as $code => &$result) {
                if ($result['variant']->is_control) {
                    $result['significance'] = null;
                    continue;
                }
                
                $result['significance'] = $this->calculateSignificance(
                    $results[$control->variant_code],
                    $result
                );
                
                $result['lift'] = $this->calculateLift(
                    $results[$control->variant_code]['conversion_rate'],
                    $result['conversion_rate']
                );
            }
        }
        
        return new ABTestResult(
            campaign: $campaign,
            variants: $results,
            isSignificant: $this->hasSignificantWinner($results),
            suggestedWinner: $this->determineSuggestedWinner($results)
        );
    }
    
    private function calculateSignificance(array $control, array $variant): float
    {
        // Z-test for proportions
        $p1 = $control['conversion_rate'];
        $p2 = $variant['conversion_rate'];
        $n1 = $control['sample_size'];
        $n2 = $variant['sample_size'];
        
        if ($n1 === 0 || $n2 === 0) {
            return 0;
        }
        
        $pooledP = ($p1 * $n1 + $p2 * $n2) / ($n1 + $n2);
        $se = sqrt($pooledP * (1 - $pooledP) * (1/$n1 + 1/$n2));
        
        if ($se === 0.0) {
            return 0;
        }
        
        $z = abs($p2 - $p1) / $se;
        
        // Convert Z-score to confidence level (simplified)
        return min(99.9, $this->zToConfidence($z));
    }
}
```

---

## Campaign Automation

### Trigger-Based Campaigns

```php
enum CampaignTrigger: string
{
    case CartAbandonment = 'cart_abandonment';
    case FirstPurchase = 'first_purchase';
    case Birthday = 'birthday';
    case Anniversary = 'anniversary';
    case InactivityPeriod = 'inactivity';
    case SpendThreshold = 'spend_threshold';
    case CategoryBrowse = 'category_browse';
    case WishlistItem = 'wishlist_item';
}
```

### Automation Rules

```php
class CampaignAutomation
{
    public function configure(Campaign $campaign, array $rules): void
    {
        $campaign->automation_rules = [
            'trigger' => $rules['trigger'],
            'conditions' => $rules['conditions'] ?? [],
            'delay' => $rules['delay'] ?? null,
            'channels' => $rules['channels'] ?? ['email'],
            'frequency_cap' => $rules['frequency_cap'] ?? null,
        ];
        
        $campaign->save();
    }
}

// Example: Cart abandonment automation
CampaignAutomation::configure($campaign, [
    'trigger' => CampaignTrigger::CartAbandonment,
    'conditions' => [
        ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
        ['type' => 'abandonment_time', 'operator' => '>=', 'value' => 60], // minutes
    ],
    'delay' => '1 hour',
    'channels' => ['email', 'push'],
    'frequency_cap' => [
        'max_per_user' => 1,
        'period' => '7 days',
    ],
]);
```

---

## Campaign Analytics

### Metrics Dashboard

```php
class CampaignAnalytics
{
    public function getMetrics(Campaign $campaign): CampaignMetrics
    {
        return new CampaignMetrics(
            impressions: $this->countEvents($campaign, 'impression'),
            applications: $this->countEvents($campaign, 'application'),
            conversions: $this->countEvents($campaign, 'conversion'),
            abandonments: $this->countEvents($campaign, 'abandonment'),
            revenue: $this->sumEventValues($campaign, 'conversion'),
            discountGiven: $this->calculateDiscountGiven($campaign),
            averageOrderValue: $this->calculateAOV($campaign),
            conversionRate: $this->calculateConversionRate($campaign),
            roi: $this->calculateROI($campaign),
        );
    }
    
    public function getTimeSeries(Campaign $campaign, string $metric, string $interval = 'day'): Collection
    {
        return CampaignEvent::query()
            ->where('campaign_id', $campaign->id)
            ->where('event_type', $this->eventTypeForMetric($metric))
            ->selectRaw("DATE_TRUNC(?, occurred_at) as period", [$interval])
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(value_cents) as value')
            ->groupBy('period')
            ->orderBy('period')
            ->get();
    }
}
```

---

## Integration Points

### Voucher-Campaign Link

```php
// When creating a voucher, optionally link to campaign
$voucher = Voucher::create([
    'code' => 'BF25-A',
    'campaign_id' => $campaign->id,
    'campaign_variant_id' => $variantA->id,
    // ... other fields
]);

// Voucher inherits campaign targeting and schedule
$voucher->getCampaign()->isActive(); // Checks campaign status too
```

### Event Tracking

```php
// In VoucherService
public function apply(string $code, Cart $cart): Cart
{
    $voucher = $this->findOrFail($code);
    
    // Track campaign event
    if ($voucher->campaign_id) {
        CampaignEvent::record(
            campaign: $voucher->campaign,
            variant: $voucher->campaignVariant,
            type: 'application',
            cart: $cart
        );
    }
    
    // ... rest of application logic
}
```

---

## Implementation Phases

### Phase 1: Campaign Foundation
- [ ] `Campaign` model
- [ ] `CampaignVariant` model
- [ ] Basic campaign CRUD

### Phase 2: A/B Testing
- [ ] `CampaignVariantAssigner`
- [ ] Sticky session support
- [ ] `ABTestAnalyzer`

### Phase 3: Analytics
- [ ] `CampaignEvent` model
- [ ] `CampaignAnalytics` service
- [ ] Time series queries

### Phase 4: Automation
- [ ] Trigger system
- [ ] Delay/scheduling
- [ ] Frequency caps

### Phase 5: Filament UI
- [ ] Campaign builder
- [ ] A/B test dashboard
- [ ] Performance charts

---

## Navigation

**Previous:** [04-gift-card-system.md](04-gift-card-system.md)  
**Next:** [06-stacking-policies.md](06-stacking-policies.md)

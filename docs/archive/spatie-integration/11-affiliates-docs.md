# Affiliates & Docs Packages: Spatie Integration Blueprint

> **Packages:** `aiarmada/affiliates`, `aiarmada/docs`  
> **Status:** Built (Enhanceable)  
> **Role:** Extension Layer - Marketing & Documentation

---

## 📋 Current State Analysis

### Affiliates Package

- Affiliate registration & management
- Commission calculation & tracking
- Referral link generation
- Payout management
- Performance analytics

### Docs Package

- Documentation generation
- PDF export (already uses spatie/laravel-pdf)
- Markdown processing
- Content management

---

## 🎯 Critical Integration: laravel-activitylog (Affiliates)

### Affiliate Activity Tracking

Every affiliate action should be logged for:
- Commission disputes resolution
- Fraud detection
- Performance analysis
- Compliance audits

```php
// affiliates/src/Models/Affiliate.php

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

class Affiliate extends Model
{
    use HasUuids;
    use LogsCommerceActivity;

    protected $fillable = [
        'user_id',
        'code',
        'name',
        'email',
        'commission_rate',
        'status',
        'approved_at',
        'metadata',
    ];

    protected function getLoggableAttributes(): array
    {
        return [
            'code',
            'name',
            'email',
            'commission_rate',
            'status',
        ];
    }

    protected function getActivityLogName(): string
    {
        return 'affiliates';
    }

    // Explicit logging for critical actions
    public function approve(string $approvedBy): void
    {
        $this->update([
            'status' => 'active',
            'approved_at' => now(),
        ]);

        activity('affiliates')
            ->performedOn($this)
            ->withProperties(['approved_by' => $approvedBy])
            ->log('Affiliate application approved');
    }

    public function suspend(string $reason): void
    {
        $this->update(['status' => 'suspended']);

        activity('affiliates')
            ->performedOn($this)
            ->withProperties(['reason' => $reason])
            ->log('Affiliate suspended');
    }
}
```

### Commission Activity Logging

```php
// affiliates/src/Models/Commission.php

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Model;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

class Commission extends Model
{
    use LogsCommerceActivity;

    protected $fillable = [
        'affiliate_id',
        'order_id',
        'amount',
        'currency',
        'rate',
        'status',
        'calculated_at',
        'approved_at',
        'paid_at',
    ];

    protected function getLoggableAttributes(): array
    {
        return [
            'amount',
            'status',
            'approved_at',
            'paid_at',
        ];
    }

    protected function getActivityLogName(): string
    {
        return 'commissions';
    }
}
```

### Payout Activity Logging

```php
// affiliates/src/Models/Payout.php

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Model;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

class Payout extends Model
{
    use LogsCommerceActivity;

    protected $fillable = [
        'affiliate_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payment_reference',
        'processed_at',
    ];

    protected function getLoggableAttributes(): array
    {
        return [
            'amount',
            'status',
            'payment_method',
            'payment_reference',
        ];
    }

    protected function getActivityLogName(): string
    {
        return 'payouts';
    }
}
```

---

## 🎯 Secondary Integration: laravel-model-states (Affiliates)

### Payout State Machine

Payouts have a clear lifecycle that benefits from state machine pattern.

```php
// affiliates/src/States/PayoutState.php

namespace AIArmada\Affiliates\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class PayoutState extends State
{
    abstract public function color(): string;
    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Approved::class, TransitionToApproved::class)
            ->allowTransition(Pending::class, Rejected::class, TransitionToRejected::class)
            ->allowTransition(Approved::class, Processing::class, TransitionToProcessing::class)
            ->allowTransition(Processing::class, Paid::class, TransitionToPaid::class)
            ->allowTransition(Processing::class, Failed::class, TransitionToFailed::class)
            ->allowTransition(Failed::class, Processing::class); // Retry
    }
}

// Individual states
class Pending extends PayoutState
{
    public function color(): string { return 'yellow'; }
    public function label(): string { return 'Pending Approval'; }
}

class Approved extends PayoutState
{
    public function color(): string { return 'blue'; }
    public function label(): string { return 'Approved'; }
}

class Rejected extends PayoutState
{
    public function color(): string { return 'red'; }
    public function label(): string { return 'Rejected'; }
}

class Processing extends PayoutState
{
    public function color(): string { return 'purple'; }
    public function label(): string { return 'Processing'; }
}

class Paid extends PayoutState
{
    public function color(): string { return 'green'; }
    public function label(): string { return 'Paid'; }
}

class Failed extends PayoutState
{
    public function color(): string { return 'red'; }
    public function label(): string { return 'Failed'; }
}
```

### Payout Transitions

```php
// affiliates/src/States/Transitions/TransitionToPaid.php

namespace AIArmada\Affiliates\States\Transitions;

use Spatie\ModelStates\Transition;
use AIArmada\Affiliates\Models\Payout;
use AIArmada\Affiliates\States\Paid;
use AIArmada\Affiliates\Events\PayoutCompleted;

class TransitionToPaid extends Transition
{
    public function __construct(
        public Payout $payout,
        public string $paymentReference,
        public ?string $transactionId = null,
    ) {}

    public function handle(): Payout
    {
        $this->payout->update([
            'state' => Paid::class,
            'payment_reference' => $this->paymentReference,
            'transaction_id' => $this->transactionId,
            'processed_at' => now(),
        ]);

        // Update associated commissions
        $this->payout->commissions()->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        activity('payouts')
            ->performedOn($this->payout)
            ->withProperties([
                'payment_reference' => $this->paymentReference,
                'transaction_id' => $this->transactionId,
                'amount' => $this->payout->amount,
            ])
            ->log('Payout completed successfully');

        event(new PayoutCompleted($this->payout));

        return $this->payout;
    }
}
```

### Affiliate State Machine

```php
// affiliates/src/States/AffiliateState.php

namespace AIArmada\Affiliates\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class AffiliateState extends State
{
    abstract public function color(): string;
    abstract public function label(): string;
    abstract public function canEarnCommissions(): bool;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Active::class)
            ->allowTransition(Pending::class, Rejected::class)
            ->allowTransition(Active::class, Suspended::class)
            ->allowTransition(Suspended::class, Active::class)
            ->allowTransition([Pending::class, Active::class, Suspended::class], Terminated::class);
    }
}

class PendingAffiliate extends AffiliateState
{
    public function color(): string { return 'yellow'; }
    public function label(): string { return 'Pending Approval'; }
    public function canEarnCommissions(): bool { return false; }
}

class ActiveAffiliate extends AffiliateState
{
    public function color(): string { return 'green'; }
    public function label(): string { return 'Active'; }
    public function canEarnCommissions(): bool { return true; }
}

class SuspendedAffiliate extends AffiliateState
{
    public function color(): string { return 'orange'; }
    public function label(): string { return 'Suspended'; }
    public function canEarnCommissions(): bool { return false; }
}

class TerminatedAffiliate extends AffiliateState
{
    public function color(): string { return 'red'; }
    public function label(): string { return 'Terminated'; }
    public function canEarnCommissions(): bool { return false; }
}
```

---

## 🎯 Tertiary Integration: laravel-tags (Affiliates)

### Affiliate Segmentation

```php
// affiliates/src/Models/Affiliate.php

use Spatie\Tags\HasTags;

class Affiliate extends Model
{
    use HasTags;

    public const TAG_TYPE_TIER = 'tier';
    public const TAG_TYPE_PROGRAM = 'program';
    public const TAG_TYPE_PERFORMANCE = 'performance';

    /**
     * Assign affiliate to tier
     */
    public function assignToTier(string $tier): self
    {
        // Remove existing tier tags (single tier only)
        $this->detachTags($this->tagsWithType(self::TAG_TYPE_TIER));
        $this->attachTag($tier, self::TAG_TYPE_TIER);
        return $this;
    }

    /**
     * Update performance tags based on metrics
     */
    public function updatePerformanceTags(): void
    {
        $tags = [];

        // Based on total earnings
        if ($this->total_earnings > 50000) {
            $tags[] = 'top-performer';
        } elseif ($this->total_earnings > 10000) {
            $tags[] = 'high-performer';
        }

        // Based on conversion rate
        if ($this->conversion_rate > 0.15) {
            $tags[] = 'high-converter';
        }

        // Based on referral count
        if ($this->referrals_count > 100) {
            $tags[] = 'influencer';
        }

        $this->syncTagsWithType($tags, self::TAG_TYPE_PERFORMANCE);
    }

    /**
     * Add to specific programs
     */
    public function addToProgram(string $program): self
    {
        $this->attachTag($program, self::TAG_TYPE_PROGRAM);
        return $this;
    }

    /**
     * Query affiliates by tier
     */
    public function scopeInTier($query, string $tier)
    {
        return $query->withAnyTags([$tier], self::TAG_TYPE_TIER);
    }
}
```

---

## 🎯 Docs Package Enhancement: laravel-export

### Already Integrated: spatie/laravel-pdf

The docs package already uses `spatie/laravel-pdf` (^1.5) for PDF generation.

### Potential Enhancement: Browsershot for Screenshots

```php
// docs/src/Services/DocumentationScreenshotService.php

namespace AIArmada\Docs\Services;

use Spatie\Browsershot\Browsershot;

class DocumentationScreenshotService
{
    public function capturePageScreenshot(string $url, string $outputPath): void
    {
        Browsershot::url($url)
            ->windowSize(1920, 1080)
            ->setScreenshotType('png')
            ->waitUntilNetworkIdle()
            ->save($outputPath);
    }

    public function captureComponentScreenshot(
        string $html,
        string $outputPath,
        int $width = 800
    ): void {
        Browsershot::html($html)
            ->windowSize($width, 600)
            ->fullPage()
            ->setScreenshotType('png')
            ->save($outputPath);
    }

    public function generatePdfFromUrl(string $url, string $outputPath): void
    {
        Browsershot::url($url)
            ->format('A4')
            ->margins(20, 20, 20, 20)
            ->showBackground()
            ->save($outputPath);
    }
}
```

---

## 📊 Affiliate State & Commission Flow

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                      AFFILIATE LIFECYCLE                                      │
├──────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│   ┌─────────────┐  approve()  ┌──────────┐  suspend()  ┌───────────┐        │
│   │   Pending   ├────────────►│  Active  ├────────────►│ Suspended │        │
│   └──────┬──────┘             └─────┬────┘◄────────────┴─────┬─────┘        │
│          │                          │      reactivate()       │              │
│          │ reject()                 │                         │              │
│          ▼                          │                         │ terminate()  │
│   ┌─────────────┐                  │                         │              │
│   │  Rejected   │                  └─────────┬───────────────┘              │
│   └─────────────┘                            │                               │
│                                              ▼                               │
│                                      ┌─────────────┐                         │
│                                      │ Terminated  │                         │
│                                      └─────────────┘                         │
│                                                                               │
└──────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────────┐
│                      PAYOUT LIFECYCLE                                         │
├──────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│   ┌─────────────────┐                                                        │
│   │ Commissions     │                                                        │
│   │ Accumulated     │                                                        │
│   └────────┬────────┘                                                        │
│            │ requestPayout()                                                 │
│            ▼                                                                 │
│   ┌─────────────────┐  approve()  ┌──────────┐  process()  ┌────────────┐   │
│   │     Pending     ├────────────►│ Approved ├────────────►│ Processing │   │
│   └────────┬────────┘             └──────────┘             └─────┬──────┘   │
│            │                                                      │          │
│            │ reject()                              ┌──────────────┼─────┐    │
│            ▼                                       │              │     │    │
│   ┌─────────────────┐                             ▼              ▼     │    │
│   │    Rejected     │                       ┌──────────┐   ┌──────────┐│    │
│   └─────────────────┘                       │  Failed  │   │   Paid   ││    │
│                                             └────┬─────┘   └──────────┘│    │
│                                                  │                      │    │
│                                                  └──────────────────────┘    │
│                                                         retry()              │
│                                                                               │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 🎯 Affiliate Analytics with Activity Log

```php
// affiliates/src/Services/AffiliateAnalyticsService.php

namespace AIArmada\Affiliates\Services;

use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use AIArmada\Affiliates\Models\Affiliate;

class AffiliateAnalyticsService
{
    public function getAffiliateTimeline(Affiliate $affiliate, int $days = 30): Collection
    {
        return Activity::query()
            ->where(function ($query) use ($affiliate) {
                $query->forSubject($affiliate)
                    ->orWhere('properties->affiliate_id', $affiliate->id);
            })
            ->where('created_at', '>=', now()->subDays($days))
            ->latest()
            ->get()
            ->map(fn (Activity $activity) => [
                'timestamp' => $activity->created_at,
                'type' => $activity->log_name,
                'event' => $activity->description,
                'details' => $activity->properties,
            ]);
    }

    public function generateFraudReport(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $suspiciousPatterns = [];

        // Find affiliates with unusually high conversion rates
        $highConverters = Affiliate::where('conversion_rate', '>', 0.5)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        foreach ($highConverters as $affiliate) {
            $suspiciousPatterns[] = [
                'affiliate' => $affiliate->code,
                'flag' => 'high_conversion_rate',
                'value' => $affiliate->conversion_rate,
                'activity' => Activity::forSubject($affiliate)
                    ->whereBetween('created_at', [$from, $to])
                    ->get(),
            ];
        }

        // Find rapid commission accumulation
        $rapidAccumulators = Activity::where('log_name', 'commissions')
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->groupBy('subject_id')
            ->filter(fn ($group) => $group->count() > 50); // 50+ commissions

        foreach ($rapidAccumulators as $affiliateId => $activities) {
            $suspiciousPatterns[] = [
                'affiliate_id' => $affiliateId,
                'flag' => 'rapid_accumulation',
                'commission_count' => $activities->count(),
            ];
        }

        return $suspiciousPatterns;
    }
}
```

---

## 📦 composer.json Updates

### affiliates/composer.json

```json
{
    "name": "aiarmada/affiliates",
    "description": "Affiliate marketing system for AIArmada Commerce",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "aiarmada/commerce-support": "^1.0",
        "spatie/laravel-model-states": "^2.7",
        "spatie/laravel-tags": "^4.6"
    },
    "autoload": {
        "psr-4": {
            "AIArmada\\Affiliates\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "AIArmada\\Affiliates\\AffiliatesServiceProvider"
            ]
        }
    }
}
```

### docs/composer.json

Already has spatie/laravel-pdf. No changes needed unless adding browsershot:

```json
{
    "name": "aiarmada/docs",
    "require": {
        "php": "^8.4",
        "aiarmada/commerce-support": "^1.0",
        "spatie/laravel-pdf": "^1.5"
    },
    "suggest": {
        "spatie/browsershot": "For advanced PDF generation and screenshots"
    }
}
```

---

## ✅ Implementation Checklist

### Phase 1: Affiliate Activity Logging

- [ ] Add LogsCommerceActivity to Affiliate model
- [ ] Add LogsCommerceActivity to Commission model
- [ ] Add LogsCommerceActivity to Payout model
- [ ] Create explicit logging methods for critical actions
- [ ] Write tests for activity logging

### Phase 2: Payout State Machine

- [ ] Create PayoutState abstract class
- [ ] Create all payout state classes
- [ ] Create transition classes
- [ ] Add HasStates to Payout model
- [ ] Write state transition tests

### Phase 3: Affiliate State Machine

- [ ] Create AffiliateState abstract class
- [ ] Create all affiliate state classes
- [ ] Add canEarnCommissions() method
- [ ] Integrate with commission calculation

### Phase 4: Affiliate Segmentation

- [ ] Add HasTags to Affiliate model
- [ ] Define tier, program, performance tag types
- [ ] Create auto-tagging based on performance
- [ ] Create segment-based query scopes

### Phase 5: Analytics & Fraud Detection

- [ ] Create AffiliateAnalyticsService
- [ ] Implement timeline generation
- [ ] Implement fraud detection report
- [ ] Create Filament analytics widgets

---

## 🔗 Related Documents

- [00-overview.md](00-overview.md) - Master overview
- [01-commerce-support.md](01-commerce-support.md) - Activity log foundation
- [04-orders-package.md](04-orders-package.md) - Commission triggers from orders
- [08-payment-packages.md](08-payment-packages.md) - Payout processing

---

*This blueprint was created by the Visionary Chief Architect.*

# Dynamic Commission System

> **Document:** 08 of 11  
> **Package:** `aiarmada/affiliates`  
> **Status:** 🟡 25% Implemented  
> **Last Updated:** December 5, 2025

---

## Overview

Build a flexible **dynamic commission system** with rule-based commission calculation, product-specific rates, volume-based tiers, time-limited promotions, and performance bonuses.

---

## Current State ✅

### Implemented Features

1. **CommissionCalculator Service**
   - Basic percentage calculations
   - Flat fee support
   - Tiered commission support (static tiers)
   - Minor unit math (cents-based)

2. **CommissionType Enum**
   ```php
   enum CommissionType: string
   {
       case Percentage = 'percentage';
       case Flat = 'flat';
       case Tiered = 'tiered';
   }
   ```

3. **Configuration**
   ```php
   'commissions' => [
       'default_type' => 'percentage',
       'default_rate' => 10.00,
       'minimum_minor' => 0,
       'maximum_minor' => null,
   ],
   ```

4. **Affiliate Model Support**
   - `commission_rate` column (nullable override)
   - `commission_type` column
   - Falls back to config defaults

### Limitations (To Be Addressed)

- No rule engine for conditional commission logic
- No product/category-specific commission rates
- No time-based promotional rates
- No volume tier automation (milestone detection)
- No performance bonuses
- No recurring commission structures
- No commission locks/caps per affiliate
- No bonus triggers
- No commission stacking/multipliers

---

## Commission Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                 COMMISSION ENGINE                            │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Order Event ──► Rule Engine ──► Commission Calculation     │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              RULE PRIORITY                           │    │
│  │                                                      │    │
│  │  1. Promotion Rules    (time-limited bonuses)       │    │
│  │  2. Product Rules      (per-product rates)          │    │
│  │  3. Category Rules     (per-category rates)         │    │
│  │  4. Volume Rules       (tier thresholds)            │    │
│  │  5. Affiliate Override (custom rate)                │    │
│  │  6. Program Default    (base rate)                  │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              MODIFIERS                               │    │
│  │                                                      │    │
│  │  • Performance Bonuses  (+X% for top performers)    │    │
│  │  • Loyalty Multipliers  (tenure-based)              │    │
│  │  • First Purchase Bonus (new customer bonus)        │    │
│  │  • Volume Bonuses       (monthly thresholds)        │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Enhanced CommissionCalculator

```php
class CommissionCalculator
{
    public function __construct(
        private CommissionRuleEngine $ruleEngine,
        private CommissionModifierStack $modifiers,
    ) {}
    
    /**
     * Calculate commission for a conversion
     */
    public function calculate(
        Affiliate $affiliate,
        int $orderAmountMinor,
        array $context = []
    ): CommissionResult {
        // Get applicable rules (ordered by priority)
        $rules = $this->ruleEngine->getApplicableRules($affiliate, $context);
        
        // Base commission from highest priority rule
        $baseCommission = $this->applyRules($rules, $orderAmountMinor, $context);
        
        // Apply modifiers (bonuses, multipliers)
        $modifiedCommission = $this->modifiers->apply(
            $baseCommission,
            $affiliate,
            $context
        );
        
        // Apply caps
        $finalCommission = $this->applyCaps($modifiedCommission, $affiliate);
        
        return new CommissionResult(
            baseAmountMinor: $baseCommission,
            finalAmountMinor: $finalCommission,
            appliedRules: $rules,
            appliedModifiers: $this->modifiers->getApplied(),
        );
    }
}
```

---

## Commission Rules

### CommissionRule Model

```php
class CommissionRule extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'name',
        'type',
        'priority',
        'conditions',
        'commission_type',
        'commission_value',
        'starts_at',
        'ends_at',
        'is_active',
    ];
    
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'priority' => 'integer',
            'commission_value' => 'decimal:4',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
```

### Rule Types

```php
enum CommissionRuleType: string
{
    case Product = 'product';           // Specific product(s)
    case Category = 'category';         // Product category
    case Affiliate = 'affiliate';       // Specific affiliate(s)
    case AffiliateGroup = 'group';      // Affiliate tier/group
    case VolumeTier = 'volume';         // Sales volume threshold
    case Promotion = 'promotion';       // Time-limited promo
    case FirstPurchase = 'first';       // New customer bonus
    case Recurring = 'recurring';       // Subscription renewals
}
```

---

## Volume-Based Tiers

### VolumeTierService

```php
class VolumeTierService
{
    /**
     * Check and upgrade affiliate tiers
     */
    public function checkTierUpgrades(): int
    {
        $upgraded = 0;
        
        $tiers = config('affiliates.volume_tiers', [
            ['name' => 'Bronze', 'threshold' => 0, 'rate' => 10],
            ['name' => 'Silver', 'threshold' => 1000_00, 'rate' => 12],
            ['name' => 'Gold', 'threshold' => 5000_00, 'rate' => 15],
            ['name' => 'Platinum', 'threshold' => 20000_00, 'rate' => 20],
        ]);
        
        Affiliate::query()
            ->with('conversions')
            ->chunk(100, function ($affiliates) use ($tiers, &$upgraded) {
                foreach ($affiliates as $affiliate) {
                    $lifetimeValue = $affiliate->conversions
                        ->where('status', ConversionStatus::Completed)
                        ->sum('order_amount_minor');
                    
                    $newTier = $this->determineTier($lifetimeValue, $tiers);
                    
                    if ($newTier !== $affiliate->tier) {
                        $affiliate->update(['tier' => $newTier]);
                        event(new AffiliateTierUpgraded($affiliate, $newTier));
                        $upgraded++;
                    }
                }
            });
        
        return $upgraded;
    }
}
```

---

## Promotional Campaigns

### PromotionalCommission Model

```php
class PromotionalCommission extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'name',
        'description',
        'bonus_type',
        'bonus_value',
        'conditions',
        'starts_at',
        'ends_at',
        'max_uses',
        'current_uses',
        'affiliate_ids', // null = all affiliates
    ];
    
    /**
     * Check if promotion applies to conversion
     */
    public function appliesTo(Affiliate $affiliate, array $context): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        
        if ($this->affiliate_ids && !in_array($affiliate->id, $this->affiliate_ids)) {
            return false;
        }
        
        return $this->evaluateConditions($context);
    }
}
```

---

## Database Schema (Proposed Additions)

```php
// commission_rules table
Schema::create('affiliate_commission_rules', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('type');
    $table->integer('priority')->default(0);
    $table->json('conditions');
    $table->string('commission_type');
    $table->decimal('commission_value', 10, 4);
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->index(['type', 'is_active', 'priority']);
});

// promotional_commissions table
Schema::create('affiliate_promotional_commissions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('bonus_type');
    $table->decimal('bonus_value', 10, 4);
    $table->json('conditions')->nullable();
    $table->timestamp('starts_at');
    $table->timestamp('ends_at');
    $table->integer('max_uses')->nullable();
    $table->integer('current_uses')->default(0);
    $table->json('affiliate_ids')->nullable();
    $table->timestamps();
    
    $table->index(['starts_at', 'ends_at']);
});

// affiliate_tiers table
Schema::create('affiliate_tiers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->bigInteger('threshold_minor');
    $table->decimal('commission_rate', 5, 2);
    $table->json('benefits')->nullable();
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

---

## Configuration

```php
// config/affiliates.php

'commissions' => [
    'default_type' => CommissionType::Percentage,
    'default_rate' => 10.00,
    'minimum_minor' => 0,
    'maximum_minor' => null,
    
    'volume_tiers' => [
        ['name' => 'Bronze', 'threshold' => 0, 'rate' => 10.00],
        ['name' => 'Silver', 'threshold' => 1000_00, 'rate' => 12.00],
        ['name' => 'Gold', 'threshold' => 5000_00, 'rate' => 15.00],
        ['name' => 'Platinum', 'threshold' => 20000_00, 'rate' => 20.00],
    ],
    
    'first_purchase_bonus' => [
        'enabled' => true,
        'type' => 'percentage',
        'value' => 5.00, // +5% for first-time customers
    ],
    
    'recurring' => [
        'enabled' => true,
        'rate' => 5.00, // 5% for subscription renewals
        'duration_months' => 12, // Pay for 12 months
    ],
],
```

---

## Implementation Priority

| Phase | Deliverable | Effort | Status |
|-------|-------------|--------|--------|
| 1 | CommissionType enum | 0.5 day | ✅ Done |
| 2 | Basic CommissionCalculator | 1 day | ✅ Done |
| 3 | Tiered calculation support | 0.5 day | ✅ Done |
| 4 | Affiliate-level override | 0.5 day | ✅ Done |
| 5 | CommissionRule model | 1 day | ⬜ Todo |
| 6 | CommissionRuleEngine | 2 days | ⬜ Todo |
| 7 | Product/category rules | 1 day | ⬜ Todo |
| 8 | VolumeTierService | 1 day | ⬜ Todo |
| 9 | AffiliateTier model | 1 day | ⬜ Todo |
| 10 | PromotionalCommission model | 1 day | ⬜ Todo |
| 11 | Time-limited promotions | 1 day | ⬜ Todo |
| 12 | Commission modifiers | 1 day | ⬜ Todo |
| 13 | First purchase bonus | 0.5 day | ⬜ Todo |
| 14 | Recurring commissions | 1 day | ⬜ Todo |
| 15 | Filament rule management | 2 days | ⬜ Todo |
| 16 | Tier upgrade commands | 1 day | ⬜ Todo |

**Remaining Effort:** ~2-3 weeks

---

## Navigation

**Previous:** [07-payout-automation.md](07-payout-automation.md)  
**Next:** [09-database-evolution.md](09-database-evolution.md)

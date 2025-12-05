# Stacking Policies

> **Document:** 06-stacking-policies.md  
> **Status:** Vision  
> **Priority:** P0 (Critical)

---

## Overview

Stacking policies determine **how multiple vouchers interact** when applied to a single cart. This is a critical feature that directly impacts revenue protection and promotional strategy.

---

## Current State

The vouchers package has basic stacking configuration:

```php
// config/vouchers.php
'cart' => [
    'max_vouchers_per_cart' => 1,
    'replace_when_max_reached' => true,
    'allow_stacking' => false,
],
```

This is a binary approach: either 1 voucher or multiple. No rules for **how** vouchers stack.

---

## Vision: Intelligent Stacking Engine

### 6.1 Stacking Modes

```php
enum StackingMode: string
{
    case None = 'none';           // Only one voucher allowed
    case Sequential = 'sequential'; // Apply in order, each to remaining total
    case Parallel = 'parallel';     // Apply all to original total
    case BestDeal = 'best_deal';    // Automatically select best combination
    case Custom = 'custom';         // Policy-defined rules
}
```

### 6.2 Stacking Behavior Comparison

| Mode | Voucher A (20%) | Voucher B (RM30) | On RM200 Cart |
|------|-----------------|------------------|---------------|
| None | -RM40 | N/A | RM160 |
| Sequential | -RM40 first | -RM30 on RM160 | RM130 |
| Parallel | -RM40 | -RM30 | RM130 |
| Best Deal | Auto-selects | Best combination | RM130 |

---

## Stacking Policy Architecture

### Policy Interface

```php
interface StackingPolicyInterface
{
    /**
     * Check if a voucher can be added given existing vouchers
     */
    public function canAdd(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart
    ): StackingDecision;
    
    /**
     * Resolve conflicts when max vouchers exceeded
     */
    public function resolveConflict(
        Collection $vouchers,
        Cart $cart
    ): Collection;
    
    /**
     * Calculate optimal application order
     */
    public function getApplicationOrder(
        Collection $vouchers,
        Cart $cart
    ): Collection;
    
    /**
     * Get the stacking mode
     */
    public function getMode(): StackingMode;
}
```

### Stacking Decision

```php
class StackingDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly ?VoucherCondition $conflictsWith = null,
        public readonly ?VoucherCondition $suggestedReplacement = null,
    ) {}
    
    public static function allow(): self
    {
        return new self(allowed: true);
    }
    
    public static function deny(string $reason, ?VoucherCondition $conflictsWith = null): self
    {
        return new self(
            allowed: false,
            reason: $reason,
            conflictsWith: $conflictsWith
        );
    }
}
```

---

## Stacking Rules

### 6.3 Rule Types

```php
enum StackingRuleType: string
{
    case MaxVouchers = 'max_vouchers';
    case MaxDiscount = 'max_discount';
    case MaxDiscountPercentage = 'max_discount_percentage';
    case MutualExclusion = 'mutual_exclusion';
    case TypeRestriction = 'type_restriction';
    case CategoryExclusion = 'category_exclusion';
    case CampaignExclusion = 'campaign_exclusion';
    case ValueThreshold = 'value_threshold';
}
```

### Rule Configuration

```php
class StackingPolicy implements StackingPolicyInterface
{
    public function __construct(
        private StackingMode $mode = StackingMode::Sequential,
        private array $rules = []
    ) {}
    
    public static function default(): self
    {
        return new self(
            mode: StackingMode::Sequential,
            rules: [
                ['type' => 'max_vouchers', 'value' => 3],
                ['type' => 'max_discount_percentage', 'value' => 50],
                ['type' => 'mutual_exclusion', 'groups' => ['flash_sale', 'clearance']],
                ['type' => 'type_restriction', 'max_per_type' => [
                    'percentage' => 1,
                    'fixed' => 2,
                    'free_shipping' => 1,
                ]],
            ]
        );
    }
}
```

---

## Rule Implementations

### Max Vouchers Rule

```php
class MaxVouchersRule implements StackingRuleInterface
{
    public function evaluate(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart,
        array $config
    ): StackingDecision {
        $max = $config['value'] ?? 1;
        
        if ($existingVouchers->count() >= $max) {
            return StackingDecision::deny(
                reason: "Maximum of {$max} vouchers allowed per cart",
                conflictsWith: $existingVouchers->first()
            );
        }
        
        return StackingDecision::allow();
    }
}
```

### Mutual Exclusion Rule

```php
class MutualExclusionRule implements StackingRuleInterface
{
    public function evaluate(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart,
        array $config
    ): StackingDecision {
        $exclusionGroups = $config['groups'] ?? [];
        $newVoucherGroups = $newVoucher->getVoucher()->exclusion_groups ?? [];
        
        foreach ($existingVouchers as $existing) {
            $existingGroups = $existing->getVoucher()->exclusion_groups ?? [];
            
            $conflict = array_intersect(
                array_intersect($newVoucherGroups, $exclusionGroups),
                array_intersect($existingGroups, $exclusionGroups)
            );
            
            if (!empty($conflict)) {
                return StackingDecision::deny(
                    reason: "Cannot combine vouchers from same group: " . implode(', ', $conflict),
                    conflictsWith: $existing
                );
            }
        }
        
        return StackingDecision::allow();
    }
}
```

### Max Discount Rule

```php
class MaxDiscountRule implements StackingRuleInterface
{
    public function evaluate(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart,
        array $config
    ): StackingDecision {
        $maxDiscount = $config['value'] ?? null;
        
        if ($maxDiscount === null) {
            return StackingDecision::allow();
        }
        
        $currentDiscount = $existingVouchers->sum(
            fn ($v) => abs($v->getCalculatedValue($cart->getRawSubtotalWithoutConditions()))
        );
        
        $newDiscount = abs($newVoucher->getCalculatedValue(
            $cart->getRawSubtotalWithoutConditions()
        ));
        
        if ($currentDiscount + $newDiscount > $maxDiscount) {
            return StackingDecision::deny(
                reason: "Total discount would exceed maximum of " . 
                    Money::MYR($maxDiscount)->format()
            );
        }
        
        return StackingDecision::allow();
    }
}
```

---

## Stacking Engine

```php
class StackingEngine
{
    /** @var array<StackingRuleInterface> */
    private array $rules = [];
    
    public function __construct(
        private StackingPolicy $policy
    ) {
        $this->registerDefaultRules();
    }
    
    public function canAdd(
        VoucherCondition $voucher,
        Collection $existing,
        Cart $cart
    ): StackingDecision {
        foreach ($this->policy->getRules() as $ruleConfig) {
            $rule = $this->getRule($ruleConfig['type']);
            
            $decision = $rule->evaluate($voucher, $existing, $cart, $ruleConfig);
            
            if (!$decision->allowed) {
                return $decision;
            }
        }
        
        return StackingDecision::allow();
    }
    
    public function getBestCombination(
        Collection $available,
        Cart $cart,
        int $maxVouchers = 3
    ): Collection {
        if ($available->count() <= $maxVouchers) {
            return $this->validateCombination($available, $cart);
        }
        
        // Generate all valid combinations
        $combinations = $this->generateCombinations($available, $maxVouchers);
        
        // Calculate discount for each combination
        $scored = $combinations->map(function ($combo) use ($cart) {
            $discount = $this->calculateCombinationDiscount($combo, $cart);
            return ['vouchers' => $combo, 'discount' => $discount];
        });
        
        // Return combination with highest discount
        return $scored->sortByDesc('discount')->first()['vouchers'];
    }
    
    private function calculateCombinationDiscount(Collection $vouchers, Cart $cart): int
    {
        $total = $cart->getRawSubtotalWithoutConditions();
        
        if ($this->policy->getMode() === StackingMode::Sequential) {
            foreach ($vouchers as $voucher) {
                $total += $voucher->getCalculatedValue($total);
            }
            return $cart->getRawSubtotalWithoutConditions() - $total;
        }
        
        // Parallel mode
        $discount = 0;
        $base = $cart->getRawSubtotalWithoutConditions();
        
        foreach ($vouchers as $voucher) {
            $discount += abs($voucher->getCalculatedValue($base));
        }
        
        return $discount;
    }
}
```

---

## Voucher-Level Stacking Configuration

### Database Column

```php
Schema::table('vouchers', function (Blueprint $table) {
    $table->jsonb('stacking_rules')->nullable();
    $table->jsonb('exclusion_groups')->nullable();
    $table->integer('stacking_priority')->default(100);
});
```

### Voucher Model

```php
class Voucher extends Model
{
    protected $casts = [
        'stacking_rules' => 'array',
        'exclusion_groups' => 'array',
    ];
    
    public function canStackWith(Voucher $other): bool
    {
        // Check mutual exclusion groups
        $myGroups = $this->exclusion_groups ?? [];
        $otherGroups = $other->exclusion_groups ?? [];
        
        return empty(array_intersect($myGroups, $otherGroups));
    }
    
    public function getStackingPriority(): int
    {
        return $this->stacking_priority;
    }
}
```

---

## Cart Integration

### Enhanced InteractsWithVouchers Trait

```php
trait InteractsWithVouchers
{
    protected ?StackingPolicy $stackingPolicy = null;
    
    public function setStackingPolicy(StackingPolicy $policy): static
    {
        $this->stackingPolicy = $policy;
        return $this;
    }
    
    public function applyVoucher(string $code, int $order = 100): static
    {
        $voucher = Voucher::findByCode($code);
        $condition = new VoucherCondition($voucher->toData(), $order);
        
        // Check stacking policy
        $existing = $this->getAppliedVouchers();
        $engine = new StackingEngine($this->getStackingPolicy());
        
        $decision = $engine->canAdd($condition, $existing, $this);
        
        if (!$decision->allowed) {
            if ($this->shouldAutoReplace() && $decision->conflictsWith) {
                $this->removeVoucher($decision->conflictsWith->getVoucherCode());
            } else {
                throw new VoucherStackingException($decision->reason);
            }
        }
        
        $this->addCondition($condition->toCartCondition());
        
        return $this;
    }
    
    public function optimizeVouchers(): static
    {
        $available = $this->getAppliedVouchers();
        $engine = new StackingEngine($this->getStackingPolicy());
        
        $optimal = $engine->getBestCombination($available, $this);
        
        // Remove non-optimal vouchers
        $toRemove = $available->diff($optimal);
        foreach ($toRemove as $voucher) {
            $this->removeVoucher($voucher->getVoucherCode());
        }
        
        return $this;
    }
}
```

---

## Configuration

```php
// config/vouchers.php
'stacking' => [
    'mode' => env('VOUCHERS_STACKING_MODE', 'sequential'),
    
    'rules' => [
        [
            'type' => 'max_vouchers',
            'value' => (int) env('VOUCHERS_MAX_PER_CART', 3),
        ],
        [
            'type' => 'max_discount_percentage',
            'value' => (int) env('VOUCHERS_MAX_DISCOUNT_PCT', 50),
        ],
        [
            'type' => 'type_restriction',
            'max_per_type' => [
                'percentage' => 1,
                'fixed' => 2,
                'free_shipping' => 1,
            ],
        ],
    ],
    
    'auto_optimize' => env('VOUCHERS_AUTO_OPTIMIZE', false),
    'auto_replace' => env('VOUCHERS_AUTO_REPLACE', true),
],
```

---

## Implementation Phases

### Phase 1: Core Stacking Engine
- [ ] `StackingPolicyInterface`
- [ ] `StackingDecision` value object
- [ ] `StackingEngine` orchestrator
- [ ] Basic rules (max_vouchers, max_discount)

### Phase 2: Advanced Rules
- [ ] Mutual exclusion groups
- [ ] Type restrictions
- [ ] Campaign exclusions
- [ ] Priority ordering

### Phase 3: Optimization
- [ ] Best deal calculation
- [ ] Combination scoring
- [ ] Auto-optimization

### Phase 4: Cart Integration
- [ ] Enhanced `InteractsWithVouchers` trait
- [ ] Policy injection
- [ ] Conflict resolution UX

### Phase 5: Filament UI
- [ ] Stacking rule builder
- [ ] Exclusion group manager
- [ ] Conflict visualization

---

## Navigation

**Previous:** [05-campaign-management.md](05-campaign-management.md)  
**Next:** [07-ai-optimization.md](07-ai-optimization.md)

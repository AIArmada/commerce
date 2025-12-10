# Price Rules

> **Document:** 04 of 06  
> **Package:** `aiarmada/pricing`  
> **Status:** Vision

---

## Overview

Price Rules enable conditional pricing modifications based on customer attributes, cart contents, time, and other factors. They're the engine behind flash sales, member pricing, and promotional campaigns.

---

## Rule Structure

```php
namespace AIArmada\Pricing\Models;

class PriceRule extends Model
{
    protected $fillable = [
        'name',
        'description',
        'priority',             // Higher = evaluated first
        'conditions',           // JSON conditions
        'actions',              // JSON actions
        'is_active',
        'is_stackable',         // Can combine with other rules?
        'usage_limit',          // Max total uses
        'usage_count',          // Current usage
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'is_stackable' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function isApplicable(PriceContext $context): bool
    {
        if (!$this->isActive()) return false;
        if ($this->isExhausted()) return false;
        
        return $this->evaluateConditions($context);
    }
}
```

---

## Condition Types

| Condition | Description | Example |
|-----------|-------------|---------|
| `customer_segment` | Customer belongs to segment | VIP Members |
| `customer_group` | Customer belongs to group | Wholesale |
| `cart_subtotal` | Cart value threshold | > RM 500 |
| `cart_quantity` | Total items in cart | >= 10 items |
| `product_category` | Product in category | Electronics |
| `product_tag` | Product has tag | sale, clearance |
| `day_of_week` | Specific days | Friday, Saturday |
| `time_of_day` | Time range | 18:00-22:00 |
| `first_order` | Customer's first order | true |

---

## Action Types

| Action | Description | Example |
|--------|-------------|---------|
| `percent_discount` | Percentage off | 15% off |
| `fixed_discount` | Fixed amount off | RM 10 off |
| `fixed_price` | Set specific price | RM 99 flat |
| `buy_x_get_y` | Buy X get Y free/discounted | Buy 2 get 1 free |

---

## Rule Evaluation

```php
namespace AIArmada\Pricing\Services;

class PriceRuleEngine
{
    public function evaluate(Priceable $item, PriceContext $context): Collection
    {
        $applicableRules = PriceRule::query()
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get()
            ->filter(fn ($rule) => $rule->isApplicable($context));

        $modifiers = collect();
        $stackedRuleApplied = false;

        foreach ($applicableRules as $rule) {
            // If a non-stackable rule was applied, skip further rules
            if ($stackedRuleApplied && !$rule->is_stackable) {
                continue;
            }

            if ($this->ruleAppliesToItem($rule, $item)) {
                $modifiers->push($this->createModifier($rule, $item));
                
                if (!$rule->is_stackable) {
                    break; // Stop after first non-stackable rule
                }
            }
        }

        return $modifiers;
    }

    protected function createModifier(PriceRule $rule, Priceable $item): PriceModifier
    {
        $action = $rule->actions[0];

        return match ($action['type']) {
            'percent_discount' => new PercentDiscountModifier(
                rule: $rule,
                percent: $action['value']
            ),
            'fixed_discount' => new FixedDiscountModifier(
                rule: $rule,
                amount: $action['value']
            ),
            'fixed_price' => new FixedPriceModifier(
                rule: $rule,
                price: $action['value']
            ),
        };
    }
}
```

---

## Example Rule Configurations

### Flash Sale
```json
{
    "name": "12.12 Flash Sale",
    "conditions": [
        {"type": "product_tag", "operator": "in", "value": ["flash-sale"]}
    ],
    "actions": [
        {"type": "percent_discount", "value": 30}
    ],
    "starts_at": "2024-12-12 00:00:00",
    "ends_at": "2024-12-12 23:59:59",
    "is_stackable": false
}
```

### VIP Member Pricing
```json
{
    "name": "VIP Member Discount",
    "conditions": [
        {"type": "customer_segment", "operator": "in", "value": ["vip"]}
    ],
    "actions": [
        {"type": "percent_discount", "value": 10}
    ],
    "is_stackable": true
}
```

### Happy Hour
```json
{
    "name": "Happy Hour Discount",
    "conditions": [
        {"type": "time_of_day", "operator": "between", "value": ["18:00", "21:00"]},
        {"type": "day_of_week", "operator": "in", "value": ["friday", "saturday"]}
    ],
    "actions": [
        {"type": "percent_discount", "value": 15}
    ],
    "is_stackable": false
}
```

---

## Navigation

**Previous:** [03-tiered-pricing.md](03-tiered-pricing.md)  
**Next:** [05-database-schema.md](05-database-schema.md)

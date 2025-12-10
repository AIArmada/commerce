# Segments & Groups

> **Document:** 04 of 06  
> **Package:** `aiarmada/customers`  
> **Status:** Vision

---

## Overview

This document details customer segmentation and grouping, enabling targeted marketing, pricing rules, and personalized experiences.

---

## Segment Types

| Type | Description | Example |
|------|-------------|---------|
| **Manual** | Hand-picked customers | VIP List, Beta Testers |
| **Automatic** | Rule-based, auto-updated | High Spenders, Recent Buyers |
| **Time-Based** | Time-sensitive rules | Dormant 90+ Days, New This Month |

---

## Segment Model

```php
namespace AIArmada\Customers\Models;

class Segment extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',                 // SegmentType enum
        'conditions',           // JSON conditions for automatic
        'match_type',           // 'all' or 'any'
        'is_active',
        'priority',             // For overlapping segments
        'cached_count',         // Customer count cache
        'last_synced_at',
    ];

    protected $casts = [
        'type' => SegmentType::class,
        'conditions' => 'array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    // Relationships
    public function customers(): BelongsToMany;

    // Helpers
    public function isAutomatic(): bool
    {
        return $this->type === SegmentType::Automatic;
    }

    public function sync(): int
    {
        if (!$this->isAutomatic()) {
            return $this->customers()->count();
        }

        $matchingIds = $this->getMatchingCustomerIds();
        $this->customers()->sync($matchingIds);
        
        $this->update([
            'cached_count' => count($matchingIds),
            'last_synced_at' => now(),
        ]);

        return count($matchingIds);
    }

    protected function getMatchingCustomerIds(): array
    {
        return app(SegmentMatcher::class)
            ->match($this->conditions, $this->match_type)
            ->pluck('id')
            ->toArray();
    }
}
```

---

## Segment Conditions

```php
namespace AIArmada\Customers\Services;

class SegmentMatcher
{
    public function match(array $conditions, string $matchType): Builder
    {
        $query = Customer::query();

        foreach ($conditions as $condition) {
            $method = $matchType === 'all' ? 'where' : 'orWhere';
            $query->{$method}(function ($q) use ($condition) {
                $this->applyCondition($q, $condition);
            });
        }

        return $query;
    }

    protected function applyCondition(Builder $query, array $condition): void
    {
        match ($condition['field']) {
            // Spending conditions
            'total_spent' => $this->applyNumericCondition($query, 'total_spent', $condition),
            'orders_count' => $this->applyNumericCondition($query, 'orders_count', $condition),
            'average_order_value' => $this->applyAovCondition($query, $condition),
            
            // Time conditions
            'last_order_at' => $this->applyDateCondition($query, 'last_order_at', $condition),
            'created_at' => $this->applyDateCondition($query, 'created_at', $condition),
            'days_since_last_order' => $this->applyDaysSinceCondition($query, $condition),
            
            // Boolean conditions
            'accepts_marketing' => $query->where('accepts_marketing', $condition['value']),
            'has_account' => $condition['value'] 
                ? $query->whereNotNull('user_id') 
                : $query->whereNull('user_id'),
            
            // Location conditions
            'country' => $this->applyCountryCondition($query, $condition),
            'state' => $this->applyStateCondition($query, $condition),
        };
    }

    protected function applyNumericCondition(Builder $query, string $field, array $condition): void
    {
        $query->where($field, $condition['operator'], $condition['value']);
    }

    protected function applyDaysSinceCondition(Builder $query, array $condition): void
    {
        $date = now()->subDays($condition['value']);
        $operator = $this->invertOperator($condition['operator']);
        $query->where('last_order_at', $operator, $date);
    }
}
```

---

## Predefined Segments

```php
// Common segment templates
return [
    'vip' => [
        'name' => 'VIP Customers',
        'conditions' => [
            ['field' => 'total_spent', 'operator' => '>=', 'value' => 500000], // RM 5000
        ],
    ],
    'frequent_buyers' => [
        'name' => 'Frequent Buyers',
        'conditions' => [
            ['field' => 'orders_count', 'operator' => '>=', 'value' => 5],
        ],
    ],
    'at_risk' => [
        'name' => 'At Risk (Dormant)',
        'conditions' => [
            ['field' => 'days_since_last_order', 'operator' => '>=', 'value' => 90],
            ['field' => 'orders_count', 'operator' => '>=', 'value' => 1],
        ],
    ],
    'new_customers' => [
        'name' => 'New Customers (30 Days)',
        'conditions' => [
            ['field' => 'created_at', 'operator' => '>=', 'value' => 'now - 30 days'],
        ],
    ],
];
```

---

## Customer Groups

Groups are manual customer classifications for business logic:

```php
namespace AIArmada\Customers\Models;

class CustomerGroup extends Model
{
    protected $fillable = [
        'name',
        'code',                 // 'wholesale', 'retail', 'b2b'
        'is_default',
        'discount_percent',     // Default discount for group
    ];

    public function customers(): BelongsToMany;
    public function priceLists(): HasMany;
}
```

Use cases:
- **B2B/Wholesale** - Different pricing
- **Retail** - Default group
- **Staff** - Employee discounts
- **Resellers** - Volume pricing

---

## Navigation

**Previous:** [03-address-management.md](03-address-management.md)  
**Next:** [05-database-schema.md](05-database-schema.md)

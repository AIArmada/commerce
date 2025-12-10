# Price Lists

> **Document:** 02 of 06  
> **Package:** `aiarmada/pricing`  
> **Status:** Vision

---

## Overview

Price Lists are named collections of prices that can be assigned to customer segments, channels, or regions. They enable wholesale pricing, VIP pricing, and regional price variations.

---

## Price List Model

```php
namespace AIArmada\Pricing\Models;

class PriceList extends Model
{
    protected $fillable = [
        'name',
        'code',                 // 'wholesale', 'vip', 'euro-zone'
        'description',
        'currency',
        'priority',             // Higher = takes precedence
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    // Relationships
    public function prices(): HasMany;
    public function segments(): BelongsToMany;
    public function customerGroups(): BelongsToMany;

    // Scopes
    public function scopeActive($query);
    public function scopeForCurrency($query, string $currency);

    // Helpers
    public function isActive(): bool
    {
        if (!$this->is_active) return false;
        if ($this->starts_at && now()->lt($this->starts_at)) return false;
        if ($this->ends_at && now()->gt($this->ends_at)) return false;
        return true;
    }
}
```

---

## Price Entry Model

Individual price entries within a price list:

```php
namespace AIArmada\Pricing\Models;

class Price extends Model
{
    protected $fillable = [
        'price_list_id',
        'priceable_type',       // Product, Variant
        'priceable_id',
        'price',                // The price in cents
        'compare_at_price',     // Original price for strikethrough
        'min_quantity',         // Minimum qty for this price
    ];

    protected $casts = [
        'price' => 'integer',
        'compare_at_price' => 'integer',
        'min_quantity' => 'integer',
    ];

    // Relationships
    public function priceList(): BelongsTo;
    public function priceable(): MorphTo;
}
```

---

## Price Resolution

```php
namespace AIArmada\Pricing\Services;

class PriceResolver
{
    public function resolve(
        Priceable $item,
        ?Customer $customer = null,
        int $quantity = 1
    ): ResolvedPrice {
        // Get applicable price lists (ordered by priority)
        $priceLists = $this->getApplicablePriceLists($customer);

        foreach ($priceLists as $priceList) {
            $price = $priceList->prices()
                ->where('priceable_type', get_class($item))
                ->where('priceable_id', $item->id)
                ->where('min_quantity', '<=', $quantity)
                ->orderBy('min_quantity', 'desc')
                ->first();

            if ($price) {
                return new ResolvedPrice(
                    price: $price->price,
                    compareAtPrice: $price->compare_at_price,
                    priceList: $priceList,
                    quantity: $quantity
                );
            }
        }

        // Fall back to product base price
        return new ResolvedPrice(
            price: $item->price,
            compareAtPrice: $item->compare_at_price,
            priceList: null,
            quantity: $quantity
        );
    }

    protected function getApplicablePriceLists(?Customer $customer): Collection
    {
        $query = PriceList::active()->orderBy('priority', 'desc');

        if ($customer) {
            $query->where(function ($q) use ($customer) {
                $q->whereDoesntHave('segments') // No segment restriction
                  ->orWhereHas('segments', fn ($sq) => 
                      $sq->whereIn('id', $customer->segments->pluck('id'))
                  );
            });
        }

        return $query->get();
    }
}
```

---

## Price List Assignment

```
┌────────────────────────────────────────────────────────────────┐
│ PRICE LIST: Wholesale                                          │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Assigned to:                                                    │
│ • Customer Group: Wholesale                                     │
│ • Segment: B2B Customers                                        │
│                                                                 │
│ Products:                                                       │
│ ├── Premium T-Shirt         RM 45.00  (retail: RM 59.00)       │
│ ├── Designer Jeans          RM 120.00 (retail: RM 159.00)      │
│ └── Leather Wallet          RM 85.00  (retail: RM 119.00)      │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-tiered-pricing.md](03-tiered-pricing.md)

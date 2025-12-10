# Tiered Pricing

> **Document:** 03 of 06  
> **Package:** `aiarmada/pricing`  
> **Status:** Vision

---

## Overview

Tiered pricing allows different prices based on quantity purchased. Common in wholesale and B2B scenarios.

---

## Tier Structure

```
┌────────────────────────────────────────────────────────────────┐
│ PRODUCT: Premium Coffee Beans (1kg)                            │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ TIER        │ QUANTITY │ PRICE/UNIT │ SAVINGS                  │
│ ────────────┼──────────┼────────────┼──────────────────────────│
│ Retail      │   1-4    │  RM 45.00  │   —                      │
│ ────────────┼──────────┼────────────┼──────────────────────────│
│ Small Bulk  │   5-9    │  RM 42.00  │  ~7%                     │
│ ────────────┼──────────┼────────────┼──────────────────────────│
│ Medium Bulk │  10-24   │  RM 38.00  │  ~16%                    │
│ ────────────┼──────────┼────────────┼──────────────────────────│
│ Large Bulk  │   25+    │  RM 35.00  │  ~22%                    │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

---

## Price Tier Model

```php
namespace AIArmada\Pricing\Models;

class PriceTier extends Model
{
    protected $fillable = [
        'priceable_type',
        'priceable_id',
        'price_list_id',        // Optional: tier specific to price list
        'min_quantity',
        'max_quantity',         // NULL = unlimited
        'price',                // Fixed price per unit
        'discount_type',        // 'fixed', 'percent'
        'discount_value',       // Alternative: discount from base
    ];

    protected $casts = [
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'price' => 'integer',
        'discount_value' => 'decimal:2',
    ];

    // Relationships
    public function priceable(): MorphTo;
    public function priceList(): BelongsTo;

    // Get effective price
    public function getEffectivePrice(Priceable $item): int
    {
        if ($this->price) {
            return $this->price;
        }

        // Calculate from discount
        $basePrice = $item->price;
        
        return match ($this->discount_type) {
            'fixed' => $basePrice - $this->discount_value,
            'percent' => (int) ($basePrice * (1 - $this->discount_value / 100)),
            default => $basePrice,
        };
    }
}
```

---

## Tiered Price Calculation

```php
namespace AIArmada\Pricing\Services;

class TieredPriceCalculator
{
    public function calculate(Priceable $item, int $quantity, ?PriceList $priceList = null): TieredPriceResult
    {
        $tier = $this->findApplicableTier($item, $quantity, $priceList);

        if (!$tier) {
            return new TieredPriceResult(
                unitPrice: $item->price,
                quantity: $quantity,
                totalPrice: $item->price * $quantity,
                tier: null,
                savingsPercent: 0
            );
        }

        $unitPrice = $tier->getEffectivePrice($item);
        $basePrice = $item->price;
        $savingsPercent = round((1 - $unitPrice / $basePrice) * 100, 1);

        return new TieredPriceResult(
            unitPrice: $unitPrice,
            quantity: $quantity,
            totalPrice: $unitPrice * $quantity,
            tier: $tier,
            savingsPercent: $savingsPercent
        );
    }

    protected function findApplicableTier(Priceable $item, int $quantity, ?PriceList $priceList): ?PriceTier
    {
        return PriceTier::query()
            ->where('priceable_type', get_class($item))
            ->where('priceable_id', $item->id)
            ->when($priceList, fn ($q) => $q->where('price_list_id', $priceList->id))
            ->where('min_quantity', '<=', $quantity)
            ->where(fn ($q) => $q
                ->whereNull('max_quantity')
                ->orWhere('max_quantity', '>=', $quantity)
            )
            ->orderBy('min_quantity', 'desc')
            ->first();
    }
}
```

---

## Frontend Display

```php
// In product page
@foreach($product->priceTiers as $tier)
    <div class="tier {{ $currentQuantity >= $tier->min_quantity ? 'active' : '' }}">
        <span class="quantity">
            {{ $tier->min_quantity }}{{ $tier->max_quantity ? '-' . $tier->max_quantity : '+' }}
        </span>
        <span class="price">{{ money($tier->getEffectivePrice($product)) }}/unit</span>
        <span class="savings text-green-600">Save {{ $tier->savingsPercent }}%</span>
    </div>
@endforeach
```

---

## Navigation

**Previous:** [02-price-lists.md](02-price-lists.md)  
**Next:** [04-price-rules.md](04-price-rules.md)

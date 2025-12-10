# Tax Rates

> **Document:** 03 of 06  
> **Package:** `aiarmada/tax`  
> **Status:** Vision

---

## Overview

Tax Rates define the actual percentages applied within a Tax Zone for each Tax Class.

---

## Tax Rate Model

```php
namespace AIArmada\Tax\Models;

class TaxRate extends Model
{
    protected $fillable = [
        'tax_zone_id',
        'tax_class_id',
        'name',                 // 'SST', 'GST', 'VAT'
        'rate',                 // Percentage (6.00 = 6%)
        'is_compound',          // Applied after other taxes?
        'is_shipping',          // Apply to shipping?
        'priority',             // Order of application
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'is_compound' => 'boolean',
        'is_shipping' => 'boolean',
    ];

    // Relationships
    public function zone(): BelongsTo;
    public function taxClass(): BelongsTo;

    // Calculate tax for amount
    public function calculate(int $amount): int
    {
        return (int) round($amount * $this->rate / 100);
    }
}
```

---

## Rate Examples

### Malaysia SST
```php
[
    'zone' => 'Malaysia',
    'rates' => [
        ['name' => 'SST Standard', 'class' => 'standard', 'rate' => 6.00],
        ['name' => 'SST Reduced', 'class' => 'reduced', 'rate' => 0.00],
        ['name' => 'SST Exempt', 'class' => 'exempt', 'rate' => 0.00],
    ]
]
```

### EU VAT (Germany)
```php
[
    'zone' => 'Germany',
    'rates' => [
        ['name' => 'MwSt Standard', 'class' => 'standard', 'rate' => 19.00],
        ['name' => 'MwSt Reduced', 'class' => 'reduced', 'rate' => 7.00],
        ['name' => 'MwSt Zero', 'class' => 'zero', 'rate' => 0.00],
    ]
]
```

### US Sales Tax (California)
```php
[
    'zone' => 'California',
    'rates' => [
        ['name' => 'CA State Tax', 'class' => 'standard', 'rate' => 7.25],
        // Note: US often has compound local taxes
    ]
]
```

---

## Compound Tax Calculation

```
Base Price:           RM 100.00
├── SST (6%):         RM   6.00
├── Local Tax (2%):   RM   2.00  (simple)
OR
├── Local Tax (2%):   RM   2.12  (compound, on 106.00)

Total:                RM 108.00  (simple)
OR
Total:                RM 108.12  (compound)
```

```php
class TaxCalculator
{
    public function calculate(int $amount, Collection $rates): TaxBreakdown
    {
        $subtotal = $amount;
        $taxes = collect();

        // First pass: non-compound taxes
        foreach ($rates->where('is_compound', false) as $rate) {
            $taxAmount = $rate->calculate($amount);
            $taxes->push(new TaxLine($rate, $taxAmount));
        }

        // Second pass: compound taxes (apply to subtotal + previous taxes)
        $compoundBase = $subtotal + $taxes->sum('amount');
        foreach ($rates->where('is_compound', true) as $rate) {
            $taxAmount = $rate->calculate($compoundBase);
            $taxes->push(new TaxLine($rate, $taxAmount));
        }

        return new TaxBreakdown(
            subtotal: $subtotal,
            taxTotal: $taxes->sum('amount'),
            taxes: $taxes,
            total: $subtotal + $taxes->sum('amount')
        );
    }
}
```

---

## Navigation

**Previous:** [02-tax-zones.md](02-tax-zones.md)  
**Next:** [04-tax-classes.md](04-tax-classes.md)

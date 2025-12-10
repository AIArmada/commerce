# Tax Classes

> **Document:** 04 of 06  
> **Package:** `aiarmada/tax`  
> **Status:** Vision

---

## Overview

Tax Classes categorize products for tax rate assignment. Different product types often have different tax treatment (essentials at 0%, luxury items at standard rate).

---

## Tax Class Model

```php
namespace AIArmada\Tax\Models;

class TaxClass extends Model
{
    protected $fillable = [
        'name',
        'code',                 // 'standard', 'reduced', 'zero', 'exempt'
        'description',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    // Relationships
    public function rates(): HasMany;

    // Get rate for a zone
    public function getRateForZone(TaxZone $zone): ?TaxRate
    {
        return $this->rates()
            ->where('tax_zone_id', $zone->id)
            ->first();
    }
}
```

---

## Standard Tax Classes

| Code | Description | Example Products |
|------|-------------|------------------|
| `standard` | Default rate | Most products |
| `reduced` | Lower rate | Essential foods, books |
| `zero` | 0% but trackable | Exports, some essentials |
| `exempt` | Not taxable | Financial services |
| `digital` | Digital goods | Software, e-books (varies by region) |

---

## Product Integration

```php
// In Product model
class Product extends Model
{
    protected $fillable = [
        'tax_class_id',
        // ...
    ];

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    public function getTaxRate(Address $address): ?TaxRate
    {
        $zone = app(TaxZoneResolver::class)->resolve($address);
        
        if (!$zone) {
            return null;
        }

        $taxClass = $this->taxClass ?? TaxClass::default();
        
        return $taxClass->getRateForZone($zone);
    }
}
```

---

## Taxable Interface

```php
namespace AIArmada\CommerceSupport\Contracts;

interface Taxable
{
    public function getTaxClass(): ?TaxClass;
    public function getTaxRate(Address $address): ?TaxRate;
    public function calculateTax(int $amount, Address $address): int;
}

// Implementation trait
trait IsTaxable
{
    public function getTaxClass(): ?TaxClass
    {
        return $this->taxClass;
    }

    public function getTaxRate(Address $address): ?TaxRate
    {
        $zone = app(TaxZoneResolver::class)->resolve($address);
        $taxClass = $this->getTaxClass() ?? TaxClass::default();
        
        return $zone ? $taxClass->getRateForZone($zone) : null;
    }

    public function calculateTax(int $amount, Address $address): int
    {
        $rate = $this->getTaxRate($address);
        return $rate ? $rate->calculate($amount) : 0;
    }
}
```

---

## Digital Goods Handling

Digital goods often have special tax treatment (EU MOSS, etc.):

```php
class DigitalTaxHandler
{
    public function getRate(Product $product, Address $address): ?TaxRate
    {
        if (!$product->is_digital) {
            return null;
        }

        // For digital goods, tax is based on customer location
        // not seller location (EU VAT MOSS rules)
        $zone = $this->resolver->resolve($address);

        return TaxRate::query()
            ->where('tax_zone_id', $zone->id)
            ->whereHas('taxClass', fn ($q) => $q->where('code', 'digital'))
            ->first();
    }
}
```

---

## Navigation

**Previous:** [03-tax-rates.md](03-tax-rates.md)  
**Next:** [05-database-schema.md](05-database-schema.md)

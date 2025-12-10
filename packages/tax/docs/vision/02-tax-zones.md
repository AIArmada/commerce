# Tax Zones

> **Document:** 02 of 06  
> **Package:** `aiarmada/tax`  
> **Status:** Vision

---

## Overview

Tax Zones define geographic regions where specific tax rules apply. Zones can be countries, states, or even postal code ranges.

---

## Zone Types

| Type | Description | Example |
|------|-------------|---------|
| **Country** | Entire country | Malaysia (MY) |
| **State** | State/Province level | Selangor, California |
| **Postal** | Postal code ranges | 47000-47999 |
| **Region** | Custom grouped countries | EU VAT Zone, ASEAN |

---

## Tax Zone Model

```php
namespace AIArmada\Tax\Models;

class TaxZone extends Model
{
    protected $fillable = [
        'name',
        'code',                 // 'my', 'eu-vat', 'us-ca'
        'type',                 // TaxZoneType enum
        'countries',            // JSON array of country codes
        'states',               // JSON array of state codes
        'postal_codes',         // JSON array of postal patterns
        'is_default',           // Fallback zone
        'priority',             // Higher = checked first
    ];

    protected $casts = [
        'type' => TaxZoneType::class,
        'countries' => 'array',
        'states' => 'array',
        'postal_codes' => 'array',
        'is_default' => 'boolean',
    ];

    // Relationships
    public function rates(): HasMany;

    // Check if address matches zone
    public function matches(Address $address): bool
    {
        return match ($this->type) {
            TaxZoneType::Country => $this->matchesCountry($address),
            TaxZoneType::State => $this->matchesState($address),
            TaxZoneType::Postal => $this->matchesPostal($address),
            TaxZoneType::Region => $this->matchesRegion($address),
        };
    }

    protected function matchesCountry(Address $address): bool
    {
        return in_array($address->country_code, $this->countries);
    }

    protected function matchesState(Address $address): bool
    {
        return in_array($address->country_code, $this->countries)
            && in_array($address->state, $this->states);
    }

    protected function matchesPostal(Address $address): bool
    {
        foreach ($this->postal_codes as $pattern) {
            if ($this->postalMatches($address->postal_code, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
```

---

## Zone Resolution

```php
namespace AIArmada\Tax\Services;

class TaxZoneResolver
{
    public function resolve(Address $address): ?TaxZone
    {
        // Check specific zones first (higher priority)
        $zone = TaxZone::query()
            ->orderBy('priority', 'desc')
            ->get()
            ->first(fn ($zone) => $zone->matches($address));

        if ($zone) {
            return $zone;
        }

        // Fall back to default zone
        return TaxZone::where('is_default', true)->first();
    }
}
```

---

## Predefined Zones

### Malaysia
```php
[
    'name' => 'Malaysia',
    'code' => 'my',
    'type' => TaxZoneType::Country,
    'countries' => ['MY'],
    'is_default' => true,
]
```

### EU VAT Zone
```php
[
    'name' => 'European Union',
    'code' => 'eu-vat',
    'type' => TaxZoneType::Region,
    'countries' => ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'PL', ...],
]
```

### US by State
```php
[
    'name' => 'California',
    'code' => 'us-ca',
    'type' => TaxZoneType::State,
    'countries' => ['US'],
    'states' => ['CA'],
]
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-tax-rates.md](03-tax-rates.md)

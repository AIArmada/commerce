# Shipping Vision: Shipping Zones

> **Document:** 07 of 11  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision Document  
> **Last Updated:** December 7, 2025

---

## Overview

Shipping Zones enable geographic-based shipping configuration including rate tables, carrier availability, delivery estimates, and restrictions.

---

## Core Models

### ShippingZone Model

```php
class ShippingZone extends Model
{
    protected $fillable = [
        'owner_id', 'owner_type',
        'name',
        'code',
        'type', // country, state, postcode, radius
        'countries', // JSON array of country codes
        'states', // JSON array of state codes  
        'postcode_ranges', // JSON: [{ "from": "40000", "to": "49999" }]
        'center_coords', // For radius type
        'radius_km',
        'priority', // Higher = checked first
        'is_default',
        'active',
    ];

    public function rates(): HasMany;
    public function carrierAvailability(): HasMany;
    public function restrictions(): HasMany;
}
```

### ShippingRate Model

```php
class ShippingRate extends Model
{
    protected $fillable = [
        'zone_id',
        'carrier_code',
        'method_code',
        'name',
        'calculation_type', // flat, per_kg, per_item, percentage, table
        'base_rate',
        'per_unit_rate',
        'min_charge',
        'max_charge',
        'free_shipping_threshold',
        'estimated_days_min',
        'estimated_days_max',
        'conditions', // JSON conditions
        'active',
    ];
}
```

---

## Zone Resolution

```php
class ShippingZoneResolver
{
    public function resolve(AddressData $address): ?ShippingZone
    {
        return ShippingZone::query()
            ->active()
            ->orderByDesc('priority')
            ->get()
            ->first(fn ($zone) => $this->matches($zone, $address));
    }

    public function getApplicableRates(AddressData $address): Collection
    {
        $zone = $this->resolve($address);
        return $zone?->rates()->active()->get() ?? collect();
    }
}
```

---

## Zone Types

| Type | Description | Example |
|------|-------------|---------|
| `country` | Entire countries | Malaysia, Singapore |
| `state` | States/provinces | Selangor, Sabah |
| `postcode` | Postcode ranges | 40000-49999 |
| `radius` | Distance from point | 50km from KL |

---

## Rate Calculation Types

| Type | Formula |
|------|---------|
| `flat` | Fixed amount |
| `per_kg` | base_rate + (weight × per_unit_rate) |
| `per_item` | base_rate + (items × per_unit_rate) |
| `percentage` | cart_total × rate_percent |
| `table` | Lookup table by weight/price brackets |

---

## Carrier Availability

Restrict carriers by zone:

```php
class CarrierZoneAvailability extends Model
{
    protected $fillable = [
        'zone_id',
        'carrier_code',
        'available', // boolean
        'surcharge', // additional charge for this zone
    ];
}
```

---

## Restricted Zones

Block shipping entirely:

```php
class ShippingRestriction extends Model
{
    protected $fillable = [
        'zone_id',
        'restriction_type', // no_shipping, product_category, hazmat
        'conditions',
        'message', // "Shipping not available to this location"
    ];
}
```

---

## Navigation

**Previous:** [06-returns-management.md](06-returns-management.md)  
**Next:** [08-cart-integration.md](08-cart-integration.md)

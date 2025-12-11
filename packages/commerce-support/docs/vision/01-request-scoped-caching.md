# Request-Scoped Caching with `once()`

> **Document:** Performance Patterns  
> **Package:** `aiarmada/commerce-support`  
> **Status:** Implementation Guide  
> **Laravel Version:** 11.x+ (native `once()` helper)

---

## Overview

Laravel 11 introduced the native `once()` helper function that caches the result of a callback for the lifetime of the request. This is invaluable for expensive computations that are called multiple times within a single request cycle.

---

## The Problem

Many commerce operations calculate the same values multiple times per request:

```php
// Checkout flow - each of these might call getTotal() internally
$cart->validateStock();           // Calls getTotal() to check minimum order
$cart->applyDiscounts();          // Calls getTotal() for discount eligibility
$cart->calculateShipping();       // Calls getTotal() for free shipping threshold
$cart->calculateTax();            // Calls getTotal() for tax base
$order = $cart->createOrder();    // Calls getTotal() for order total
```

**Result:** 5+ database queries for the same calculation.

---

## The Solution: `once()`

```php
public function getTotal(): Money
{
    return once(function () {
        $total = $this->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });
        
        return Money::MYR($total);
    });
}
```

**Result:** 1 calculation, cached for entire request.

---

## Pattern: `CachesComputedValues` Trait

The `commerce-support` package provides a trait that standardizes this pattern:

```php
use AIArmada\CommerceSupport\Traits\CachesComputedValues;

class Affiliate extends Model
{
    use CachesComputedValues;

    public function getDefaultTier(): ?AffiliateProgramTier
    {
        return $this->cachedComputation(__METHOD__, fn () => 
            $this->tiers()->orderBy('level', 'desc')->first()
        );
    }

    public function getTotalEarnings(): int
    {
        return $this->cachedComputation(__METHOD__, fn () => 
            $this->conversions()->sum('commission_minor')
        );
    }
}
```

---

## When to Use

### ✅ Good Candidates

| Scenario | Example |
|----------|---------|
| **Aggregate queries** | `->sum()`, `->count()`, `->avg()` |
| **Related model lookups** | `getDefaultTier()`, `getPrimaryAddress()` |
| **Computed accessors** | `getLifetimeValueAttribute()` |
| **Configuration resolution** | Settings that don't change mid-request |
| **External API results** | Cached rate quotes, tax lookups |

### ❌ Not Suitable

| Scenario | Why |
|----------|-----|
| **Values that change mid-request** | Cart items added/removed |
| **Time-sensitive values** | `now()` comparisons |
| **User input dependent** | Form submissions |
| **Side effects expected** | Logging, events on each call |

---

## ⚠️ Critical: `once()` and Method Parameters

**`once()` does NOT consider method parameters!** It caches based on:
1. The object instance (`$this`)
2. The call site (file + line number)

### ❌ Dangerous Pattern

```php
// BAD: Different addresses return the same cached zone!
public function resolve(Address $address): ?Zone
{
    return once(function () use ($address) {
        return $this->performResolution($address);
    });
}

// First call caches result for $addressA
$resolver->resolve($addressA);  // Calculates and caches

// Second call returns WRONG result (cached $addressA zone)!
$resolver->resolve($addressB);  // Returns $addressA's zone!
```

### ✅ Correct Pattern: Parameter-Keyed Caching

```php
class ZoneResolver
{
    private array $cache = [];

    public function resolve(Address $address): ?Zone
    {
        $key = $this->buildCacheKey($address);
        
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        
        return $this->cache[$key] = $this->performResolution($address);
    }

    private function buildCacheKey(Address $address): string
    {
        return md5(serialize([
            'country' => $address->countryCode,
            'state' => $address->state,
            'postal' => $address->postCode,
        ]));
    }
    
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
```

### When to Use Which

| Approach | Use When |
|----------|----------|
| `once()` | Parameterless methods, or parameters don't affect result |
| Parameter-keyed cache | Parameters determine the result (addresses, IDs, etc.) |
| Lazy invalidation (like cart) | Values change during request lifecycle |

---

## Package-Specific Use Cases

### Cart Package
```php
// Total is calculated once per request
public function getTotal(): Money
{
    return once(fn () => $this->calculateTotal());
}
```

### Affiliates Package
```php
// Volume calculations cached
public function getMonthlyVolume(): int
{
    return once(fn () => $this->conversions()
        ->where('occurred_at', '>=', now()->startOfMonth())
        ->sum('total_minor'));
}
```

### Pricing Package
```php
// Price resolution cached per product/customer combo
public function resolve(Priceable $item, ?Customer $customer): ResolvedPrice
{
    $key = $item->id . ':' . ($customer?->id ?? 'guest');
    
    return once(fn () => $this->doResolve($item, $customer));
}
```

### Tax Package
```php
// Zone resolution cached per address
public function resolveZone(Address $address): ?TaxZone
{
    return once(fn () => $this->doResolveZone($address));
}
```

---

## Testing Considerations

The `once()` cache persists for the request lifetime. In tests:

```php
it('calculates total correctly after adding items', function () {
    $cart = new Cart();
    $cart->add($item1);
    
    expect($cart->getTotal()->getAmount())->toBe(1000);
    
    // This won't update if using once() without reset!
    $cart->add($item2);
    
    // Need to invalidate cache or use fresh instance
    expect($cart->fresh()->getTotal()->getAmount())->toBe(2000);
});
```

**Solution:** Implement cache invalidation on mutations:

```php
public function add(CartItem $item): void
{
    $this->items->push($item);
    $this->invalidateComputedCache();
}
```

---

## Navigation

**Back to:** [ECOSYSTEM-ARCHITECTURE.md](/docs/ECOSYSTEM-ARCHITECTURE.md)

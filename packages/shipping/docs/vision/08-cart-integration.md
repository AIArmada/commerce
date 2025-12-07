# Shipping Vision: Cart Integration

> **Document:** 08 of 11  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision Document  
> **Last Updated:** December 7, 2025

---

## Overview

Cart Integration enables seamless shipping rate calculation, method selection, and checkout flow within the `aiarmada/cart` ecosystem.

---

## ShippingConditionProvider

Integrates with cart's condition system:

```php
class ShippingConditionProvider implements ConditionProviderInterface
{
    public function __construct(
        private readonly RateShoppingEngine $rateEngine,
        private readonly FreeShippingEvaluator $freeShipping
    ) {}

    public function getConditions(Cart $cart): Collection
    {
        $destination = $this->getShippingAddress($cart);
        if (! $destination) {
            return collect();
        }

        $rate = $this->getSelectedRate($cart, $destination);
        if (! $rate) {
            return collect();
        }

        // Apply free shipping if qualified
        $freeResult = $this->freeShipping->evaluate($cart);
        if ($freeResult?->applies) {
            $rate = $rate->withRate(0);
        }

        return collect([
            new ShippingCondition(
                name: $rate->service,
                type: 'shipping',
                value: $rate->rate,
                attributes: [
                    'carrier' => $rate->carrier,
                    'service' => $rate->service,
                    'estimated_days' => $rate->estimatedDays,
                ],
            ),
        ]);
    }
}
```

---

## Address Handling

### Shipping Address Trait

```php
trait InteractsWithShippingAddress
{
    public function setShippingAddress(AddressData $address): self
    {
        return $this->setMetadata('shipping_address', $address->toArray());
    }

    public function getShippingAddress(): ?AddressData
    {
        $data = $this->metadata['shipping_address'] ?? null;
        return $data ? AddressData::from($data) : null;
    }

    public function setSelectedShippingMethod(string $carrier, string $service): self
    {
        return $this->setMetadata('selected_shipping_method', [
            'carrier' => $carrier,
            'service' => $service,
        ]);
    }
}
```

---

## Checkout Flow

### Step 1: Address Entry
```php
$cart->setShippingAddress($address);
```

### Step 2: Get Available Rates
```php
$rates = app(RateShoppingEngine::class)->getRatesForCart($cart, $address);
```

### Step 3: Customer Selects Method
```php
$cart->setSelectedShippingMethod('jnt', 'EZ');
```

### Step 4: Recalculate Cart
```php
$cart->recalculate(); // Shipping condition now applies
```

---

## Address Validation

```php
interface AddressValidatorInterface
{
    public function validate(AddressData $address): ValidationResult;
    public function suggest(AddressData $address): Collection;
}

class AddressValidationResult
{
    public function __construct(
        public bool $valid,
        public ?AddressData $correctedAddress = null,
        public array $warnings = [],
    ) {}
}
```

---

## Weight Calculation

```php
trait HasShippingWeight
{
    public function getTotalWeight(): int
    {
        return $this->getItems()->sum(function ($item) {
            $weight = $item->getAttribute('weight') ?? 0;
            return $weight * $item->quantity;
        });
    }

    public function getTotalWeightKg(): float
    {
        return $this->getTotalWeight() / 1000;
    }
}
```

---

## Free Shipping Threshold Display

```php
class FreeShippingResult
{
    public function __construct(
        public bool $applies,
        public ?string $message = null,
        public ?int $remainingAmount = null,
        public bool $nearThreshold = false,
    ) {}
}

// Usage in view
@if($freeShipping->nearThreshold)
    <div class="promo-banner">
        Add {{ money($freeShipping->remainingAmount) }} more for free shipping!
    </div>
@endif
```

---

## Events

```php
class ShippingAddressSet // Cart address updated
class ShippingMethodSelected // Customer selected method
class ShippingRatesUpdated // Rates refreshed
class FreeShippingApplied // Free shipping threshold met
```

---

## Navigation

**Previous:** [07-shipping-zones.md](07-shipping-zones.md)  
**Next:** [09-database-schema.md](09-database-schema.md)

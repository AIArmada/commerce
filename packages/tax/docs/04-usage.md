---
title: Usage
---

# Usage

## Basic Tax Calculation

### Using the Facade

```php
use AIArmada\Tax\Facades\Tax;

// Calculate tax on RM 100.00 (10000 cents)
$result = Tax::calculateTax(
    amountInCents: 10000,
    taxClass: 'standard',
    zoneId: $zoneId, // optional - will auto-detect from context
    context: []
);

// Access results
$result->taxAmount;      // 600 (in cents)
$result->ratePercentage; // 600 (basis points = 6.00%)
$result->rateName;       // "SST"
$result->zoneName;       // "Malaysia"
$result->zoneId;         // UUID
$result->includedInPrice; // false
$result->breakdown;      // Array of applied rates
```

### Using Dependency Injection

```php
use AIArmada\Tax\Contracts\TaxCalculatorInterface;

class CheckoutService
{
    public function __construct(
        private TaxCalculatorInterface $taxCalculator
    ) {}

    public function calculateOrderTax(Order $order): int
    {
        $result = $this->taxCalculator->calculateTax(
            $order->subtotal,
            'standard',
            null,
            ['shipping_address' => $order->shippingAddress->toArray()]
        );

        return $result->taxAmount;
    }
}
```

## Shipping Tax

```php
$shippingTax = Tax::calculateShippingTax(
    shippingAmountInCents: 1500, // RM 15.00
    zoneId: $zoneId,
    context: []
);
```

Shipping tax respects the `calculate_tax_on_shipping` config. Only rates with `is_shipping = true` are applied.

## Zone Resolution

### Explicit Zone

```php
$result = Tax::calculateTax(10000, 'standard', $zoneId);
```

### Auto-detect from Address

```php
$result = Tax::calculateTax(10000, 'standard', null, [
    'shipping_address' => [
        'country' => 'MY',
        'state' => 'Selangor',
        'postcode' => '43000',
    ],
]);
```

### Billing vs Shipping

Configure `address_priority` or use context:

```php
$result = Tax::calculateTax(10000, 'standard', null, [
    'shipping_address' => ['country' => 'MY'],
    'billing_address' => ['country' => 'SG'],
]);
// Uses shipping_address by default (configurable)
```

## Tax Exemptions

### Check Exemption in Calculation

```php
$result = Tax::calculateTax(10000, 'standard', $zoneId, [
    'customer_id' => $customer->id,
    'customer_type' => Customer::class,
]);

if ($result->isExempt()) {
    echo $result->exemptionReason; // "Non-profit organization"
}
```

### Create Exemption

```php
use AIArmada\Tax\Models\TaxExemption;

$exemption = TaxExemption::create([
    'exemptable_id' => $customer->id,
    'exemptable_type' => Customer::class,
    'tax_zone_id' => $zone->id, // null = all zones
    'reason' => 'Non-profit organization',
    'certificate_number' => 'NPO-2024-001',
    'status' => 'pending', // pending, approved, rejected
    'starts_at' => now(),
    'expires_at' => now()->addYear(),
]);

// Approve
$exemption->approve();

// Reject
$exemption->reject('Invalid certificate');
```

## Tax Classes

### Using Tax Classes

```php
// Standard rate
$result = Tax::calculateTax(10000, 'standard');

// Reduced rate (e.g., food items)
$result = Tax::calculateTax(10000, 'reduced');

// Zero rate (tracked but 0%)
$result = Tax::calculateTax(10000, 'zero');

// Exempt (not taxable)
$result = Tax::calculateTax(10000, 'exempt');
```

### Managing Tax Classes

```php
use AIArmada\Tax\Models\TaxClass;

TaxClass::create([
    'name' => 'Digital Goods',
    'slug' => 'digital',
    'description' => 'E-books, software, streaming',
    'is_active' => true,
]);
```

## Compound Taxes

For stacked taxes (e.g., federal + state):

```php
use AIArmada\Tax\Models\TaxRate;

// Federal tax (applied first)
TaxRate::create([
    'zone_id' => $zone->id,
    'name' => 'Federal Tax',
    'tax_class' => 'standard',
    'rate' => 500, // 5%
    'is_compound' => false,
    'priority' => 10,
]);

// State tax (applied on base + federal)
TaxRate::create([
    'zone_id' => $zone->id,
    'name' => 'State Tax',
    'tax_class' => 'standard',
    'rate' => 300, // 3%
    'is_compound' => true,
    'priority' => 5,
]);

// On RM 100:
// Federal: 100 × 5% = 5
// State: (100 + 5) × 3% = 3.15 → 3
// Total: 8
```

### Breakdown

```php
$result = Tax::calculateTax(10000, 'standard', $zoneId);

foreach ($result->breakdown as $tax) {
    echo "{$tax['name']}: {$tax['rate']/100}% = {$tax['amount']} cents";
    if ($tax['is_compound']) {
        echo " (compound)";
    }
}
```

## Tax Result Data

The `TaxResultData` DTO provides:

```php
$result->taxAmount;        // int - Tax in cents
$result->rateId;           // string - Primary rate UUID
$result->rateName;         // string - Rate display name
$result->ratePercentage;   // int - Rate in basis points
$result->zoneId;           // string - Zone UUID
$result->zoneName;         // string - Zone display name
$result->includedInPrice;  // bool - Tax-inclusive pricing
$result->exemptionReason;  // ?string - If exempt, why
$result->breakdown;        // array - All applied rates

// Helper methods
$result->isExempt();                    // bool
$result->getFormattedAmount('RM');      // "RM 6.00"
$result->getFormattedRate();            // "6.00%"
$result->getSummary();                  // "SST (6.00%)"
$result->hasCompoundTaxes();            // bool
```

## Multi-tenancy

Enable owner scoping:

```php
// config/tax.php
'features' => [
    'owner' => [
        'enabled' => true,
        'include_global' => false,
    ],
],
```

All queries will automatically scope to the current owner via `TaxOwnerScope`.

---
title: Condition System
---

# Condition System

The Cart package features a sophisticated condition system for discounts, fees, taxes, and shipping with precise control over when and how they apply.

## Core Concepts

### Condition Target

Every condition has a **target** that defines:
- **Scope**: Where it applies (cart-level or item-level)
- **Phase**: When it applies in the calculation pipeline
- **Application**: How it applies to amounts

### Target DSL

Targets can be expressed as a DSL string:

```
scope@phase/application
```

Examples:
- `cart@CART_SUBTOTAL/aggregate` - Apply once to subtotal
- `items@ITEM_DISCOUNT/per_item` - Apply to each item
- `cart@TAX/aggregate` - Apply to taxable amount

## Creating Conditions

### Using CartCondition Directly

```php
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Target;

$condition = new CartCondition(
    name: '10% Off',
    type: 'discount',
    target: Target::cart()->phase(ConditionPhase::CART_SUBTOTAL)->build(),
    value: '-10%',
    attributes: ['description' => 'Summer sale discount'],
    order: 1
);

Cart::addCondition($condition);
```

### Using Presets

```php
use AIArmada\Cart\Conditions\ConditionPresets;

// Percentage discount
$discount = ConditionPresets::percentageDiscount(
    percentage: 15,
    name: 'Member Discount'
);

// Fixed discount
$fixed = ConditionPresets::fixedDiscount(
    amountCents: 500,
    name: '$5 Off'
);

// Tax
$tax = ConditionPresets::taxRate(
    percentage: 7,
    name: 'Sales Tax'
);

// Shipping
$shipping = ConditionPresets::flatRateShipping(
    amountCents: 599,
    name: 'Standard'
);

Cart::addCondition($discount);
Cart::addCondition($tax);
```

## Pipeline Phases

Conditions execute in strict order:

```php
use AIArmada\Cart\Conditions\Enums\ConditionPhase;

ConditionPhase::PRE_ITEM       // Order 10 - Before items
ConditionPhase::ITEM_DISCOUNT  // Order 20 - Item discounts
ConditionPhase::ITEM_POST      // Order 30 - After item discounts
ConditionPhase::CART_SUBTOTAL  // Order 40 - Subtotal adjustments
ConditionPhase::SHIPPING       // Order 50 - Shipping fees
ConditionPhase::TAXABLE        // Order 60 - Pre-tax adjustments
ConditionPhase::TAX            // Order 70 - Tax calculations
ConditionPhase::PAYMENT        // Order 80 - Payment fees
ConditionPhase::GRAND_TOTAL    // Order 90 - Final adjustments
ConditionPhase::CUSTOM         // Order 100 - Custom phase
```

### Phase Selection

```php
// Discount before tax
$discount = new CartCondition(
    name: 'Pre-tax Discount',
    type: 'discount',
    target: Target::cart()->phase(ConditionPhase::CART_SUBTOTAL)->build(),
    value: '-10%'
);

// Fee after tax
$fee = new CartCondition(
    name: 'Processing Fee',
    type: 'fee',
    target: Target::cart()->phase(ConditionPhase::GRAND_TOTAL)->build(),
    value: '+199'
);
```

## Application Strategies

```php
use AIArmada\Cart\Conditions\Enums\ConditionApplication;

// AGGREGATE: Apply once to total amount
Target::cart()->applyAggregate()->build();

// PER_ITEM: Apply to each line item's total
Target::items()->applyPerItem()->build();

// PER_UNIT: Apply to each unit (quantity)
Target::items()->applyPerUnit()->build();

// PER_GROUP: Apply to grouped items
Target::items()->applyPerGroup()->groupBy('category')->build();
```

### Application Examples

```php
// $5 off total
$condition = new CartCondition(
    name: 'Cart Discount',
    type: 'discount',
    target: Target::cart()->applyAggregate()->build(),
    value: '-500'
);

// $1 off each item
$condition = new CartCondition(
    name: 'Per Item Discount',
    type: 'discount',
    target: Target::items()->applyPerItem()->build(),
    value: '-100'
);

// $0.50 off each unit
$condition = new CartCondition(
    name: 'Per Unit Discount',
    type: 'discount',
    target: Target::items()->applyPerUnit()->build(),
    value: '-50'
);
```

## Fixed-Value Syntax

Fixed (non-percentage) values follow one contract with no exceptions:

- Integer strings are already minor units: `'+5'` is 5 minor, `'-500'` is 500 minor.
- Decimal strings are major units with half-up rounding: `'+5.00'` is 500 minor.
- Percentage strings (`'-10%'`, `'+8%'`) are rates, not money.

> [!WARNING]
> Whole numbers are exact minor units, so `'+5'` (5 minor) and `'+5.00'`
> (500 minor) differ by 100x. This cliff is intentional: it keeps whole-number
> money exact instead of routing it through float parsing. Always write the
> decimal form when you mean major units.

## Condition Scopes

### Cart Scope

Applies to the entire cart:

```php
$condition = new CartCondition(
    name: 'Cart Discount',
    type: 'discount',
    target: Target::cart()->build(),
    value: '-10%'
);
```

### Items Scope

Applies to items:

```php
$condition = new CartCondition(
    name: 'Item Discount',
    type: 'discount',
    target: Target::items()->build(),
    value: '-5%'
);
```

### Filtered Items

Apply to specific items:

```php
use AIArmada\Cart\Conditions\Enums\ConditionFilterOperator;

// Filter by attribute
$condition = new CartCondition(
    name: 'Category Discount',
    type: 'discount',
    target: Target::items()
        ->where('category', ConditionFilterOperator::EQ, 'electronics')
        ->build(),
    value: '-15%'
);

// Multiple filters
$condition = new CartCondition(
    name: 'Premium Discount',
    type: 'discount',
    target: Target::items()
        ->where('category', ConditionFilterOperator::IN, ['premium', 'gold'])
        ->where('price', ConditionFilterOperator::GT, 5000)
        ->build(),
    value: '-20%'
);
```

## Condition Values

### Percentage Values

```php
'-10%'   // 10% discount
'+7%'    // 7% surcharge
'-15.5%' // 15.5% discount
```

### Fixed Values (in cents)

```php
'-500'   // $5.00 discount
'+299'   // $2.99 fee
'1500'   // $15.00 (implicit +)
```

### Multipliers

```php
'*0.9'   // Multiply by 0.9 (10% off)
'*1.15'  // Multiply by 1.15 (15% markup)
'/2'     // Divide by 2 (50% off)
```

## Condition Presets

The `ConditionPresets` class provides 20+ ready-to-use conditions:

### Discounts

```php
ConditionPresets::percentageDiscount(10, 'Sale');
ConditionPresets::fixedDiscount(500, '$5 Off');
ConditionPresets::tieredDiscount([100 => 5, 500 => 10, 1000 => 15], 'Volume');
ConditionPresets::bulkQuantityDiscount(3, 15, 'Bulk');
ConditionPresets::percentageDiscountWithMinimum(20, 5000, 'Min $50');
ConditionPresets::flashSaleDiscount(25, '2024-01-15', '2024-01-16', 'Flash');
```

### Fees & Charges

```php
ConditionPresets::serviceFee(199, 'Handling');
ConditionPresets::surcharge(2.5, 'Commission');
```

### Shipping

```php
ConditionPresets::flatRateShipping(599, 'Standard');
ConditionPresets::freeShippingOver(5000, 'Free Ship');
ConditionPresets::shippingDiscount(5, 'Rate');
ConditionPresets::freeShipping('Free Shipping');
```

### Tax

```php
ConditionPresets::taxRate(7, 'Sales Tax');
ConditionPresets::taxExempt('Tax Exempt');
ConditionPresets::taxRate(8, 'Combined');
```

## Condition Providers (Integrations)

The Cart package can auto-attach conditions from other packages via the `ConditionProviderRegistry`.
These providers are registered by integrations (e.g., Vouchers, Affiliates, Shipping, JNT) and
are synced when you read conditions or totals.

### Registering a Provider

```php
use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Cart\Contracts\ConditionProviderInterface;

app(ConditionProviderRegistry::class)->register(MyConditionProvider::class);
```

### Provider Lifecycle

- Conditions are added on-demand during `Cart::getConditions()` and totals.
- Providers can invalidate their conditions via `validate()`.
- Provider-managed conditions are tagged with a `__provider` attribute so stale
    conditions can be removed when a provider is no longer available.

## Item-Level Conditions

Add conditions to specific items:

```php
// Add to item
Cart::addItemCondition('SKU-001', new CartCondition(
    name: 'Item Discount',
    type: 'discount',
    target: Target::items()->build(),
    value: '-10%'
));

// Remove from item
Cart::removeItemCondition('SKU-001', 'Item Discount');

// Clear all item conditions
Cart::clearItemConditions('SKU-001');
```

## Percentage Rate Arithmetic

Percentages use **basis points** (1/100th of a percent) internally for integer arithmetic:

```php
use AIArmada\Cart\Conditions\PercentageRate;

$rate = PercentageRate::fromPercentString('-10%');
// Internally: -1000 basis points

$result = $rate->apply(10000); // $100.00 in cents
// Returns: 9000 (10% off = $90.00)

// Scale is 10000 (100% = 10000 bp)
$rate->basisPoints; // -1000
$rate->isDiscount(); // true
$rate->isCharge(); // false
```

## Pipeline Results

Get detailed breakdown of condition calculations:

```php
$result = Cart::evaluateConditionPipeline();

$result->initialAmount; // Starting amount
$result->finalAmount;   // After all conditions
$result->subtotal();    // After subtotal phase
$result->total();       // Grand total

// Per-phase breakdown
foreach ($result->phases() as $phase => $phaseResult) {
    echo "{$phase}: {$phaseResult->baseAmount} → {$phaseResult->finalAmount}";
}
```

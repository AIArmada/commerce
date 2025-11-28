# Conditions & Pricing

Conditions modify prices at three levels: item, subtotal, and total.

## Calculation Order

```
1. Item Level    → Per-item discounts/fees
        ↓
2. Subtotal Level → Cart-wide discounts, shipping
        ↓
3. Total Level    → Taxes, processing fees
```

## Quick Helpers

### Discounts

```php
Cart::addDiscount('promo', '15%');
Cart::addDiscount('coupon', '-10.00');
```

### Taxes

```php
Cart::addTax('vat', '8%');
Cart::addTax('sales-tax', '6.5%', ['region' => 'CA']);
```

### Fees

```php
Cart::addFee('processing', '+2.50');
Cart::addFee('convenience', '+3%');
```

### Shipping

```php
use AIArmada\Cart\Conditions\TargetPresets;

Cart::addShipping('standard', '10.00', TargetPresets::cartShipping(), 'standard', [
    'eta' => '3-5 business days',
]);

$shipping = Cart::getShipping();
Cart::removeShipping();
```

## CartCondition Object

```php
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\TargetPresets;

$condition = new CartCondition(
    name: 'summer-sale',
    type: 'discount',
    target: TargetPresets::cartSubtotal(),
    value: '-20%',
    attributes: ['campaign' => 'Summer 2024'],
    order: 100,
    rules: [
        fn($cart) => $cart->getRawSubtotalWithoutConditions() >= 100.00,
    ],
);

Cart::addCondition($condition);
```

### Parameters

- **name** – Unique identifier
- **type** – Category: `discount`, `tax`, `fee`, `shipping`
- **target** – Where to apply (ConditionTarget or preset)
- **value** – Amount: `'-20%'`, `'+8%'`, `'-10.00'`, `'+5.50'`, `'*0.9'`
- **attributes** – Arbitrary metadata
- **order** – Execution priority (lower = earlier)
- **rules** – Array of closures returning bool

## Target Presets

```php
use AIArmada\Cart\Conditions\TargetPresets;

TargetPresets::cartSubtotal();    // Cart subtotal phase
TargetPresets::cartGrandTotal();  // Cart total phase
TargetPresets::cartShipping();    // Shipping phase
TargetPresets::cartTax();         // Tax phase
TargetPresets::itemsPerItem();    // Per-item application
```

## Value Formats

### Percentages

```php
'-15%'  // 15% off
'+8%'   // 8% markup
```

### Fixed Amounts

```php
'-10.00'  // $10 off
'+5.00'   // $5 fee
```

### Multipliers

```php
'*0.9'  // 10% off (multiply by 0.9)
'/2'    // Half price
```

## Item-Level Conditions

```php
$discount = new CartCondition(
    name: 'bulk-discount',
    type: 'discount',
    target: TargetPresets::itemsPerItem(),
    value: '-10%',
);

Cart::addItemCondition('laptop-001', $discount);
Cart::removeItemCondition('laptop-001', 'bulk-discount');
Cart::clearItemConditions('laptop-001');
```

## Managing Conditions

```php
// Get all conditions
$conditions = Cart::getConditions();

// Get by type
$discounts = $conditions->discounts();
$taxes = $conditions->taxes();

// Check existence
if ($conditions->has('promo-code')) {
    echo "Promo applied";
}

// Remove condition
Cart::removeCondition('summer-sale');

// Clear all
Cart::clearConditions();
```

## Dynamic Conditions

Conditions that apply only when rules pass:

```php
$tieredDiscount = new CartCondition(
    name: 'spend-200-save-20',
    type: 'discount',
    target: TargetPresets::cartSubtotal(),
    value: '-20.00',
    rules: [
        fn($cart) => $cart->getRawSubtotalWithoutConditions() >= 200.00,
    ],
);

Cart::getCurrentCart()->registerDynamicCondition($tieredDiscount);
Cart::evaluateDynamicConditions();
```

Rules are evaluated after `add()`, `update()`, and `remove()` operations.

## Common Patterns

### Tiered Discounts

```php
$tier1 = new CartCondition('tier-1', 'discount', TargetPresets::cartSubtotal(), '-10%',
    rules: [fn($c) => $c->getRawSubtotalWithoutConditions() >= 100.00], order: 100);

$tier2 = new CartCondition('tier-2', 'discount', TargetPresets::cartSubtotal(), '-20%',
    rules: [fn($c) => $c->getRawSubtotalWithoutConditions() >= 200.00], order: 90);

Cart::getCurrentCart()->registerDynamicCondition($tier1);
Cart::getCurrentCart()->registerDynamicCondition($tier2);
```

### Member-Only Pricing

```php
$memberDiscount = new CartCondition(
    'member-pricing', 'discount', TargetPresets::cartSubtotal(), '-20%',
    rules: [
        fn($cart) => auth()->check(),
        fn($cart) => auth()->user()->isMember(),
    ]
);
```

### Free Shipping Threshold

```php
$shipping = new CartCondition(
    'shipping', 'shipping', TargetPresets::cartShipping(), '+10.00',
    rules: [fn($cart) => $cart->getRawSubtotalWithoutConditions() < 50.00]
);
```

## Calculation Example

```php
Cart::add('laptop', 'Laptop', 1000.00, 2);  // $2000
Cart::add('mouse', 'Mouse', 50.00, 3);      // $150

// Item discount on laptop
$itemDiscount = new CartCondition('bulk', 'discount', TargetPresets::itemsPerItem(), '-10%');
Cart::addItemCondition('laptop', $itemDiscount);

// Cart discount
Cart::addDiscount('promo', '-5%');

// Shipping
Cart::addShipping('standard', '15.00', TargetPresets::cartShipping());

// Tax
Cart::addTax('vat', '8%');

// Calculation:
// Laptop: $1000 × 2 = $2000 → -10% = $1800
// Mouse: $50 × 3 = $150
// Subtotal: $1800 + $150 = $1950
// After promo (-5%): $1852.50
// After shipping (+$15): $1867.50
// After tax (+8%): $2016.90

echo Cart::total()->format(); // "$2,016.90"
```

## Next Steps

- [Money & Currency](money-and-currency.md) – Price calculations
- [Events](events.md) – Condition events
- [API Reference](api-reference.md) – Complete condition API

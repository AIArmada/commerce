# Conditions & Pricing

Conditions are the pricing engine of AIArmada Cart. They enable discounts, taxes, fees, shipping costs, and dynamic rules that respond to cart state. This guide covers everything from basic discounts to advanced dynamic conditions.

## 🎯 Understanding Conditions

Conditions modify prices at three levels in a predictable order:

```
1. Item Level    → Per-item discounts/fees
        ↓
2. Subtotal Level → Cart-wide discounts, shipping
        ↓
3. Total Level    → Taxes, processing fees
```

Each condition has:
- **Name** – Unique identifier
- **Type** – Category (discount, tax, fee, shipping)
- **Target definition** – Structured `ConditionTarget` describing scope, phase, and application
- **Value** – How much to adjust (%, fixed amount, multiplier)
- **Order** – Execution priority (lower = earlier)
- **Rules** – Conditions for application (optional)

## 📦 The CartCondition Object

```php
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\TargetPresets;

$condition = new CartCondition(
    name: 'summer-sale',
    type: 'discount',
    target: TargetPresets::cartSubtotal(),
    value: '-20%',
    attributes: ['campaign' => 'Summer 2024', 'code' => 'SUM20'],
    order: 100,
    rules: [
        fn($cart) => $cart->getRawSubtotalWithoutConditions() >= 100.00,
    ],
);

Cart::addCondition($condition);
```

### Parameters Explained

**name** (string, required)
- Unique identifier per cart instance
- Used to remove or update conditions
- Example: `'black-friday'`, `'vat-tax'`, `'express-shipping'`

**type** (string, required)
- Descriptive category for filtering
- Common types: `'discount'`, `'tax'`, `'fee'`, `'shipping'`
- Not enforced—use any string that fits your domain

**target/target_definition** (ConditionTarget|string|array, required)
- Pass a `ConditionTarget` instance (recommended), an associative array, or a target DSL string (`scope[:filters]@phase/application`).
- Targets carry **scope**, **phase**, **application**, and optional **selector/grouping** data.
- When providing an array payload (e.g. via API/factory) use the `target_definition` key to stay consistent with storage.
- Example builder usage:

```php
use AIArmada\Cart\Conditions\Target;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;

$target = Target::items()
    ->phase(ConditionPhase::ITEM_DISCOUNT)
    ->whereAttribute('category', 'electronics')
    ->applyPerItem()
    ->build();

$condition = new CartCondition(
    name: 'electronics-weekend',
    type: 'discount',
    target: $target,
    value: '-15%',
);
```

- DSL alternative: `items:attributes.category=electronics@item_discount/per-item`
- Helper presets are available for common combinations (see **Target Presets** below).

### Target Presets

`AIArmada\Cart\Conditions\TargetPresets` ships with ready-to-use targets that
cover the most common scenarios:

- `TargetPresets::cartSubtotal()` – cart scope, subtotal phase, aggregate application
- `TargetPresets::cartGrandTotal()` – cart scope, grand total phase
- `TargetPresets::cartShipping()` / `::cartTaxable()` / `::cartTax()`
- `TargetPresets::itemsPerItem()` – item scope, per-item application
- `TargetPresets::shipmentsPerGroup()` – shipment scope grouped per shipment
- `TargetPresets::paymentsPerPayment()` and `::fulfillmentsPerGroup()`

Call `->toArray()` when building payloads for persistence or APIs, or pass the
object instance directly to `CartCondition`.

> Unless stated otherwise, the snippets below assume
> `use AIArmada\Cart\Conditions\TargetPresets;` is present.

**value** (string, required)
- Percentage: `'-20%'`, `'+8%'`
- Fixed amount: `'-10.00'`, `'+5.50'`
- Multiplier: `'*0.9'` (10% off), `'*1.08'` (8% markup)
- Division: `'/2'` (half price—rarely used)

**attributes** (array, optional)
- Arbitrary metadata stored with the condition
- Access via `$condition->attributes['key']`
- Example: `['source' => 'email_campaign', 'expires' => '2024-12-31']`

**order** (int, optional, default: 100)
- Lower numbers execute first within the same target
- Item conditions → Subtotal conditions → Total conditions
- Within each group, sorted by order

**rules** (array, optional)
- Array of closures that return bool
- All must return `true` for condition to apply
- Receives `$cart` (and `$item` for item-level conditions)

## 🏷️ Convenience Helpers

Quick methods for common condition types:

### Discounts

```php
// Percentage discount on subtotal
Cart::addDiscount('new-customer', '15%');

// Fixed discount on subtotal
Cart::addDiscount('welcome-10', '-10.00');

// With attributes
Cart::addDiscount('promo-code', '20%', ['code' => 'SAVE20']);

// Targeted discount using the ConditionTarget builder
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Target;

Cart::addDiscount(
    'electronics-weekend',
    '-15%',
    Target::items()
        ->phase(ConditionPhase::ITEM_DISCOUNT)
        ->whereAttribute('category', 'electronics')
        ->applyPerItem()
        ->build()
);
```

### Taxes

```php
// Percentage tax on subtotal
Cart::addTax('vat', '8%');

// Sales tax with region info
Cart::addTax('sales-tax', '6.5%', ['region' => 'CA']);
```

### Fees

```php
// Fixed processing fee on total
Cart::addFee('processing', '+2.50');

// Percentage fee
Cart::addFee('convenience', '+3%');
```

### Shipping

```php
// Flat rate shipping
Cart::addShipping(
    'standard',
    '10.00',
    target: TargetPresets::cartShipping(),
    method: 'standard',
    attributes: ['eta' => '3-5 business days']
);

// Express shipping
Cart::addShipping(
    'express',
    '25.00',
    target: TargetPresets::cartShipping(),
    method: 'express',
    attributes: [
        'eta' => '1-2 business days',
        'carrier' => 'FedEx',
    ]
);

// Using a builder to target the shipping phase explicitly
Cart::addShipping(
    'international',
    '+35.00',
    TargetPresets::cartShipping(),
    method: 'intl',
    attributes: ['carrier' => 'DHL']
);

// Get active shipping
$shipping = Cart::getShipping();
if ($shipping) {
    echo "Shipping: {$shipping->name}";
    echo "ETA: {$shipping->attributes['eta']}";
}

// Remove shipping
Cart::removeShipping();

// Provide shipment data so pipeline can calculate charges per shipment
Cart::resolveShipmentsUsing(fn () => [
    ['id' => 'domestic', 'base_amount' => 10.00],
    ['id' => 'express', 'base_amount' => 5.00],
]);
// Each shipping condition targeting Phase::SHIPPING is evaluated per shipment

// Payment-specific adjustments (e.g., BNPL surcharge)
Cart::resolvePaymentsUsing(fn () => [
    ['id' => 'card', 'base_amount' => 100.00],
    ['id' => 'bnpl', 'base_amount' => 50.00],
]);
Cart::addFee(
    'bnpl-surcharge',
    '+2%',
    Target::cart()
        ->phase(ConditionPhase::PAYMENT)
        ->apply(ConditionApplication::PER_PAYMENT)
        ->build()
);
```

## 🎨 Value Formats

### Percentages

```php
// Discount (negative percentage)
Cart::addCondition(new CartCondition(
    'sale',
    'discount',
    TargetPresets::cartSubtotal(),
    '-15%'  // 15% off
));

// Markup (positive percentage)
Cart::addCondition(new CartCondition(
    'tax',
    'tax',
    TargetPresets::cartGrandTotal(),
    '+8%'  // 8% tax
));
```

**Calculation:**
- Base: $100.00
- `-15%` → $100.00 - ($100.00 × 0.15) = $85.00
- `+8%` → $100.00 + ($100.00 × 0.08) = $108.00

### Fixed Amounts

```php
// Discount (negative amount)
Cart::addCondition(new CartCondition(
    'coupon',
    'discount',
    TargetPresets::cartSubtotal(),
    '-10.00'  // $10 off
));

// Fee (positive amount)
Cart::addCondition(new CartCondition(
    'handling',
    'fee',
    TargetPresets::cartGrandTotal(),
    '+5.00'  // $5 fee
));
```

### Multipliers & Division

```php
// 10% off via multiplier
Cart::addCondition(new CartCondition(
    'bulk',
    'discount',
    TargetPresets::cartSubtotal(),
    '*0.9'  // Multiply by 0.9 (10% off)
));

// Half price (uncommon)
Cart::addCondition(new CartCondition(
    'half-off',
    'discount',
    TargetPresets::itemsPerItem(),
    '/2'  // Divide by 2
));
```

## 🔄 Item-Level Conditions

Apply conditions to specific items:

```php
use AIArmada\Cart\Conditions\CartCondition;

// Create item condition
$bulkDiscount = new CartCondition(
    name: 'bulk-discount',
    type: 'discount',
    target: TargetPresets::itemsPerItem(),
    value: '-10%',
);

// Add to specific item
Cart::addItemCondition('laptop-001', $bulkDiscount);

// Remove from item
Cart::removeItemCondition('laptop-001', 'bulk-discount');

// Clear all conditions from item
Cart::clearItemConditions('laptop-001');
```

### Item Condition Example

```php
// Add items
Cart::add('widget-a', 'Widget A', 100.00, 1);
Cart::add('widget-b', 'Widget B', 100.00, 1);

// Apply discount only to widget-a
$discount = new CartCondition('item-sale', 'discount', TargetPresets::itemsPerItem(), '-20%');
Cart::addItemCondition('widget-a', $discount);

// Check totals
$itemA = Cart::get('widget-a');
$itemA->getSubtotal()->format(); // "$80.00" (with discount)

$itemB = Cart::get('widget-b');
$itemB->getSubtotal()->format(); // "$100.00" (no discount)

Cart::total()->format(); // "$180.00"
```

### Querying Item Conditions

```php
$item = Cart::get('laptop-001');

// Check if condition exists
if ($item->hasCondition('bulk-discount')) {
    echo "Bulk discount applied";
}

// Get all conditions
$conditions = $item->getConditions();

// Filter by type
$discounts = $item->getConditions()->discounts();
$taxes = $item->getConditions()->byType('tax');
```

## 🎯 Cart-Level Conditions

### Adding Conditions

```php
// Add single condition
$condition = new CartCondition('loyalty', 'discount', TargetPresets::cartSubtotal(), '-15%');
Cart::addCondition($condition);

// Add multiple conditions
Cart::addCondition($discount);
Cart::addTax('vat', '8%');
Cart::addShipping('standard', '10.00');
```

### Removing Conditions

```php
// Remove specific condition
Cart::removeCondition('loyalty');

// Clear all cart-level conditions
Cart::clearConditions();

// Clear specific types
$conditions = Cart::getConditions();
$conditions->byType('discount')->each(function ($condition) {
    Cart::removeCondition($condition->name);
});
```

### Querying Conditions

```php
// Get all conditions
$conditions = Cart::getConditions();

// Filter by type
$discounts = $conditions->discounts();
$taxes = $conditions->taxes();
$shipping = $conditions->byType('shipping');

// Filter by target
$subtotalConditions = $conditions->byTarget(TargetPresets::cartSubtotal());
$totalConditions = $conditions->byTarget(TargetPresets::cartGrandTotal());

// Check existence
if ($conditions->has('promo-code')) {
    echo "Promo code applied";
}
```

## ⚡ Dynamic Conditions

Dynamic conditions apply only when their rules evaluate to `true`.

### Creating Dynamic Conditions

```php
$tieredDiscount = new CartCondition(
    name: 'spend-200-save-20',
    type: 'discount',
    target: TargetPresets::cartSubtotal(),
    value: '-20.00',
    attributes: ['threshold' => 200.00],
    rules: [
        // Applies only if subtotal >= $200
        fn($cart) => $cart->getRawSubtotalWithoutConditions() >= 200.00,
    ],
);

// Register (doesn't add yet—waits for rules)
Cart::getCurrentCart()->registerDynamicCondition($tieredDiscount);

// Rules are evaluated automatically on add/update/remove
Cart::add('item-1', 'Product', 100.00, 1);  // Below threshold
Cart::evaluateDynamicConditions();           // Discount not applied

Cart::add('item-2', 'Product', 150.00, 1);  // Now above threshold
Cart::evaluateDynamicConditions();           // Discount applied!
```

### Multiple Rules

All rules must return `true`:

```php
$vipDiscount = new CartCondition(
    name: 'vip-exclusive',
    type: 'discount',
    target: TargetPresets::cartSubtotal(),
    value: '-25%',
    rules: [
        fn($cart) => auth()->check(),
        fn($cart) => auth()->user()->isVip(),
        fn($cart) => $cart->getRawSubtotalWithoutConditions() >= 100.00,
    ],
);

Cart::getCurrentCart()->registerDynamicCondition($vipDiscount);
```

### Item-Level Dynamic Conditions

```php
$bulkRule = new CartCondition(
    name: 'buy-5-get-10-off',
    type: 'discount',
    target: TargetPresets::itemsPerItem(),
    value: '-10%',
    rules: [
        fn($cart, $item) => $item->quantity >= 5,
    ],
);

// Register for specific item
$item = Cart::get('widget-001');
$item->registerDynamicCondition($bulkRule);

// Evaluates when quantity changes
Cart::update('widget-001', ['quantity' => 5]); // Discount applies
Cart::update('widget-001', ['quantity' => 4]); // Discount removed
```

### When Rules Are Evaluated

Dynamic conditions are automatically evaluated:
- After `Cart::add()`
- After `Cart::update()`
- After `Cart::remove()`

Manual evaluation:
```php
Cart::evaluateDynamicConditions();
```

## 📊 Calculation Order

Conditions execute in predictable order:

### 1. Item-Level Conditions
Applied to each item individually, sorted by `order`:

```php
Item A: $100 × 2 = $200
  → Item condition (order: 10): -10% → $180
  → Item condition (order: 20): +$5   → $185

Item B: $50 × 1 = $50
  (no item conditions)

Raw Subtotal: $185 + $50 = $235
```

### 2. Subtotal-Level Conditions
Applied to the sum of items, sorted by `order`:

```php
Subtotal: $235
  → Condition (order: 100): -15% → $199.75
  → Condition (order: 200): +$10 (shipping) → $209.75
```

### 3. Total-Level Conditions
Applied last, sorted by `order`:

```php
Subtotal: $209.75
  → Tax (order: 300): +8% → $226.53
  
Final Total: $226.53
```

### Example: Complete Calculation

```php
Cart::clear();

// Add items
Cart::add('item-1', 'Laptop', 1000.00, 2);  // $2000
Cart::add('item-2', 'Mouse', 50.00, 1);     // $50

// Item discount on laptop
$itemDiscount = new CartCondition('bulk', 'discount', TargetPresets::itemsPerItem(), '-10%', order: 10);
Cart::addItemCondition('item-1', $itemDiscount);

// Cart-wide discount (explicit order)
Cart::addCondition(new CartCondition(
    name: 'promo',
    type: 'discount',
    target: TargetPresets::cartSubtotal(),
    value: '-5%',
    order: 100
));

// Shipping
Cart::addCondition(new CartCondition(
    name: 'shipping-standard',
    type: 'shipping',
    target: TargetPresets::cartShipping(),
    value: '+15.00',
    order: 200
));

// Tax
Cart::addCondition(new CartCondition(
    name: 'vat',
    type: 'tax',
    target: TargetPresets::cartGrandTotal(),
    value: '8%',
    order: 300
));

// Calculate
/*
Item 1: $1000 × 2 = $2000
  → -10% (item discount) = $1800
  
Item 2: $50 × 1 = $50

Subtotal: $1800 + $50 = $1850
  → -5% (promo) = $1757.50
  → +$15 (shipping) = $1772.50

Total:
  → +8% (tax) = $1914.30
*/

echo Cart::total()->format(); // "$1,914.30"
```

## 🔍 Inspecting Conditions

### Get Condition Details

```php
$conditions = Cart::getConditions();

foreach ($conditions as $condition) {
    echo "Name: {$condition->name}\n";
    echo "Type: {$condition->type}\n";
    echo "Value: {$condition->value}\n";
    echo "Target: {$condition->target}\n";
    echo "Order: {$condition->order}\n";
    
    if ($condition->isDynamic()) {
        echo "Dynamic: Yes\n";
    }
    
    if ($condition->isPercentage()) {
        echo "Format: Percentage\n";
    }
}
```

### Detailed Array Export

```php
$baseValue = 100.00;
$details = Cart::getConditions()->toDetailedArray($baseValue);

/*
[
    [
        'name' => 'promo-code',
        'type' => 'discount',
        'value' => '-15%',
        'calculated_value' => -15.00,
        'is_percentage' => true,
        'is_discount' => true,
    ],
    ...
]
*/
```

## 🎓 Common Patterns

### Tiered Discounts

```php
// 10% off orders $100+
$tier1 = new CartCondition(
    'tier-1', 'discount', TargetPresets::cartSubtotal(), '-10%',
    rules: [fn($c) => $c->getRawSubtotalWithoutConditions() >= 100.00],
    order: 100
);

// 20% off orders $200+
$tier2 = new CartCondition(
    'tier-2', 'discount', TargetPresets::cartSubtotal(), '-20%',
    rules: [fn($c) => $c->getRawSubtotalWithoutConditions() >= 200.00],
    order: 90  // Lower order = higher priority
);

Cart::getCurrentCart()->registerDynamicCondition($tier1);
Cart::getCurrentCart()->registerDynamicCondition($tier2);

// Only the highest tier applies (tier-2 evaluates first)
```

### Buy X, Get Y Free

```php
// Buy 2, get 1 free (effectively 33% off)
$bogo = new CartCondition(
    'buy-2-get-1',
    'discount',
    TargetPresets::itemsPerItem(),
    '-33.33%',
    rules: [
        fn($cart, $item) => $item->quantity >= 3,
    ],
);

Cart::addItemCondition('widget-001', $bogo);
```

### Member-Only Pricing

```php
$memberDiscount = new CartCondition(
    'member-pricing',
    'discount',
    TargetPresets::cartSubtotal(),
    '-20%',
    rules: [
        fn($cart) => auth()->check(),
        fn($cart) => auth()->user()->isMember(),
    ],
);

Cart::getCurrentCart()->registerDynamicCondition($memberDiscount);
```

### Free Shipping Threshold

```php
// Free shipping on orders $50+
$shipping = new CartCondition(
    'shipping',
    'shipping',
    TargetPresets::cartShipping(),
    '+10.00',
    rules: [
        fn($cart) => $cart->getRawSubtotalWithoutConditions() < 50.00,
    ],
);

Cart::getCurrentCart()->registerDynamicCondition($shipping);
```

### Regional Tax

```php
// Different tax rates by region
$region = session('user_region', 'default');

$taxRates = [
    'CA' => 8.5,
    'NY' => 7.5,
    'TX' => 6.25,
    'default' => 0,
];

$taxRate = $taxRates[$region];

if ($taxRate > 0) {
    Cart::addTax("tax-{$region}", "+{$taxRate}%");
}
```

## ⚙️ Advanced Usage

### Custom Condition Classes

```php
namespace App\Cart\Conditions;

use AIArmada\Cart\Conditions\CartCondition;

class VipDiscount extends CartCondition
{
    public function __construct(float $percentage = 20.0)
    {
        parent::__construct(
            name: 'vip-discount',
            type: 'discount',
            target: TargetPresets::cartSubtotal(),
            value: "-{$percentage}%",
            attributes: ['tier' => 'vip'],
            rules: [
                fn($cart) => auth()->user()?->isVip() ?? false,
            ]
        );
    }
}

// Usage
Cart::addCondition(new VipDiscount(25));
```

### Condition Validation

```php
use AIArmada\Cart\Exceptions\InvalidCartConditionException;

try {
    $condition = new CartCondition(
        'invalid',
        'discount',
        TargetPresets::cartSubtotal(),
        'not-a-number'  // Invalid value
    );
} catch (InvalidCartConditionException $e) {
    logger()->error('Invalid condition', ['error' => $e->getMessage()]);
}
```

### Temporary Conditions

```php
// Apply condition for checkout preview
$previewDiscount = new CartCondition('preview', 'discount', TargetPresets::cartSubtotal(), '-10%');
Cart::addCondition($previewDiscount);

$previewTotal = Cart::total()->format();

// Remove before actual checkout
Cart::removeCondition('preview');
```

## 📚 Related Documentation

- **[Cart Operations](cart-operations.md)** – Managing items and totals
- **[Money & Currency](money-and-currency.md)** – Working with Money objects
- **[API Reference](api-reference.md)** – Complete CartCondition API
- **[Quick Examples](examples.md)** – More condition recipes

---

**Need help?** Check [Troubleshooting](troubleshooting.md) or [open a discussion](https://github.com/aiarmada/cart/discussions).

2. Subtotal-targeted conditions apply next (respecting ascending `order`).
3. Total-targeted conditions apply last.

This mirrored flow ensures cross-driver parity and repeatable amounts.

## Best Practices

- Group related conditions via prefixes (`promo:`, `shipping:`) to simplify filtering.
- Use attributes for storing display labels, coupon codes, or rate IDs.
- Remove conflicting conditions explicitly (e.g., only one active shipping condition at a time).
- Combine with metadata to persist user selections.

For a full API surface, check the [reference](api-reference.md#conditions).

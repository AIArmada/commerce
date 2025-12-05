# Advanced Voucher Types

> **Document:** 02-advanced-voucher-types.md  
> **Status:** Vision  
> **Priority:** P1

---

## Current State

The vouchers package currently supports three voucher types:

```php
enum VoucherType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';
}
```

These are converted to cart conditions via `VoucherCondition::formatVoucherValue()`:

| Type | Cart Value Format | Example |
|------|-------------------|---------|
| Percentage | `-X%` | `-10%` (10% off) |
| Fixed | `-X` | `-2000` (RM20 off) |
| FreeShipping | `+0` | Handled by shipping phase |

---

## Vision: Compound Voucher Types

### 2.1 Buy X Get Y (BOGO)

**Use Cases:**
- Buy 2 Get 1 Free
- Buy 1 Get 1 50% Off
- Buy Any 3, Cheapest Free

**Schema Design:**

```php
enum VoucherType: string
{
    // Existing
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';
    
    // New Compound Types
    case BuyXGetY = 'buy_x_get_y';
    case Tiered = 'tiered';
    case Bundle = 'bundle';
    case Cashback = 'cashback';
}
```

**BOGO Configuration (stored in `value_config` JSON):**

```json
{
  "type": "buy_x_get_y",
  "buy": {
    "quantity": 2,
    "product_matcher": {
      "type": "category",
      "categories": ["shirts"]
    }
  },
  "get": {
    "quantity": 1,
    "discount": "100%",
    "selection": "cheapest",
    "product_matcher": {
      "type": "same_as_buy"
    }
  },
  "max_applications": 3
}
```

**Cart Integration:**

```php
class BOGOVoucherCondition extends VoucherCondition
{
    public function toCartCondition(): CartCondition
    {
        // Creates item-scoped conditions for eligible products
        return Target::items()
            ->matching($this->getProductMatcher())
            ->phase(ConditionPhase::CART_SUBTOTAL)
            ->build();
    }
    
    public function calculateDiscount(Cart $cart): int
    {
        $eligibleItems = $this->findEligibleItems($cart);
        $buyItems = $this->selectBuyItems($eligibleItems);
        $getItems = $this->selectGetItems($eligibleItems, $buyItems);
        
        return $this->applyDiscountToGetItems($getItems);
    }
}
```

---

### 2.2 Tiered Discounts

**Use Cases:**
- Spend RM100+, get 5% off
- Spend RM200+, get 10% off
- Spend RM500+, get 15% off

**Tiered Configuration:**

```json
{
  "type": "tiered",
  "tiers": [
    { "min_value": 10000, "discount": "-5%", "label": "Bronze" },
    { "min_value": 20000, "discount": "-10%", "label": "Silver" },
    { "min_value": 50000, "discount": "-15%", "label": "Gold" }
  ],
  "calculation_base": "subtotal",
  "apply_highest_only": true
}
```

**Cart Integration:**

```php
class TieredVoucherCondition extends VoucherCondition
{
    public function toCartCondition(): CartCondition
    {
        $tier = $this->determineApplicableTier($this->cart);
        
        if ($tier === null) {
            return $this->createNullCondition();
        }
        
        return new CartCondition(
            name: "voucher_{$this->voucher->code}_tier_{$tier['label']}",
            type: 'voucher',
            target: Target::cart()->phase(ConditionPhase::CART_SUBTOTAL)->build(),
            value: $tier['discount'],
            attributes: ['tier' => $tier]
        );
    }
}
```

---

### 2.3 Bundle Discounts

**Use Cases:**
- Buy laptop + mouse + keyboard = 20% off bundle
- Complete outfit bundle = RM50 off
- Starter kit products together

**Bundle Configuration:**

```json
{
  "type": "bundle",
  "required_products": [
    { "sku": "LAPTOP-001", "quantity": 1 },
    { "sku": "MOUSE-001", "quantity": 1 },
    { "sku": "KEYBOARD-001", "quantity": 1 }
  ],
  "discount": "-20%",
  "allow_duplicates": false,
  "bundle_name": "Work From Home Kit"
}
```

---

### 2.4 Cashback Vouchers

**Use Cases:**
- Get 5% cashback on purchase (credited to wallet)
- Earn RM10 store credit on orders over RM100
- Loyalty rewards program

**Cashback Configuration:**

```json
{
  "type": "cashback",
  "rate": 500,
  "rate_type": "percentage",
  "max_cashback": 5000,
  "credit_to": "wallet",
  "credit_delay_hours": 168,
  "requires_order_completion": true
}
```

**Implementation Note:** Cashback vouchers don't reduce cart total at checkout. Instead, they trigger a post-checkout job to credit the user's wallet.

---

## Database Evolution

### New Columns

```php
Schema::table('vouchers', function (Blueprint $table) {
    // Compound voucher configuration
    $table->jsonb('value_config')->nullable();
    
    // For cashback vouchers
    $table->string('credit_destination')->nullable(); // 'wallet', 'next_order', 'points'
    $table->integer('credit_delay_hours')->default(0);
});
```

### Migration Strategy

1. Add `value_config` column (nullable)
2. Existing vouchers continue using `value` column
3. New compound types use `value_config` JSON
4. `VoucherCondition` factory method detects type and creates appropriate subclass

---

## Cart Package Requirements

For compound voucher types to work, the cart package needs:

| Enhancement | Purpose | Priority |
|-------------|---------|----------|
| Item-level condition calculation | BOGO discounts on specific items | P1 |
| Dynamic condition values | Tiered discount evaluation | P1 |
| Post-checkout phase | Cashback credit processing | P2 |
| Compound condition type | Multiple conditions from one source | P1 |

---

## Implementation Phases

### Phase 1: Foundation
- [ ] Add `value_config` column
- [ ] Create `CompoundVoucherCondition` base class
- [ ] Implement condition factory pattern

### Phase 2: BOGO
- [ ] `BOGOVoucherCondition` class
- [ ] Product matcher interface
- [ ] Item selection algorithms (cheapest, specific, random)

### Phase 3: Tiered
- [ ] `TieredVoucherCondition` class
- [ ] Tier evaluation logic
- [ ] Dynamic threshold checking

### Phase 4: Bundle & Cashback
- [ ] `BundleVoucherCondition` class
- [ ] `CashbackVoucherCondition` class
- [ ] Post-checkout credit job

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-targeting-engine.md](03-targeting-engine.md)

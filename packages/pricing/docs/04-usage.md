---
title: Usage
---

# Usage

## Price Calculator

The `PriceCalculator` service is the main entry point for calculating prices. It evaluates all pricing rules and returns the best applicable price.

### Basic Usage

```php
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Pricing\Data\PriceResultData;

$calculator = app(PriceCalculatorInterface::class);

// Calculate price for an item
$result = $calculator->calculate(
    item: $product,      // Must implement Priceable interface
    quantity: 5,
    context: []
);

// Access results
$result->originalPrice;      // Base price in cents
$result->finalPrice;         // Calculated final price
$result->discountAmount;     // Discount amount in cents
$result->discountPercentage; // Discount as percentage
$result->discountSource;     // Source of discount
$result->priceListName;      // Applied price list
$result->tierDescription;    // Tier description if applied
$result->promotionName;      // Promotion name if applied
$result->breakdown;          // Detailed calculation breakdown
```

### Context Options

The context array allows you to customize the calculation:

```php
$result = $calculator->calculate($product, 1, [
    'customer_id' => 'uuid-of-customer',     // For customer-specific pricing
    'segment_ids' => ['segment-1', 'segment-2'], // Customer segments
    'price_list_id' => 'uuid-of-price-list', // Specific price list
    'currency' => 'USD',                      // Override currency
    'effective_at' => now()->addDays(7),     // Future date pricing
]);
```

## Priceable Interface

Your models must implement the `Priceable` interface to work with the pricing engine:

```php
use AIArmada\Pricing\Contracts\Priceable;

class Product extends Model implements Priceable
{
    public function getBuyableIdentifier(): string
    {
        return (string) $this->id;
    }

    public function getBasePrice(): int
    {
        return $this->price; // Price in cents
    }

    public function getComparePrice(): ?int
    {
        return $this->compare_at_price;
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price !== null 
            && $this->compare_at_price > $this->price;
    }

    public function getDiscountPercentage(): ?float
    {
        if (!$this->isOnSale()) {
            return null;
        }
        
        return round(
            (($this->compare_at_price - $this->price) / $this->compare_at_price) * 100, 
            1
        );
    }
}
```

## PriceResultData

The calculator returns a `PriceResultData` DTO with helper methods:

```php
$result = $calculator->calculate($product, 1);

// Check if there's a discount
if ($result->hasDiscount()) {
    echo "You save: " . $result->getFormattedSavings();
}

// Formatted prices
echo $result->getFormattedOriginalPrice(); // "RM 50.00"
echo $result->getFormattedFinalPrice();    // "RM 45.00"
echo $result->getFormattedSavings();       // "RM 5.00"
```

## Working with Price Lists

### Creating a Price List

```php
use AIArmada\Pricing\Models\PriceList;

$priceList = PriceList::create([
    'name' => 'Wholesale',
    'slug' => 'wholesale',
    'description' => 'Prices for wholesale customers',
    'currency' => 'MYR',
    'priority' => 10,
    'is_default' => false,
    'is_active' => true,
    'starts_at' => now(),
    'ends_at' => now()->addYear(),
]);
```

### Creating Prices

```php
use AIArmada\Pricing\Models\Price;

// Create a price for a product in a price list
$price = Price::create([
    'price_list_id' => $priceList->id,
    'priceable_type' => Product::class,
    'priceable_id' => $product->id,
    'amount' => 4500, // RM 45.00
    'compare_amount' => 5000, // Original RM 50.00
    'currency' => 'MYR',
    'min_quantity' => 1,
]);

// Quantity-based price in same list
$bulkPrice = Price::create([
    'price_list_id' => $priceList->id,
    'priceable_type' => Product::class,
    'priceable_id' => $product->id,
    'amount' => 4000, // RM 40.00 for 10+ units
    'currency' => 'MYR',
    'min_quantity' => 10,
]);
```

### Querying Price Lists

```php
// Get active price lists
$activeLists = PriceList::active()->get();

// Get default price list
$default = PriceList::default()->first();

// Get price lists for owner (multitenancy)
$ownerLists = PriceList::forOwner($owner)->get();
```

## Working with Price Tiers

Price tiers provide volume discounts based on quantity:

```php
use AIArmada\Pricing\Models\PriceTier;

// Create tiers for a product
PriceTier::create([
    'tierable_type' => Product::class,
    'tierable_id' => $product->id,
    'min_quantity' => 1,
    'max_quantity' => 9,
    'amount' => 5000, // RM 50.00 each
    'currency' => 'MYR',
]);

PriceTier::create([
    'tierable_type' => Product::class,
    'tierable_id' => $product->id,
    'min_quantity' => 10,
    'max_quantity' => 49,
    'amount' => 4500, // RM 45.00 each
    'currency' => 'MYR',
    'discount_type' => 'percentage',
    'discount_value' => 10, // 10% off
]);

PriceTier::create([
    'tierable_type' => Product::class,
    'tierable_id' => $product->id,
    'min_quantity' => 50,
    'max_quantity' => null, // Unlimited
    'amount' => 4000, // RM 40.00 each
    'currency' => 'MYR',
    'discount_type' => 'percentage',
    'discount_value' => 20, // 20% off
]);
```

### Tier Helpers

```php
$tier = PriceTier::find($id);

// Check if quantity falls within tier
$tier->appliesTo(25); // true/false

// Get tier description
$tier->getDescription(); // "10-49 units" or "50+ units"

// Get discount description
$tier->getDiscountDescription(); // "10% off" or "RM 5.00 off"
```

## Calculation Priority

The price calculator evaluates prices in this order:

1. **Customer-Specific Price**
   - Looks for prices in price lists assigned to the customer

2. **Segment Price**
   - Checks prices in price lists assigned to customer segments
   - Returns the best (lowest) price across all matching segments

3. **Tier Price**
   - Applies quantity-based tiered pricing
   - Only checked when quantity > 1

4. **Promotional Price**
   - Applies active promotions (requires `aiarmada/promotions`)
   - Checks usage limits and date ranges

5. **Price List Price**
   - Uses the specified price list or default active price list

6. **Base Price**
   - Falls back to the item's base price

## Example: Complete Pricing Flow

```php
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceTier;

// Setup: Create wholesale price list
$wholesale = PriceList::create([
    'name' => 'Wholesale',
    'slug' => 'wholesale',
    'priority' => 10,
    'is_active' => true,
]);

// Add product price to wholesale list
Price::create([
    'price_list_id' => $wholesale->id,
    'priceable_type' => Product::class,
    'priceable_id' => $product->id,
    'amount' => 8000, // RM 80.00
    'min_quantity' => 1,
]);

// Add tier pricing
PriceTier::create([
    'price_list_id' => $wholesale->id,
    'tierable_type' => Product::class,
    'tierable_id' => $product->id,
    'min_quantity' => 100,
    'amount' => 7000, // RM 70.00
]);

// Calculate price
$calculator = app(PriceCalculatorInterface::class);

// Single unit - uses price list price
$result = $calculator->calculate($product, 1, [
    'price_list_id' => $wholesale->id,
]);
// finalPrice: 8000

// 100+ units - uses tier price
$result = $calculator->calculate($product, 150, [
    'price_list_id' => $wholesale->id,
]);
// finalPrice: 7000
// tierDescription: "100+ units"
```

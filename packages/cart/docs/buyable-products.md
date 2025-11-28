# Buyable Products

The Cart package provides a `BuyableInterface` contract and supporting traits for integrating your product models with the cart system. This provides automatic validation for stock, quantity limits, and purchasability.

## Quick Start

### 1. Implement BuyableInterface on Your Model

```php
use AIArmada\Cart\Contracts\BuyableInterface;
use AIArmada\Cart\Concerns\Buyable;

class Product extends Model implements BuyableInterface
{
    use Buyable;
    
    // The trait provides default implementations
    // Override methods as needed for your specific requirements
}
```

### 2. Add Products to Cart

```php
use AIArmada\Cart\Facades\Cart;

$product = Product::find(1);

// Add with validation
Cart::addBuyable($product, quantity: 2);

// Add with extra attributes
Cart::addBuyable($product, 1, [
    'gift_wrap' => true,
    'message' => 'Happy Birthday!',
]);
```

## BuyableInterface Methods

| Method | Description | Required |
|--------|-------------|----------|
| `getBuyableIdentifier()` | Unique ID (used as cart item ID) | Yes |
| `getBuyableName()` | Display name | Yes |
| `getBuyablePrice()` | Unit price as Money object | Yes |
| `canBePurchased(?int $quantity)` | Check if purchasable | Yes |
| `getBuyableAttributes()` | Extra attributes for cart item | Yes |
| `getBuyableDescription()` | For receipts/invoices | Optional |
| `getBuyableSku()` | Product SKU/code | Optional |
| `getBuyableStock()` | Available quantity | Optional |
| `getBuyableWeight()` | Weight in grams | Optional |
| `getBuyableDimensions()` | L×W×H in mm | Optional |
| `getMinimumQuantity()` | Min qty per order | Optional |
| `getMaximumQuantity()` | Max qty per order | Optional |
| `getQuantityIncrement()` | Qty must be multiple of | Optional |
| `isTaxable()` | Subject to tax? | Optional |
| `getTaxCategory()` | Tax class/category | Optional |

## The Buyable Trait

The `Buyable` trait provides sensible defaults for all interface methods. It maps common model properties:

```php
// The trait looks for these properties on your model:
$model->id           // → getBuyableIdentifier()
$model->name         // → getBuyableName()
$model->price        // → getBuyablePrice() (in cents)
$model->is_active    // → canBePurchased()
$model->stock        // → getBuyableStock()
$model->weight       // → getBuyableWeight()
$model->sku          // → getBuyableSku()
$model->min_quantity // → getMinimumQuantity()
$model->max_quantity // → getMaximumQuantity()
```

### Customizing the Trait

Override methods for custom behavior:

```php
class Product extends Model implements BuyableInterface
{
    use Buyable;
    
    // Custom pricing (e.g., sale prices)
    public function getBuyablePrice(): Money
    {
        $price = $this->sale_price ?? $this->price;
        return Money::MYR($price);
    }
    
    // Custom stock check (e.g., reserved stock)
    public function getBuyableStock(): ?int
    {
        if (!$this->tracks_inventory) {
            return null;
        }
        
        return max(0, $this->stock - $this->reserved_stock);
    }
    
    // Custom purchasability (e.g., pre-orders)
    public function canBePurchased(?int $quantity = null): bool
    {
        if (!$this->is_active && !$this->is_preorder) {
            return false;
        }
        
        // Pre-orders always available
        if ($this->is_preorder) {
            return true;
        }
        
        return parent::canBePurchased($quantity);
    }
}
```

## Cart Methods for Buyables

### Adding Products

```php
// Basic add
Cart::addBuyable($product);

// With quantity
Cart::addBuyable($product, 3);

// With extra attributes
Cart::addBuyable($product, 1, [
    'variant_id' => 'blue-xl',
    'engraving' => 'Custom Text',
]);
```

### Updating Quantity

```php
// Set absolute quantity (with validation)
Cart::updateBuyable($product, 5);

// Remove if quantity is 0 or less
Cart::updateBuyable($product, 0); // Removes item
```

### Checking Cart Contents

```php
// Check if product is in cart
if (Cart::hasBuyable($product)) {
    $item = Cart::getBuyable($product);
    echo "Quantity: " . $item->quantity;
}

// Remove product
Cart::removeBuyable($product);
```

## Validation

The cart automatically validates before adding/updating:

### Validation Checks

1. **Purchasability** - `canBePurchased()` returns true
2. **Stock** - Quantity ≤ available stock (if tracking inventory)
3. **Minimum Quantity** - Quantity ≥ `getMinimumQuantity()`
4. **Maximum Quantity** - Quantity ≤ `getMaximumQuantity()`
5. **Quantity Increment** - Quantity is multiple of `getQuantityIncrement()`

### Handling Validation Errors

```php
use AIArmada\Cart\Exceptions\ProductNotPurchasableException;

try {
    Cart::addBuyable($product, 100);
} catch (ProductNotPurchasableException $e) {
    echo $e->reason;           // "Insufficient stock"
    echo $e->productId;        // "123"
    echo $e->productName;      // "Widget"
    echo $e->requestedQuantity; // 100
    echo $e->availableStock;   // 5
}
```

### Exception Types

```php
// Out of stock
ProductNotPurchasableException::outOfStock($id, $name, $requested, $available);

// Product inactive
ProductNotPurchasableException::inactive($id, $name);

// Below minimum
ProductNotPurchasableException::minimumNotMet($id, $name, $requested, $minimum);

// Above maximum
ProductNotPurchasableException::maximumExceeded($id, $name, $requested, $maximum);

// Invalid increment
ProductNotPurchasableException::invalidIncrement($id, $name, $requested, $increment);
```

## Refreshing Prices

Refresh all cart prices when products may have changed:

```php
// Refresh prices from database
$changes = Cart::refreshBuyablePrices(function ($product) {
    return $product->fresh(); // Get fresh instance
});

// $changes = [
//     'product-1' => ['old' => 1000, 'new' => 900],
//     'product-3' => ['old' => 500, 'new' => 550],
// ]

foreach ($changes as $itemId => $change) {
    echo "Price changed from {$change['old']} to {$change['new']}";
}
```

## Validating Cart at Checkout

Validate all items before checkout:

```php
$errors = Cart::validateAllBuyables(function ($product) {
    return $product->fresh();
});

if (!empty($errors)) {
    foreach ($errors as $itemId => $exception) {
        // Handle each error
        echo "Item {$itemId}: {$exception->reason}";
        
        // Optionally remove invalid items
        Cart::remove($itemId);
    }
}
```

## Shipping Weight

Calculate total weight for shipping:

```php
$totalWeight = Cart::getTotalWeight(); // in grams

// Use with shipping calculator
$shippingCost = ShippingCalculator::forWeight($totalWeight)
    ->toAddress($address)
    ->calculate();
```

## Product Variants

Handle product variants with attributes:

```php
class ProductVariant extends Model implements BuyableInterface
{
    use Buyable;
    
    public function getBuyableIdentifier(): string
    {
        // Unique ID per variant
        return "product-{$this->product_id}-variant-{$this->id}";
    }
    
    public function getBuyableName(): string
    {
        return "{$this->product->name} ({$this->options})";
    }
    
    public function getBuyableAttributes(): array
    {
        return array_merge(parent::getBuyableAttributes(), [
            'variant_id' => $this->id,
            'product_id' => $this->product_id,
            'color' => $this->color,
            'size' => $this->size,
        ]);
    }
}
```

## Example: Full E-commerce Product

```php
class Product extends Model implements BuyableInterface
{
    use Buyable;
    
    protected $casts = [
        'is_active' => 'boolean',
        'is_taxable' => 'boolean',
        'tracks_inventory' => 'boolean',
    ];
    
    public function getBuyablePrice(): Money
    {
        // Check for active sale
        if ($this->isOnSale()) {
            return Money::MYR($this->sale_price);
        }
        
        // Check for member pricing
        if (auth()->check() && auth()->user()->is_member) {
            return Money::MYR($this->member_price ?? $this->price);
        }
        
        return Money::MYR($this->price);
    }
    
    public function canBePurchased(?int $quantity = null): bool
    {
        // Check active status
        if (!$this->is_active) {
            return false;
        }
        
        // Check publish dates
        if ($this->publish_at && $this->publish_at->isFuture()) {
            return false;
        }
        
        // Check inventory
        if ($quantity !== null && $this->tracks_inventory) {
            $available = $this->stock - $this->reserved_stock;
            return $available >= $quantity;
        }
        
        return true;
    }
    
    public function getBuyableAttributes(): array
    {
        return [
            'sku' => $this->sku,
            'weight' => $this->weight,
            'brand' => $this->brand?->name,
            'category' => $this->category?->name,
            'taxable' => $this->is_taxable,
            'tax_category' => $this->tax_category,
            'image' => $this->featured_image_url,
        ];
    }
    
    protected function isOnSale(): bool
    {
        return $this->sale_price 
            && $this->sale_starts_at?->isPast()
            && $this->sale_ends_at?->isFuture();
    }
}
```

## Related Documentation

- [Cart Operations](cart-operations.md) - Basic cart methods
- [Conditions & Pricing](conditions.md) - Discounts and taxes
- [Payment Integration](payment-integration.md) - Checkout with gateways
- [Events](events.md) - Inventory reservation via events

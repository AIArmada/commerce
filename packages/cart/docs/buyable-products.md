# Buyable Products

Integrate Eloquent models with the cart using `BuyableInterface`.

## Quick Start

### 1. Implement BuyableInterface

```php
use AIArmada\Cart\Contracts\BuyableInterface;
use Akaunting\Money\Money;

class Product extends Model implements BuyableInterface
{
    public function getBuyableIdentifier(): string
    {
        return $this->id;
    }
    
    public function getBuyableName(): string
    {
        return $this->name;
    }
    
    public function getBuyablePrice(): Money
    {
        return Money::MYR($this->price);
    }
    
    public function getBuyableMetadata(): array
    {
        return [
            'sku' => $this->sku,
            'weight' => $this->weight,
            'image' => $this->image_url,
        ];
    }
}
```

### 2. Add to Cart

```php
$product = Product::find(1);

// Add directly - extracts buyable properties automatically
Cart::addBuyable($product, 2);

// With extra metadata
Cart::addBuyable($product, 1, [
    'gift_wrap' => true,
    'message' => 'Happy Birthday!',
]);
```

### 3. Retrieve Product from Item

```php
$item = Cart::get('product-1');

// Get the original model
$product = $item->getBuyable(); // Product instance

// Refresh price from database
$currentPrice = $product->getBuyablePrice();
```

## Interface Methods

```php
interface BuyableInterface
{
    /**
     * Unique identifier (typically model ID or SKU)
     */
    public function getBuyableIdentifier(): string;
    
    /**
     * Display name for cart item
     */
    public function getBuyableName(): string;
    
    /**
     * Current price as Money object
     */
    public function getBuyablePrice(): Money;
    
    /**
     * Additional data to store with cart item
     */
    public function getBuyableMetadata(): array;
}
```

## Product Variants

```php
class ProductVariant extends Model implements BuyableInterface
{
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    
    public function getBuyableIdentifier(): string
    {
        return "variant-{$this->id}";
    }
    
    public function getBuyableName(): string
    {
        return "{$this->product->name} - {$this->name}";
    }
    
    public function getBuyablePrice(): Money
    {
        return Money::MYR($this->price ?? $this->product->price);
    }
    
    public function getBuyableMetadata(): array
    {
        return [
            'product_id' => $this->product_id,
            'variant_id' => $this->id,
            'size' => $this->size,
            'color' => $this->color,
            'sku' => $this->sku,
        ];
    }
}
```

Usage:

```php
$variant = ProductVariant::find(42);
Cart::addBuyable($variant, 1);
```

## Subscription Products

```php
class SubscriptionPlan extends Model implements BuyableInterface
{
    public function getBuyableIdentifier(): string
    {
        return "plan-{$this->id}";
    }
    
    public function getBuyableName(): string
    {
        return "{$this->name} ({$this->billing_cycle})";
    }
    
    public function getBuyablePrice(): Money
    {
        return Money::MYR($this->price);
    }
    
    public function getBuyableMetadata(): array
    {
        return [
            'billing_cycle' => $this->billing_cycle,
            'features' => $this->features,
            'trial_days' => $this->trial_days,
        ];
    }
}
```

## Digital Downloads

```php
class DigitalProduct extends Model implements BuyableInterface
{
    public function getBuyableMetadata(): array
    {
        return [
            'type' => 'digital',
            'file_size' => $this->file_size,
            'format' => $this->format,
            'download_limit' => 3,
        ];
    }
}
```

## Price Refresh

Prices may change after items are added. Refresh before checkout:

```php
// Check single item
$item = Cart::get('product-1');
$product = Product::find($item->getMetadata('product_id'));
$storedPrice = $item->getPrice();
$currentPrice = $product->getBuyablePrice();

if (!$storedPrice->equals($currentPrice)) {
    Cart::update('product-1', ['price' => $currentPrice]);
}

// Refresh all items
foreach (Cart::getContent() as $item) {
    $buyable = $item->getBuyable();
    if ($buyable && !$item->getPrice()->equals($buyable->getBuyablePrice())) {
        Cart::update($item->getIdentifier(), [
            'price' => $buyable->getBuyablePrice(),
        ]);
    }
}
```

## Stock Validation

Validate stock before checkout:

```php
class CartValidator
{
    public function validateStock(): array
    {
        $errors = [];
        
        foreach (Cart::getContent() as $item) {
            $product = Product::find($item->getMetadata('product_id'));
            
            if (!$product) {
                $errors[] = "Product no longer available: {$item->getName()}";
                continue;
            }
            
            if ($product->stock < $item->getQuantity()) {
                $errors[] = "Insufficient stock for {$item->getName()}: " .
                           "requested {$item->getQuantity()}, available {$product->stock}";
            }
        }
        
        return $errors;
    }
}
```

## Mixed Buyables

Cart can hold different buyable types:

```php
$product = Product::find(1);
$variant = ProductVariant::find(42);
$subscription = SubscriptionPlan::find(3);
$addon = ServiceAddon::find(5);

Cart::addBuyable($product, 2);
Cart::addBuyable($variant, 1);
Cart::addBuyable($subscription, 1);
Cart::addBuyable($addon, 1);

// Filter by type
$physicalItems = Cart::getContent()->filter(
    fn ($item) => $item->getMetadata('type') !== 'digital'
);
```

## Service Container Integration

Register a buyable resolver:

```php
// AppServiceProvider
$this->app->bind('buyable.resolver', function () {
    return new class {
        public function resolve(string $identifier): ?BuyableInterface
        {
            [$type, $id] = explode('-', $identifier, 2);
            
            return match ($type) {
                'product' => Product::find($id),
                'variant' => ProductVariant::find($id),
                'plan' => SubscriptionPlan::find($id),
                default => null,
            };
        }
    };
});
```

## Testing

```php
it('adds buyable product to cart', function () {
    $product = Product::factory()->create([
        'name' => 'Test Product',
        'price' => 5999, // RM59.99
    ]);
    
    Cart::addBuyable($product, 2);
    
    expect(Cart::count())->toBe(2);
    expect(Cart::total()->getAmount())->toBe(11998);
});

it('stores buyable metadata', function () {
    $product = Product::factory()->create(['sku' => 'TEST-001']);
    
    Cart::addBuyable($product, 1);
    
    $item = Cart::get($product->getBuyableIdentifier());
    expect($item->getMetadata('sku'))->toBe('TEST-001');
});
```

## Next Steps

- [Cart Operations](cart-operations.md) – Item management
- [Payment Integration](payment-integration.md) – Checkout flow
- [API Reference](api-reference.md) – Complete methods

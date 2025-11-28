# Examples

Real-world patterns for common e-commerce scenarios.

## Basic E-commerce Flow

```php
// Product page: Add to cart
Cart::add($product->sku, $product->name, $product->price, 1, [
    'image' => $product->thumbnail,
    'weight' => $product->weight,
]);

// Cart page: Update quantity
Cart::update('sku-123', ['quantity' => 3]);

// Apply discount
Cart::applyCondition(new CartCondition([
    'name' => 'SUMMER20',
    'type' => 'discount',
    'target' => 'subtotal',
    'value' => '-20%',
]));

// Checkout
$payment = Chip::createPurchase([
    'amount' => Cart::total()->getAmount(),
    'currency' => 'MYR',
    'line_items' => Cart::toLineItems(),
]);
```

## Product Variants

```php
class ProductVariant extends Model implements BuyableInterface
{
    public function getBuyableIdentifier(): string
    {
        return "variant-{$this->id}";
    }
    
    public function getBuyableName(): string
    {
        return "{$this->product->name} - {$this->option_label}";
    }
    
    public function getBuyablePrice(): Money
    {
        return Money::MYR($this->price_adjustment + $this->product->base_price);
    }
    
    public function getBuyableMetadata(): array
    {
        return [
            'product_id' => $this->product_id,
            'size' => $this->size,
            'color' => $this->color,
            'sku' => $this->sku,
        ];
    }
}

// Usage
$variant = ProductVariant::find(42);
Cart::addBuyable($variant, 2);
```

## Subscription Products

```php
// Add subscription
Cart::add('plan-pro', 'Pro Plan (Monthly)', Money::MYR(9900), 1, [
    'type' => 'subscription',
    'billing_cycle' => 'monthly',
    'trial_days' => 14,
]);

// At checkout, handle separately
$subscriptions = Cart::getContent()->filter(
    fn ($item) => $item->getMetadata('type') === 'subscription'
);

foreach ($subscriptions as $item) {
    $user->subscribe($item->getIdentifier());
}
```

## Gift Cards

```php
// Add gift card
Cart::add('giftcard-50', 'Gift Card RM50', Money::MYR(5000), 1, [
    'type' => 'giftcard',
    'recipient_email' => $request->email,
    'message' => $request->message,
]);

// Redeem gift card (as condition)
$giftCard = GiftCard::where('code', $code)->first();

Cart::applyCondition(new CartCondition([
    'name' => "Gift Card: {$code}",
    'type' => 'giftcard',
    'target' => 'total',
    'value' => "-{$giftCard->balance}",
]));
```

## Tiered Pricing

```php
// Dynamic quantity-based pricing
$quantity = Cart::get('bulk-item')?->getQuantity() ?? 0;

$pricePerUnit = match (true) {
    $quantity >= 100 => Money::MYR(800),  // RM8.00
    $quantity >= 50 => Money::MYR(850),   // RM8.50
    $quantity >= 10 => Money::MYR(900),   // RM9.00
    default => Money::MYR(1000),           // RM10.00
};

Cart::update('bulk-item', ['price' => $pricePerUnit]);
```

## Shipping Calculator

```php
class ShippingCalculator
{
    public function calculate(): CartCondition
    {
        $totalWeight = Cart::getContent()->sum(
            fn ($item) => $item->getMetadata('weight', 0) * $item->getQuantity()
        );
        
        $address = Cart::getMetadata('shipping_address');
        $zone = $this->getShippingZone($address);
        
        $cost = match ($zone) {
            'local' => $totalWeight * 5,
            'domestic' => $totalWeight * 10,
            'international' => $totalWeight * 25,
            default => 0,
        };
        
        return new CartCondition([
            'name' => 'Shipping',
            'type' => 'shipping',
            'target' => 'total',
            'value' => $cost,
        ]);
    }
}

// Apply
$shipping = (new ShippingCalculator())->calculate();
Cart::applyCondition($shipping);
```

## Free Shipping Threshold

```php
// Auto-apply free shipping
$subtotal = Cart::subtotal();
$freeShippingThreshold = Money::MYR(15000); // RM150

if ($subtotal->greaterThanOrEqual($freeShippingThreshold)) {
    Cart::removeCondition('Shipping');
    Cart::applyCondition(new CartCondition([
        'name' => 'Free Shipping',
        'type' => 'shipping',
        'target' => 'total',
        'value' => 0,
    ]));
}
```

## Multi-Vendor Marketplace

```php
// Add items with vendor info
Cart::add('vendor1-item1', 'Product A', Money::MYR(5000), 1, [
    'vendor_id' => 1,
    'vendor_name' => 'Shop A',
]);

Cart::add('vendor2-item1', 'Product B', Money::MYR(3000), 1, [
    'vendor_id' => 2,
    'vendor_name' => 'Shop B',
]);

// Group by vendor for split payments
$byVendor = Cart::getContent()->groupBy(
    fn ($item) => $item->getMetadata('vendor_id')
);

foreach ($byVendor as $vendorId => $items) {
    $vendorTotal = $items->sum(fn ($item) => $item->getLineTotal()->getAmount());
    
    // Process vendor payment
    VendorPayout::create([
        'vendor_id' => $vendorId,
        'amount' => $vendorTotal,
    ]);
}
```

## Save for Later

```php
// Move to saved list
public function saveForLater(string $itemId): void
{
    $item = Cart::get($itemId);
    
    Cart::instance('saved')->add(
        $item->getIdentifier(),
        $item->getName(),
        $item->getPrice(),
        $item->getQuantity(),
        $item->getMetadata()
    );
    
    Cart::remove($itemId);
}

// Move back to cart
public function moveToCart(string $itemId): void
{
    $item = Cart::instance('saved')->get($itemId);
    
    Cart::add(
        $item->getIdentifier(),
        $item->getName(),
        $item->getPrice(),
        $item->getQuantity(),
        $item->getMetadata()
    );
    
    Cart::instance('saved')->remove($itemId);
}
```

## Wishlist

```php
// Separate wishlist instance
Cart::instance('wishlist')->add($product->sku, $product->name, $product->price, 1);

// Check if in wishlist
$isWishlisted = Cart::instance('wishlist')->has($product->sku);

// Move all to cart
foreach (Cart::instance('wishlist')->getContent() as $item) {
    Cart::add(
        $item->getIdentifier(),
        $item->getName(),
        $item->getPrice(),
        1,
        $item->getMetadata()
    );
}
Cart::instance('wishlist')->clear();
```

## Cart Preview Widget

```php
// API endpoint for cart widget
Route::get('/api/cart/summary', function () {
    return response()->json([
        'count' => Cart::count(),
        'subtotal' => Cart::subtotal()->format(),
        'items' => Cart::getContent()->take(3)->map(fn ($item) => [
            'name' => $item->getName(),
            'quantity' => $item->getQuantity(),
            'image' => $item->getMetadata('image'),
        ]),
    ]);
});
```

## Abandoned Cart Recovery

```php
// Store email with cart
Cart::setMetadata('email', $request->email);
Cart::setMetadata('last_activity', now());

// Job: Find abandoned carts
class SendAbandonedCartReminders implements ShouldQueue
{
    public function handle(): void
    {
        $abandoned = DB::table('carts')
            ->where('updated_at', '<', now()->subHours(24))
            ->whereNotNull('metadata->email')
            ->get();
        
        foreach ($abandoned as $cart) {
            Mail::to($cart->metadata['email'])
                ->send(new AbandonedCartReminder($cart));
        }
    }
}
```

## Stock Reservation

```php
// Reserve on add
Event::listen(ItemAdded::class, function ($event) {
    StockReservation::create([
        'product_sku' => $event->item->getIdentifier(),
        'quantity' => $event->item->getQuantity(),
        'cart_id' => Cart::getIdentifier(),
        'expires_at' => now()->addMinutes(30),
    ]);
});

// Release on remove
Event::listen(ItemRemoved::class, function ($event) {
    StockReservation::where('cart_id', Cart::getIdentifier())
        ->where('product_sku', $event->item->getIdentifier())
        ->delete();
});

// Clear expired reservations (scheduled job)
StockReservation::where('expires_at', '<', now())->delete();
```

## Testing Cart Scenarios

```php
it('applies tiered discount', function () {
    // Buy 5, get 10% off
    Cart::add('sku-1', 'Product', Money::MYR(1000), 5);
    
    Cart::applyCondition(new CartCondition([
        'name' => 'Bulk Discount',
        'type' => 'discount',
        'target' => 'subtotal',
        'value' => '-10%',
    ]));
    
    expect(Cart::total()->getAmount())->toBe(4500); // 5 × RM10 - 10%
});

it('handles complex checkout', function () {
    Cart::add('product-1', 'Widget', Money::MYR(5000), 2);
    Cart::add('product-2', 'Gadget', Money::MYR(7500), 1);
    
    Cart::applyCondition(new CartCondition([
        'name' => 'Member Discount',
        'type' => 'discount',
        'target' => 'subtotal',
        'value' => '-15%',
    ]));
    
    Cart::applyCondition(new CartCondition([
        'name' => 'Shipping',
        'type' => 'shipping',
        'target' => 'total',
        'value' => 1500,
    ]));
    
    Cart::applyCondition(new CartCondition([
        'name' => 'SST',
        'type' => 'tax',
        'target' => 'total',
        'value' => '8%',
    ]));
    
    // (RM100 + RM75) × 0.85 + RM15 × 1.08
    expect(Cart::total()->getAmount())->toBe(16929);
});
```

## Next Steps

- [API Reference](api-reference.md) – Complete method list
- [Troubleshooting](troubleshooting.md) – Common issues

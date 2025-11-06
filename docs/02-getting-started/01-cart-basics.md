# Getting Started: Cart Basics

Learn how to build a shopping cart in 10 minutes using AIArmada Cart.

## Basic Cart Operations

### Adding Items

```php
use AIArmada\Cart\Facades\Cart;

// Simple item
Cart::add(
    id: 'prod-001',
    name: 'Blue T-Shirt',
    price: 2999, // RM 29.99
    quantity: 2
);

// Item with attributes
Cart::add(
    id: 'prod-001',
    name: 'Blue T-Shirt',
    price: 2999,
    quantity: 2,
    attributes: [
        'size' => 'L',
        'color' => 'Blue',
        'sku' => 'SHIRT-BLUE-L',
    ]
);

// Item with associatedModel
Cart::add(
    id: 'prod-001',
    name: 'Blue T-Shirt',
    price: 2999,
    quantity: 2,
    attributes: ['size' => 'L'],
    associatedModel: Product::class
);
```

### Retrieving Cart Items

```php
// Get all items
$items = Cart::content(); // Collection of CartItem

// Get specific item
$item = Cart::get('prod-001');

// Check if item exists
if (Cart::has('prod-001')) {
    // Item exists
}

// Get item count
$count = Cart::count(); // Total unique items

// Get total quantity
$quantity = Cart::totalQuantity(); // Sum of all quantities
```

### Updating Items

```php
// Update quantity
Cart::update('prod-001', ['quantity' => 5]);

// Update price
Cart::update('prod-001', ['price' => 2499]);

// Update attributes
Cart::update('prod-001', ['attributes' => ['color' => 'Red']]);

// Update multiple fields
Cart::update('prod-001', [
    'quantity' => 3,
    'price' => 2499,
    'attributes' => ['color' => 'Red', 'size' => 'XL'],
]);
```

### Removing Items

```php
// Remove specific item
Cart::remove('prod-001');

// Clear entire cart
Cart::clear();

// Remove all items but keep conditions
Cart::clearItems();
```

## Cart Totals

### Calculating Totals

```php
use Akaunting\Money\Money;

// Subtotal (before conditions)
$subtotal = Cart::subtotal(); // Money instance

// Total (after conditions)
$total = Cart::total(); // Money instance

// Get raw amounts
$subtotalCents = Cart::subtotal()->getAmount(); // 5998 (RM 59.98)
$totalCents = Cart::total()->getAmount();

// Format for display
echo Cart::subtotal()->format(); // "RM59.98"
echo Cart::total()->format(); // "RM53.98"
```

### Tax Calculation

```php
// Add tax condition
Cart::applyCondition([
    'name' => 'SST',
    'type' => 'tax',
    'target' => 'subtotal',
    'value' => 6, // 6%
]);

// Tax amount
$taxAmount = Cart::conditionTotal('tax');
echo $taxAmount->format(); // "RM3.60"
```

## Cart Conditions

Conditions modify cart totals (discounts, taxes, shipping, fees).

### Applying Conditions

```php
// Percentage discount
Cart::applyCondition([
    'name' => 'Holiday Sale',
    'type' => 'discount',
    'target' => 'subtotal', // or 'total'
    'value' => -10, // -10%
]);

// Fixed amount discount
Cart::applyCondition([
    'name' => 'First Order',
    'type' => 'discount',
    'target' => 'subtotal',
    'value' => -500, // RM 5.00 off
    'is_percentage' => false,
]);

// Shipping fee
Cart::applyCondition([
    'name' => 'Standard Shipping',
    'type' => 'shipping',
    'target' => 'total',
    'value' => 1000, // RM 10.00
    'is_percentage' => false,
]);

// Item-level condition
Cart::applyItemCondition('prod-001', [
    'name' => 'Bundle Discount',
    'type' => 'discount',
    'value' => -5, // -5%
]);
```

### Managing Conditions

```php
// Get all conditions
$conditions = Cart::conditions(); // Collection

// Get condition total by type
$discountTotal = Cart::conditionTotal('discount');
$shippingTotal = Cart::conditionTotal('shipping');

// Remove condition
Cart::removeCondition('Holiday Sale');

// Clear all conditions
Cart::clearConditions();
```

### Condition Order

Conditions apply in this order:
1. **Item conditions** → Item totals
2. **Subtotal conditions** → Subtotal
3. **Total conditions** → Final total

```php
// Example calculation flow
Cart::add('prod-001', 'Product', 10000, 2); // RM 200.00

// Item condition: -10%
Cart::applyItemCondition('prod-001', [
    'name' => 'Item Discount',
    'type' => 'discount',
    'value' => -10,
]);
// Item total: RM 180.00

// Subtotal condition: -RM 20
Cart::applyCondition([
    'name' => 'Coupon',
    'type' => 'discount',
    'target' => 'subtotal',
    'value' => -2000,
    'is_percentage' => false,
]);
// Subtotal: RM 160.00

// Total condition: +6% tax
Cart::applyCondition([
    'name' => 'SST',
    'type' => 'tax',
    'target' => 'total',
    'value' => 6,
]);
// Final total: RM 169.60
```

## Cart Instances

Manage multiple carts per user (cart, wishlist, saved-for-later).

```php
// Default instance
Cart::add('prod-001', 'Product', 2999, 1);

// Wishlist instance
Cart::instance('wishlist')->add('prod-002', 'Product 2', 4999, 1);

// Switch instances
Cart::instance('wishlist')->content();
Cart::instance('cart')->content();

// Get current instance name
$current = Cart::currentInstance(); // 'cart'

// Destroy instance
Cart::instance('wishlist')->destroy();
```

## User Association

Associate carts with authenticated users for persistence.

```php
use Illuminate\Support\Facades\Auth;

// Store cart for logged-in user
if (Auth::check()) {
    Cart::store(Auth::id());
}

// Restore cart after login
Cart::restore(Auth::id());

// Merge guest cart with user cart after login
Cart::restore(Auth::id(), merge: true);

// Clear stored cart
Cart::erase(Auth::id());
```

## Storage Drivers

### Session Storage (Default)

```php
// config/cart.php
'storage_driver' => 'session',
```

**Pros**: Simple, no additional setup
**Cons**: Lost on session expiry, not shared across devices

### Cache Storage

```php
// config/cart.php
'storage_driver' => 'cache',
'cache' => [
    'ttl' => 3600, // 1 hour
],
```

**Pros**: Fast, good for high traffic
**Cons**: Requires cache infrastructure (Redis)

### Database Storage

```php
// config/cart.php
'storage_driver' => 'database',
'database' => [
    'enable_optimistic_locking' => true,
],
```

**Pros**: Persistent, survives sessions, supports concurrency
**Cons**: Slower than cache, requires migrations

## Practical Examples

### Complete Checkout Flow

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Support\Facades\Auth;

// 1. Add products
Cart::add('prod-001', 'T-Shirt', 2999, 2);
Cart::add('prod-002', 'Jeans', 7999, 1);

// 2. Apply voucher
$voucher = Voucher::findByCode('SAVE10');
if ($voucher && $voucher->canBeRedeemed()) {
    Cart::applyCondition([
        'name' => $voucher->name,
        'type' => 'discount',
        'target' => 'subtotal',
        'value' => -$voucher->discount_amount,
        'is_percentage' => $voucher->type === 'percentage',
    ]);
}

// 3. Calculate shipping
$shipping = calculateShipping(Auth::user()->address);
Cart::applyCondition([
    'name' => 'Shipping',
    'type' => 'shipping',
    'target' => 'total',
    'value' => $shipping,
    'is_percentage' => false,
]);

// 4. Add tax
Cart::applyCondition([
    'name' => 'SST',
    'type' => 'tax',
    'target' => 'total',
    'value' => 6,
]);

// 5. Get final total
$total = Cart::total();

// 6. Create order
$order = Order::create([
    'user_id' => Auth::id(),
    'total' => $total->getAmount(),
    'currency' => $total->getCurrency(),
]);

// 7. Save items
foreach (Cart::content() as $item) {
    $order->items()->create([
        'product_id' => $item->id,
        'name' => $item->name,
        'price' => $item->price,
        'quantity' => $item->quantity,
        'attributes' => $item->attributes,
    ]);
}

// 8. Clear cart
Cart::clear();
```

### Ajax Cart Updates

```php
// routes/web.php
Route::post('/cart/add', function (Request $request) {
    $validated = $request->validate([
        'id' => 'required|string',
        'name' => 'required|string',
        'price' => 'required|integer|min:0',
        'quantity' => 'required|integer|min:1',
    ]);
    
    Cart::add(
        $validated['id'],
        $validated['name'],
        $validated['price'],
        $validated['quantity']
    );
    
    return response()->json([
        'count' => Cart::count(),
        'total' => Cart::total()->format(),
    ]);
});

Route::post('/cart/update/{id}', function (Request $request, string $id) {
    $validated = $request->validate([
        'quantity' => 'required|integer|min:0',
    ]);
    
    if ($validated['quantity'] === 0) {
        Cart::remove($id);
    } else {
        Cart::update($id, ['quantity' => $validated['quantity']]);
    }
    
    return response()->json([
        'count' => Cart::count(),
        'total' => Cart::total()->format(),
    ]);
});
```

## Next Steps

- **[Payment Integration](02-payment-integration.md)**: Accept payments with CHIP
- **[Voucher System](03-voucher-system.md)**: Add discount codes
- **[Cart Package Reference](../03-packages/01-cart.md)**: Complete API documentation

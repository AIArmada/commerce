# Cart Operations

Complete guide to the `Cart` facade and its daily operations. This document covers everything from adding items to advanced querying and instance management.

## 🛒 Managing Items

### Adding Items

Add items one at a time or in batches:

```php
use AIArmada\Cart\Facades\Cart;

// Add single item
Cart::add(
    id: 'laptop-001',
    name: 'MacBook Pro 16"',
    price: 2499.00,
    quantity: 1,
    attributes: [
        'sku' => 'MBP16-2024',
        'color' => 'Space Gray',
        'specs' => ['ram' => '32GB', 'storage' => '1TB'],
    ],
    conditions: null,
    associatedModel: App\Models\Product::class,
);

// Add multiple items
Cart::add([
    [
        'id' => 'mouse-001',
        'name' => 'Magic Mouse',
        'price' => 99.00,
        'quantity' => 1,
    ],
    [
        'id' => 'keyboard-001',
        'name' => 'Magic Keyboard',
        'price' => 149.00,
        'quantity' => 1,
    ],
]);
```

**Parameter details:**
- `id` (string, required) – Unique identifier per instance
- `name` (string, required) – Display name
- `price` (float|int|string, required) – Price per unit (sanitized automatically)
- `quantity` (int, required) – Initial quantity (must be positive)
- `attributes` (array, optional) – Additional metadata (becomes `Collection`)
- `conditions` (array|null, optional) – Item-level conditions
- `associatedModel` (string|object, optional) – Eloquent model class or instance

**Price sanitization:**
String prices are automatically cleaned:
```php
Cart::add('item-1', 'Product', '1,234.56', 1);  // → 1234.56
Cart::add('item-2', 'Product', '$ 99.00', 1);   // → 99.00
Cart::add('item-3', 'Product', '49.99 USD', 1); // → 49.99
```

### Updating Items

Update quantity, price, name, or attributes:

```php
// Absolute quantity update
Cart::update('laptop-001', [
    'quantity' => ['value' => 2], // Set to exactly 2
]);

// Relative quantity update
Cart::update('laptop-001', [
    'quantity' => 1, // Add 1 to current quantity
]);

// Update price
Cart::update('laptop-001', [
    'price' => 2299.00,
]);

// Update name
Cart::update('laptop-001', [
    'name' => 'MacBook Pro 16" (Refurbished)',
]);

// Update attributes (merges with existing)
Cart::update('laptop-001', [
    'attributes' => ['condition' => 'refurbished'],
]);

// Update multiple properties
Cart::update('laptop-001', [
    'quantity' => ['value' => 3],
    'price' => 2199.00,
    'name' => 'MacBook Pro 16" (Sale)',
    'attributes' => ['sale' => true, 'discount' => '10%'],
]);
```

**Auto-remove on zero:**
If an update reduces quantity to ≤ 0, the item is automatically removed.

### Removing Items

```php
// Remove specific item
Cart::remove('laptop-001');

// Clear entire cart
Cart::clear();

// Check if cart is empty
if (Cart::isEmpty()) {
    return redirect()->route('shop');
}
```

### Checking Item Existence

```php
// Check if item exists
if (Cart::has('laptop-001')) {
    echo "Item is in cart";
}

// Get item or null
$item = Cart::get('laptop-001');
if ($item) {
    echo $item->name;
}
```

## 💰 Totals & Calculations

All totals return `Akaunting\Money\Money` objects for precision.

### Available Total Methods

| Method | Description |
|--------|-------------|
| `Cart::total()` | Final total with all conditions applied |
| `Cart::subtotal()` | Subtotal with item and subtotal conditions |
| `Cart::subtotalWithoutConditions()` | Raw sum of item prices × quantities |
| `Cart::totalWithoutConditions()` | Alias of `subtotalWithoutConditions()` |
| `Cart::savings()` | Positive difference between subtotal and total |

### Working with Money Objects

```php
$total = Cart::total();

// For display
echo $total->format();        // "$2,648.00"
echo $total->format('en_GB'); // "£2,648.00"

// For calculations
$amount = $total->getAmount(); // 2648.00 (float)
$value = $total->getValue();   // "2648.00" (string)

// For comparisons
if ($total->greaterThan(Money::MYR(100000))) {
    Cart::addDiscount('bulk-order', '5%');
}
```

### Quantity Methods

```php
// Total quantity across all items
$quantity = Cart::count(); // e.g., 5

// Number of unique line items
$items = Cart::countItems(); // e.g., 3

// Example:
// Item A: quantity 2
// Item B: quantity 3
// count() = 5, countItems() = 3
```

### Calculation Example

```php
Cart::clear();

// Add items
Cart::add('item-1', 'Laptop', 1000.00, 2); // $2000
Cart::add('item-2', 'Mouse', 50.00, 3);    // $150

// Apply conditions
Cart::addDiscount('promo', '10%');         // -$215
Cart::addShipping('standard', '15.00');    // +$15
Cart::addTax('vat', '8%');                 // +$163.20

// Check totals
echo Cart::subtotalWithoutConditions()->format(); // "$2,150.00"
echo Cart::subtotal()->format();                  // "$1,950.00" (after discount & shipping)
echo Cart::total()->format();                     // "$2,106.00" (after tax)
echo Cart::savings()->format();                   // "$215.00" (discount amount)
```

## 📦 Accessing Items

### Get All Items

```php
// Returns CartCollection (extends Illuminate\Support\Collection)
$items = Cart::getItems();

// Iterate items
foreach ($items as $item) {
    echo "{$item->id}: {$item->name} - ";
    echo "{$item->quantity} × {$item->getPrice()->format()}\n";
}

// Collection methods available
$laptops = $items->filter(fn($item) => str_contains($item->name, 'Laptop'));
$total = $items->sum(fn($item) => $item->getSubtotal()->getAmount());
```

### Get Single Item

```php
$item = Cart::get('laptop-001');

if ($item) {
    echo "ID: {$item->id}\n";
    echo "Name: {$item->name}\n";
    echo "Price: {$item->price}\n";
    echo "Quantity: {$item->quantity}\n";
    echo "Subtotal: {$item->getSubtotal()->format()}\n";
    
    // Access attributes
    echo "Color: {$item->attributes->get('color')}\n";
    
    // Access conditions
    $discounts = $item->getConditions()->discounts();
}
```

### CartItem Properties & Methods

**Properties:**
- `id` (string) – Unique identifier
- `name` (string) – Display name
- `price` (float) – Base price per unit
- `quantity` (int) – Current quantity
- `attributes` (Collection) – Additional metadata
- `conditions` (CartConditionCollection) – Applied conditions
- `associatedModel` (string|null) – Model class name

**Methods:**
```php
$item = Cart::get('laptop-001');

// Pricing
$item->getPrice();                  // Money object
$item->getSubtotal();               // Money (price × quantity)
$item->getDiscountAmount();         // Money (total discounts)

// Conditions
$item->hasCondition('bulk-discount');
$item->getConditions();             // CartConditionCollection
$item->addCondition($condition);
$item->removeCondition('bulk-discount');
$item->clearConditions();

// Attributes
$item->attributes->get('color');
$item->attributes->has('warranty');

// Immutable updates
$updated = $item->with([
    'quantity' => 5,
    'price' => 999.00,
]);
```

## 🔍 Searching & Filtering

The `CartCollection` provides powerful filtering:

```php
$items = Cart::getItems();

// Filter by attribute
$largeItems = $items->filterByAttribute('size', 'L');

// Filter by price range
$expensive = $items->filter(fn($item) => $item->price > 1000);

// Sort by price
$sorted = $items->sortByPrice('desc');

// Sort by name
$alphabetical = $items->sortBy('name');

// Get statistics
$stats = $items->getStatistics();
/*
[
    'total_items' => 10,
    'total_quantity' => 25,
    'average_price' => 149.50,
    'total_value' => 3737.50,
]
*/
```

### Custom Search

```php
// Search with callback
$results = Cart::search(function ($item) {
    return $item->price > 100 && $item->attributes->get('category') === 'electronics';
});

// Search by condition
$discounted = Cart::search(fn($item) => $item->hasCondition('sale'));
```

## 🏷️ Metadata

Metadata stores contextual information about the cart (e.g., shipping method, coupon code, customer notes).

### Managing Metadata

```php
// Set single value
Cart::setMetadata('shipping_method', 'express');

// Set multiple values
Cart::setMetadataBatch([
    'coupon_code' => 'SPRING2024',
    'gift_message' => 'Happy Birthday!',
    'delivery_instructions' => 'Leave at door',
]);

// Get value
$method = Cart::getMetadata('shipping_method'); // 'express'
$code = Cart::getMetadata('coupon_code');       // 'SPRING2024'

// Get with default
$notes = Cart::getMetadata('notes', 'No notes'); // 'No notes' if not set

// Check existence
if (Cart::hasMetadata('coupon_code')) {
    echo "Coupon applied";
}

// Remove metadata
Cart::removeMetadata('gift_message');
```

### Metadata Events

Metadata operations dispatch events:
- `MetadataAdded` – When key is set
- `MetadataRemoved` – When key is removed
- `CartUpdated` – Consolidated event

See [Event System](events.md) for details.

## 🎯 Multiple Instances

Use instances to maintain separate carts for different purposes.

### Switching Instances

```php
// Default instance (implicit)
Cart::add('item-1', 'Laptop', 999.00);

// Switch to wishlist
Cart::instance('wishlist')->add('item-2', 'Monitor', 499.00);

// Switch to quote basket
Cart::instance('quote')->add('item-3', 'Keyboard', 129.00);

// Check which instance is active
echo Cart::instance(); // 'quote'

// Switch back to default
Cart::instance('default');
echo Cart::instance(); // 'default'
```

### Working with Instances

```php
// Get cart counts per instance
$mainCount = Cart::instance('default')->count();
$wishlistCount = Cart::instance('wishlist')->count();

// Clear specific instance
Cart::instance('wishlist')->clear();

// Get instance without switching globally
$quoteCart = Cart::getCartInstance('quote');
$quoteTotal = $quoteCart->total()->format();

// Get another user's cart instance
$userCart = Cart::getCartInstance('default', 'user-123');
$userItems = $userCart->getItems();
```

### Loading Carts by Identifier

When you need to work with specific user carts or load carts from external systems:

```php
// Switch to a specific user's cart
Cart::setIdentifier('user-456');
Cart::add('item-1', 'Product', 10.00);

// Return to current user's cart
Cart::forgetIdentifier();

// Get current identifier
$currentId = Cart::getIdentifier();

// Load cart by UUID (from payment/order system)
$cartUuid = $payment->cart_id;
$cart = Cart::getById($cartUuid);

if ($cart) {
    $total = $cart->total();
    $items = $cart->getItems();
}
```

### Instance Independence

Each instance has independent:
- Items
- Conditions
- Metadata
- Totals

```php
// These don't interfere with each other
Cart::instance('default')->add('item-1', 'A', 10.00);
Cart::instance('wishlist')->add('item-1', 'B', 20.00);

Cart::instance('default')->get('item-1')->name;   // 'A'
Cart::instance('wishlist')->get('item-1')->name;  // 'B'
```

### Common Instance Names

| Instance | Purpose |
|----------|---------|
| `default` | Main shopping cart |
| `wishlist` | Saved for later |
| `quote` | B2B quotes/proposals |
| `saved` | Saved items |
| `comparison` | Product comparisons |

## 🔗 Associating Models

Link cart items to Eloquent models for easy reference.

### Setting Associated Models

```php
use App\Models\Product;

// Pass model class
Cart::add('prod-1', 'Laptop', 999.00, 1, [], null, Product::class);

// Or pass model instance
$product = Product::find(1);
Cart::add('prod-1', 'Laptop', 999.00, 1, [], null, $product);
```

### Retrieving Models

```php
$item = Cart::get('prod-1');

// Get model class name
$modelClass = $item->associatedModel; // "App\Models\Product"

// Resolve the model
if ($modelClass) {
    $product = app($modelClass)->find($item->id);
    // Or use your repository
}
```

**Note:** The cart stores the model class name (string), not the full model. This keeps storage lean. You're responsible for rehydrating models when needed.

## 📊 Content Snapshot

Get a complete cart snapshot for APIs or debugging:

```php
$snapshot = Cart::content();
/*
[
    'identifier' => 'user-42',
    'instance' => 'default',
    'items' => [
        'laptop-001' => [
            'id' => 'laptop-001',
            'name' => 'MacBook Pro',
            'price' => 2499.00,
            'quantity' => 1,
            'attributes' => ['color' => 'Space Gray'],
            'subtotal' => 2499.00,
        ],
    ],
    'conditions' => [
        [
            'name' => 'discount-10',
            'type' => 'discount',
            'value' => '-10%',
            'target' => 'subtotal',
        ],
    ],
    'metadata' => [
        'coupon_code' => 'SPRING24',
        'shipping_method' => 'express',
    ],
    'subtotal' => 2499.00,
    'total' => 2249.10,
    'savings' => 249.90,
]
*/

// Also available as alias
$data = Cart::toArray();
```

## ⚠️ Error Handling

The cart validates all operations and throws exceptions for invalid data.

### Common Exceptions

| Exception | Thrown When |
|-----------|-------------|
| `InvalidCartItemException` | Missing/invalid ID, name, price, or quantity |
| `InvalidCartConditionException` | Invalid condition parameters |
| `UnknownModelException` | Associated model class doesn't exist |
| `CartConflictException` | Concurrent modification detected (database driver) |

### Handling Exceptions

```php
use AIArmada\Cart\Exceptions\InvalidCartItemException;
use AIArmada\Cart\Exceptions\CartConflictException;

try {
    Cart::add('', 'Product', 10.00); // Empty ID
} catch (InvalidCartItemException $e) {
    // Log and show user-friendly message
    logger()->error('Invalid cart item', ['error' => $e->getMessage()]);
    return back()->withErrors('Invalid item data');
}

try {
    Cart::update('item-1', ['quantity' => 5]);
} catch (CartConflictException $e) {
    // Handle concurrent modification
    return response()->json([
        'error' => 'Cart was modified elsewhere',
        'suggestions' => $e->getResolutionSuggestions(),
    ], 409);
}
```

See [API Reference](api-reference.md#exceptions) for complete exception list.

## 🎓 Best Practices

### Validate User Input

```php
$request->validate([
    'quantity' => 'required|integer|min:1|max:999',
    'item_id' => 'required|string|max:255',
]);

Cart::update($request->item_id, [
    'quantity' => $request->quantity,
]);
```

### Use Transactions for Multi-Step Operations

```php
DB::transaction(function () {
    Cart::add('item-1', 'Product A', 10.00);
    Cart::add('item-2', 'Product B', 20.00);
    Cart::addDiscount('bulk', '10%');
    
    // If any operation fails, all are rolled back
});
```

### Cache Totals for Read-Heavy Scenarios

```php
$total = Cache::remember("cart.{$userId}.total", 300, function () {
    return Cart::total()->getAmount();
});
```

### Store Base Prices, Calculate Dynamically

```php
// Store original price in attributes
Cart::add('item-1', 'Product', $discountedPrice, 1, [
    'base_price' => $originalPrice,
    'discount_applied' => true,
]);

// Access later
$item = Cart::get('item-1');
$basePrice = $item->attributes->get('base_price');
```

## 📚 Related Documentation

- **[Conditions & Pricing](conditions.md)** – Apply discounts, taxes, and fees
- **[Storage Drivers](storage.md)** – Choose and configure storage
- **[User Migration](identifiers-and-migration.md)** – Guest-to-user cart migration
- **[API Reference](api-reference.md)** – Complete method signatures
- **[Quick Examples](examples.md)** – Common patterns and recipes

---

**Need help?** Check [Troubleshooting](troubleshooting.md) or [open a discussion](https://github.com/aiarmada/cart/discussions).


Metadata pairs contextual state with a cart instance (e.g., selected shipping method).

```php
Cart::setMetadata('shipping_method', 'express');
Cart::setMetadataBatch([
    'coupon_code' => 'SPRING24',
    'notes' => 'Deliver after 6pm',
]);

Cart::getMetadata('coupon_code');           // "SPRING24"
Cart::hasMetadata('shipping_method');       // true
Cart::removeMetadata('notes');              // nullifies the key
```

Metadata persists through the configured storage driver and triggers events (see [Events](events.md)).

## Instances

Instances let a single identifier (user/session) keep multiple carts side-by-side (e.g., `default`, `wishlist`, `quote`).

```php
Cart::setInstance('wishlist');              // Switch the facade globally for this request
Cart::add('dream-camera', 'Mirrorless', 1999.00);

$quotes = Cart::getCartInstance('quotes'); // Detached cart instance without flipping globals
$quotes->add('proposal-1', 'Annual plan', 499.00);
```

Use `Cart::getCurrentCart()` to operate on the active instance object and avoid surprises when using dependency injection.

## Searching & Filtering

```php
$premium = Cart::search(fn ($item) => $item->price > 1000);

Cart::getItems()
    ->filterByConditionType('discount')
    ->groupByAttribute('vendor');
```

Search callbacks receive `AIArmada\Cart\Models\CartItem` objects, enabling access to conditions and attributes.

## Associating Domain Models

Item payloads accept an Eloquent model class or instance via `associatedModel`. When stored, the cart retains the class name. You can recover the class name through `CartItem::getAssociatedModel()` and resolve it manually or with your own repository.

> The cart does **not** automatically rehydrate full Eloquent models on read; this keeps storage drivers lean and deterministic.

## Error Handling & Validation

- Missing `id`, `name`, or invalid pricing/quantity throw `InvalidCartItemException`.
- Unknown model classes trigger `UnknownModelException`.
- **Best practices**
- Validation is built-in and always active to ensure data integrity.
- For variable-pricing items (membership tiers, subscriptions), store `base_price` in attributes and calculate `price` dynamically via a condition.
- If you need incremental stock reservation, pair the cart with a `StockReservation` model that expires unconfirmed holds after N minutes.

For a complete list of exceptions, see [API Reference](api-reference.md#exceptions).

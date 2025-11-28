# Cart Operations

Complete guide to managing items, totals, metadata, and instances.

## Adding Items

### Single Item

```php
use AIArmada\Cart\Facades\Cart;

Cart::add(
    id: 'laptop-001',
    name: 'MacBook Pro 16"',
    price: 2499.00,
    quantity: 1,
    attributes: [
        'sku' => 'MBP16-2024',
        'color' => 'Space Gray',
    ],
    conditions: null,
    associatedModel: App\Models\Product::class,
);
```

**Parameters:**
- `id` – Unique identifier per instance
- `name` – Display name
- `price` – Price per unit (float, int, string, or Money object)
- `quantity` – Initial quantity (positive integer)
- `attributes` – Additional metadata
- `conditions` – Item-level conditions
- `associatedModel` – Eloquent model class or instance

### Multiple Items

```php
Cart::add([
    ['id' => 'mouse-001', 'name' => 'Magic Mouse', 'price' => 99.00, 'quantity' => 1],
    ['id' => 'keyboard-001', 'name' => 'Magic Keyboard', 'price' => 149.00, 'quantity' => 1],
]);
```

### Price Sanitization

String prices are automatically cleaned:

```php
Cart::add('item-1', 'Product', '1,234.56', 1);  // → 1234.56
Cart::add('item-2', 'Product', '$ 99.00', 1);   // → 99.00
```

## Retrieving Items

### Get Single Item

```php
$item = Cart::get('laptop-001');

if ($item) {
    echo $item->name;                    // "MacBook Pro 16""
    echo $item->quantity;                // 1
    echo $item->getSubtotal()->format(); // "$2,499.00"
    echo $item->attributes->get('color'); // "Space Gray"
}
```

### Get All Items

```php
$items = Cart::getItems();

foreach ($items as $item) {
    echo "{$item->name}: {$item->getSubtotal()->format()}\n";
}
```

### Check Existence

```php
if (Cart::has('laptop-001')) {
    echo "Item in cart";
}
```

## Updating Items

### Update Quantity

```php
// Absolute value
Cart::update('laptop-001', ['quantity' => ['value' => 3]]);

// Relative (add to current)
Cart::update('laptop-001', ['quantity' => 1]);
```

### Update Other Properties

```php
Cart::update('laptop-001', [
    'price' => 2299.00,
    'name' => 'MacBook Pro 16" (Sale)',
    'attributes' => ['sale' => true],
]);
```

Items auto-remove when quantity reaches zero.

## Removing Items

```php
Cart::remove('laptop-001');
Cart::clear();  // Remove all items

if (Cart::isEmpty()) {
    echo "Cart is empty";
}
```

## Totals & Calculations

All totals return `Money` objects:

```php
$total = Cart::total();
echo $total->format();     // "$2,648.00"
echo $total->getAmount();  // 264800 (cents)

$subtotal = Cart::subtotal();
$savings = Cart::savings();
```

### Quantity Counts

```php
$quantity = Cart::count();      // Total items: 5
$items = Cart::countItems();    // Unique items: 3
```

## Metadata

Store contextual information about the cart:

```php
Cart::setMetadata('shipping_method', 'express');
Cart::setMetadataBatch([
    'coupon_code' => 'SPRING24',
    'notes' => 'Deliver after 6pm',
]);

$method = Cart::getMetadata('shipping_method');
$all = Cart::getMetadata();

if (Cart::hasMetadata('coupon_code')) {
    echo "Coupon applied";
}

Cart::removeMetadata('notes');
```

## Multiple Instances

Use instances for different cart types:

```php
// Shopping cart (default)
Cart::add('product-1', 'Laptop', 999.00);

// Wishlist
Cart::instance('wishlist')->add('product-2', 'Monitor', 449.00);

// Quote basket
Cart::instance('quote')->add('product-3', 'Keyboard', 129.00);

// Check which instance
echo Cart::instance(); // 'quote'

// Switch back
Cart::instance('default');
```

Each instance has independent items, conditions, and metadata.

### Common Instance Names

| Instance | Purpose |
|----------|---------|
| `default` | Main shopping cart |
| `wishlist` | Saved for later |
| `quote` | B2B quotes |
| `compare` | Product comparison |

## Identifier Management

```php
// Get current identifier
$id = Cart::getIdentifier();

// Work with another user's cart
Cart::setIdentifier('user-456');
Cart::add('item', 'Product', 10.00);

// Return to current user
Cart::forgetIdentifier();

// Load cart by UUID
$cart = Cart::getById($cartUuid);
```

## Cart Content Snapshot

```php
$snapshot = Cart::content();
// Returns array with items, conditions, metadata, totals
```

## Error Handling

```php
use AIArmada\Cart\Exceptions\InvalidCartItemException;
use AIArmada\Cart\Exceptions\CartConflictException;

try {
    Cart::add('', 'Product', 10.00); // Empty ID
} catch (InvalidCartItemException $e) {
    // Handle validation error
}

try {
    Cart::update('item-1', ['quantity' => 5]);
} catch (CartConflictException $e) {
    // Handle concurrent modification
    $e->getResolutionSuggestions();
}
```

## Searching & Filtering

```php
// Search with callback
$results = Cart::search(fn($item) => $item->price > 100);

// Filter by attribute
$electronics = Cart::getItems()->filter(
    fn($item) => $item->attributes->get('category') === 'electronics'
);
```

## Associated Models

Link cart items to Eloquent models:

```php
use App\Models\Product;

Cart::add('prod-1', 'Laptop', 999.00, 1, [], null, Product::class);

$item = Cart::get('prod-1');
$modelClass = $item->associatedModel; // "App\Models\Product"
```

## Next Steps

- [Conditions](conditions.md) – Apply discounts and taxes
- [Money & Currency](money-and-currency.md) – Working with prices
- [API Reference](api-reference.md) – Complete method signatures

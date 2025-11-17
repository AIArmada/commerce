# 📘 API Reference

Complete reference for all public methods, classes, and interfaces in the Cart package.

---

## Table of Contents

- [Cart Facade](#cart-facade)
  - [Adding Items](#adding-items)
  - [Retrieving Items](#retrieving-items)
  - [Updating Items](#updating-items)
  - [Removing Items](#removing-items)
  - [Cart Information](#cart-information)
  - [Conditions](#conditions)
  - [Metadata](#metadata)
  - [Instance Management](#instance-management)
  - [Storage Operations](#storage-operations)
- [CartItem Class](#cartitem-class)
- [CartCondition Class](#cartcondition-class)
- [CartCollection Class](#cartcollection-class)
- [Storage Interface](#storage-interface)
- [Services](#services)
- [Exceptions](#exceptions)
- [Console Commands](#console-commands)

---

## Cart Facade

The `Cart` facade provides the primary interface for cart operations.

```php
use AIArmada\Cart\Facades\Cart;
```

### Adding Items

#### `add()`

Add an item to the cart.

```php
Cart::add(
    int|string $id,
    string $name,
    int|string|Money $price,
    int $quantity = 1,
    array $attributes = []
): CartItem
```

**Parameters:**
- `$id` (int|string): Product ID or unique identifier
- `$name` (string): Product name (max 255 characters)
- `$price` (int|string|Money): Price in cents, string, or Money object
- `$quantity` (int): Quantity (default: 1, min: 1)
- `$attributes` (array): Additional attributes (e.g., size, color)

**Returns:**
- `CartItem`: The added item

**Throws:**
- `InvalidCartItemException`: If validation fails

**Example:**

```php
// Basic usage
$item = Cart::add(1, 'T-Shirt', 2500, 2);

// With attributes
$item = Cart::add(
    id: 'TSHIRT-BLUE-L',
    name: 'Blue T-Shirt',
    price: Money::USD(2500),
    quantity: 1,
    attributes: [
        'size' => 'L',
        'color' => 'Blue',
        'sku' => 'TSH-BLU-L-001',
    ]
);

// Price as string (will be sanitized)
$item = Cart::add(2, 'Jeans', '49.99', 1);
```

---

#### `addMany()`

Add multiple items at once.

```php
Cart::addMany(array $items): CartCollection
```

**Parameters:**
- `$items` (array): Array of item arrays, each with keys: `id`, `name`, `price`, `quantity`, `attributes`

**Returns:**
- `CartCollection`: Collection of added items

**Example:**

```php
$items = Cart::addMany([
    [
        'id' => 1,
        'name' => 'Product A',
        'price' => 1000,
        'quantity' => 2,
    ],
    [
        'id' => 2,
        'name' => 'Product B',
        'price' => 2000,
        'quantity' => 1,
        'attributes' => ['color' => 'Red'],
    ],
]);
```

---

### Retrieving Items

#### `get()`

Get an item by ID.

```php
Cart::get(int|string $itemId): ?CartItem
```

**Parameters:**
- `$itemId` (int|string): Item identifier

**Returns:**
- `CartItem|null`: Item if found, null otherwise

**Example:**

```php
$item = Cart::get(1);

if ($item) {
    echo "Found: {$item->name}";
}
```

---

#### `has()`

Check if an item exists in the cart.

```php
Cart::has(int|string $itemId): bool
```

**Parameters:**
- `$itemId` (int|string): Item identifier

**Returns:**
- `bool`: True if item exists

**Example:**

```php
if (Cart::has(1)) {
    echo 'Item exists!';
}
```

---

#### `items()`

Get all cart items.

```php
Cart::items(): CartCollection
```

**Returns:**
- `CartCollection`: Collection of all items

**Example:**

```php
$items = Cart::items();

foreach ($items as $item) {
    echo "{$item->name}: {$item->quantity}x {$item->price->format()}\n";
}
```

---

#### `content()`

Alias for `items()`.

```php
Cart::content(): CartCollection
```

---

#### `search()`

Search for items matching criteria.

```php
Cart::search(callable $callback): CartCollection
```

**Parameters:**
- `$callback` (callable): Callback function receiving `CartItem $item`

**Returns:**
- `CartCollection`: Matching items

**Example:**

```php
// Find all items with size 'L'
$largeItems = Cart::search(function (CartItem $item) {
    return $item->attributes['size'] ?? null === 'L';
});

// Find items over $50
$expensive = Cart::search(fn($item) => $item->price->getAmount() > 5000);
```

---

### Updating Items

#### `update()`

Update an item's attributes.

```php
Cart::update(
    int|string $itemId,
    array $attributes
): CartItem
```

**Parameters:**
- `$itemId` (int|string): Item identifier
- `$attributes` (array): Attributes to update (supports: `quantity`, `price`, `name`, `attributes`)

**Returns:**
- `CartItem`: Updated item

**Throws:**
- `InvalidCartItemException`: If item not found or validation fails

**Example:**

```php
// Update quantity
Cart::update(1, ['quantity' => 5]);

// Update multiple attributes
Cart::update(1, [
    'quantity' => 3,
    'price' => Money::USD(2200),
    'attributes' => [
        'size' => 'XL',
        'gift_wrap' => true,
    ],
]);
```

---

#### `updateQuantity()`

Update item quantity.

```php
Cart::updateQuantity(
    int|string $itemId,
    int $quantity
): CartItem
```

**Parameters:**
- `$itemId` (int|string): Item identifier
- `$quantity` (int): New quantity (min: 1)

**Returns:**
- `CartItem`: Updated item

**Example:**

```php
Cart::updateQuantity(1, 5);
```

---

#### `increment()`

Increment item quantity.

```php
Cart::increment(
    int|string $itemId,
    int $amount = 1
): CartItem
```

**Parameters:**
- `$itemId` (int|string): Item identifier
- `$amount` (int): Amount to increment (default: 1)

**Returns:**
- `CartItem`: Updated item

**Example:**

```php
// Increment by 1
Cart::increment(1);

// Increment by 3
Cart::increment(1, 3);
```

---

#### `decrement()`

Decrement item quantity.

```php
Cart::decrement(
    int|string $itemId,
    int $amount = 1
): CartItem
```

**Parameters:**
- `$itemId` (int|string): Item identifier
- `$amount` (int): Amount to decrement (default: 1)

**Returns:**
- `CartItem`: Updated item

**Throws:**
- `InvalidCartItemException`: If resulting quantity would be < 1

**Example:**

```php
// Decrement by 1
Cart::decrement(1);

// Decrement by 2
Cart::decrement(1, 2);
```

---

### Removing Items

#### `remove()`

Remove an item from the cart.

```php
Cart::remove(int|string $itemId): void
```

**Parameters:**
- `$itemId` (int|string): Item identifier

**Example:**

```php
Cart::remove(1);
```

---

#### `clear()`

Remove all items and conditions.

```php
Cart::clear(): void
```

**Example:**

```php
Cart::clear();
```

---

#### `destroy()`

Destroy the cart completely (removes from storage).

```php
Cart::destroy(): void
```

**Example:**

```php
Cart::destroy();
```

---

### Cart Information

#### `count()`

Get total item quantity in cart.

```php
Cart::count(): int
```

**Returns:**
- `int`: Total quantity

**Example:**

```php
$totalItems = Cart::count(); // e.g., 5 (2 + 3)
```

---

#### `isEmpty()`

Check if cart is empty.

```php
Cart::isEmpty(): bool
```

**Returns:**
- `bool`: True if cart has no items

**Example:**

```php
if (Cart::isEmpty()) {
    echo 'Your cart is empty!';
}
```

---

#### `isNotEmpty()`

Check if cart has items.

```php
Cart::isNotEmpty(): bool
```

**Returns:**
- `bool`: True if cart has items

---

#### `subtotal()`

Get cart subtotal (before conditions).

```php
Cart::subtotal(?string $currency = null): Money
```

**Parameters:**
- `$currency` (string|null): Currency code (uses default if null)

**Returns:**
- `Money`: Subtotal amount

**Example:**

```php
$subtotal = Cart::subtotal(); // Money object
echo $subtotal->format(); // "$75.00"
```

---

#### `total()`

Get cart total (after conditions).

```php
Cart::total(?string $currency = null): Money
```

**Parameters:**
- `$currency` (string|null): Currency code (uses default if null)

**Returns:**
- `Money`: Total amount

**Example:**

```php
$total = Cart::total();
echo $total->format(); // "$81.75"
```

---

#### `tax()`

Get total tax amount.

```php
Cart::tax(?string $currency = null): Money
```

**Parameters:**
- `$currency` (string|null): Currency code

**Returns:**
- `Money`: Total tax

---

#### `discount()`

Get total discount amount.

```php
Cart::discount(?string $currency = null): Money
```

**Parameters:**
- `$currency` (string|null): Currency code

**Returns:**
- `Money`: Total discount (positive value)

---

### Conditions

#### `addCondition()`

Add a condition to the cart.

```php
Cart::addCondition(CartCondition|array $condition): self
```

**Parameters:**
- `$condition` (CartCondition|array): Condition object or array

**Returns:**
- `Cart`: Fluent interface

**Throws:**
- `InvalidCartConditionException`: If validation fails

**Example:**

> These examples assume `use AIArmada\Cart\Conditions\TargetPresets;`.

```php
// Using CartCondition object
Cart::addCondition(new CartCondition(
    name: 'VAT',
    type: 'tax',
    target: TargetPresets::cartGrandTotal(),
    value: '20%',
));

// Using array
Cart::addCondition([
    'name' => 'Summer Sale',
    'type' => 'discount',
    'target_definition' => TargetPresets::cartGrandTotal()->toArray(),
    'value' => '-15%',
]);
```

---

#### `addConditions()`

Add multiple conditions.

```php
Cart::addConditions(array $conditions): self
```

**Parameters:**
- `$conditions` (array): Array of CartCondition objects or arrays

**Returns:**
- `Cart`: Fluent interface

**Example:**

```php
Cart::addConditions([
    [
        'name' => 'Tax',
        'type' => 'tax',
        'target_definition' => TargetPresets::cartGrandTotal()->toArray(),
        'value' => '10%',
    ],
    [
        'name' => 'Shipping',
        'type' => 'shipping',
        'target_definition' => TargetPresets::cartShipping()->toArray(),
        'value' => '+5.00',
    ],
]);
```

---

#### `getCondition()`

Get a condition by name.

```php
Cart::getCondition(string $name): ?CartCondition
```

**Parameters:**
- `$name` (string): Condition name

**Returns:**
- `CartCondition|null`: Condition if found

**Example:**

```php
$vat = Cart::getCondition('VAT');

if ($vat) {
    echo "VAT rate: {$vat->value}";
}
```

---

#### `getConditions()`

Get all conditions.

```php
Cart::getConditions(): Collection
```

**Returns:**
- `Collection`: All conditions

**Example:**

```php
$conditions = Cart::getConditions();

foreach ($conditions as $condition) {
    echo "{$condition->name}: {$condition->value}\n";
}
```

---

#### `getConditionsByType()`

Get conditions by type.

```php
Cart::getConditionsByType(string $type): Collection
```

**Parameters:**
- `$type` (string): Condition type (e.g., 'tax', 'discount', 'shipping')

**Returns:**
- `Collection`: Matching conditions

**Example:**

```php
$taxes = Cart::getConditionsByType('tax');
$discounts = Cart::getConditionsByType('discount');
```

---

#### `removeCondition()`

Remove a condition.

```php
Cart::removeCondition(string $name): self
```

**Parameters:**
- `$name` (string): Condition name

**Returns:**
- `Cart`: Fluent interface

**Example:**

```php
Cart::removeCondition('Summer Sale');
```

---

#### `clearConditions()`

Remove all conditions.

```php
Cart::clearConditions(): self
```

**Returns:**
- `Cart`: Fluent interface

**Example:**

```php
Cart::clearConditions();
```

---

### Metadata

#### `setMetadata()`

Set cart metadata.

```php
Cart::setMetadata(array $metadata): self
```

**Parameters:**
- `$metadata` (array): Metadata key-value pairs

**Returns:**
- `Cart`: Fluent interface

**Example:**

```php
Cart::setMetadata([
    'coupon_code' => 'SUMMER2024',
    'gift_message' => 'Happy Birthday!',
    'billing_same_as_shipping' => true,
]);
```

---

#### `getMetadata()`

Get cart metadata.

```php
Cart::getMetadata(?string $key = null, mixed $default = null): mixed
```

**Parameters:**
- `$key` (string|null): Specific key (null returns all metadata)
- `$default` (mixed): Default value if key not found

**Returns:**
- `mixed`: Metadata value or all metadata

**Example:**

```php
// Get all metadata
$metadata = Cart::getMetadata();

// Get specific key
$coupon = Cart::getMetadata('coupon_code');

// With default
$message = Cart::getMetadata('gift_message', 'No message');
```

---

#### `hasMetadata()`

Check if metadata key exists.

```php
Cart::hasMetadata(string $key): bool
```

**Parameters:**
- `$key` (string): Metadata key

**Returns:**
- `bool`: True if key exists

**Example:**

```php
if (Cart::hasMetadata('coupon_code')) {
    echo 'Coupon applied!';
}
```

---

#### `removeMetadata()`

Remove metadata key.

```php
Cart::removeMetadata(string $key): self
```

**Parameters:**
- `$key` (string): Metadata key

**Returns:**
- `Cart`: Fluent interface

**Example:**

```php
Cart::removeMetadata('gift_message');
```

---

### Instance Management

#### `instance()`

Switch cart instance.

```php
Cart::instance(string $name): self
```

**Parameters:**
- `$name` (string): Instance name

**Returns:**
- `Cart`: Cart instance

**Example:**

```php
// Main cart
Cart::instance('default')->add(1, 'Product', 1000);

// Wishlist
Cart::instance('wishlist')->add(2, 'Favorite', 2000);

// Compare
Cart::instance('compare')->add(3, 'Comparison', 3000);
```

---

#### `setInstance()`

Set the cart instance (chainable).

```php
Cart::setInstance(string $name): CartManager
```

**Parameters:**
- `$name` (string): Instance name

**Returns:**
- `CartManager`: Manager instance for chaining

**Example:**

```php
Cart::setInstance('wishlist')->add(1, 'Product', 1000);
```

---

#### `currentInstance()`

Get current instance name.

```php
Cart::currentInstance(): string
```

**Returns:**
- `string`: Current instance name

**Example:**

```php
$current = Cart::currentInstance(); // 'default'
```

---

#### `getCartInstance()`

Get a cart instance with a specific name and identifier.

```php
Cart::getCartInstance(string $name, ?string $identifier = null): Cart
```

**Parameters:**
- `$name` (string): Instance name (e.g., 'default', 'wishlist')
- `$identifier` (string|null): Optional custom identifier (defaults to current identifier)

**Returns:**
- `Cart`: Cart instance

**Example:**

```php
// Get another user's cart
$userCart = Cart::getCartInstance('default', 'user-123');

// Get current user's wishlist
$wishlist = Cart::getCartInstance('wishlist');
```

---

### Identifier Management

#### `getIdentifier()`

Get the current cart identifier.

```php
Cart::getIdentifier(): string
```

**Returns:**
- `string`: Current identifier (user ID or session ID)

**Example:**

```php
$identifier = Cart::getIdentifier();
// For authenticated user: returns user ID
// For guest: returns session ID
```

---

#### `setIdentifier()`

Set a custom cart identifier.

```php
Cart::setIdentifier(string $identifier): CartManager
```

**Parameters:**
- `$identifier` (string): Custom identifier

**Returns:**
- `CartManager`: Manager instance for chaining

**Example:**

```php
// Switch to a specific user's cart
Cart::setIdentifier('user-456')->add(1, 'Product', 1000);
```

---

#### `forgetIdentifier()`

Reset identifier to default (current user/session).

```php
Cart::forgetIdentifier(): CartManager
```

**Returns:**
- `CartManager`: Manager instance for chaining

**Example:**

```php
// Temporarily check another cart, then return
Cart::setIdentifier('user-789');
$items = Cart::count();

// Return to current user's cart
Cart::forgetIdentifier();
```

---

#### `getById()`

Load a cart by its UUID (database primary key).

```php
Cart::getById(string $uuid): ?Cart
```

**Parameters:**
- `$uuid` (string): Cart UUID from database

**Returns:**
- `Cart|null`: Cart instance or null if not found

**Example:**

```php
// Load cart from payment system
$cartUuid = $payment->cart_id;
$cart = Cart::getById($cartUuid);

if ($cart) {
    $total = $cart->total();
    $items = $cart->getItems();
}
```

---

### Storage Operations

#### `exists()`

Check if a cart exists in storage.

```php
Cart::exists(?string $identifier = null, ?string $instance = null): bool
```

**Parameters:**
- `$identifier` (string|null): Identifier to check (defaults to current)
- `$instance` (string|null): Instance name (defaults to current)

**Returns:**
- `bool`: True if cart exists in storage

**Example:**

```php
if (Cart::exists()) {
    // Cart has been persisted
}

// Check another user's cart
if (Cart::exists('user-123', 'default')) {
    // User 123 has a cart
}
```

---

#### `destroy()`

Destroy a cart from storage.

```php
Cart::destroy(?string $identifier = null, ?string $instance = null): void
```

**Parameters:**
- `$identifier` (string|null): Identifier to destroy (defaults to current)
- `$instance` (string|null): Instance name (defaults to current)

**Example:**

```php
// Destroy current cart
Cart::destroy();

// Destroy specific cart
Cart::destroy('user-123', 'wishlist');
```

---

#### `instances()`

Get all instance names for an identifier.

```php
Cart::instances(?string $identifier = null): array
```

**Parameters:**
- `$identifier` (string|null): Identifier to check (defaults to current)

**Returns:**
- `array`: Array of instance names

**Example:**

```php
$instances = Cart::instances();
// ['default', 'wishlist', 'compare']
```

---

#### `getId()`

Get cart UUID (database primary key).

```php
Cart::getId(): ?string
```

**Returns:**
- `string|null`: Cart UUID or null if not persisted

**Example:**

```php
$uuid = Cart::getId();

// Save to payment record
$payment->cart_id = $uuid;
$payment->save();
```

---

#### `getVersion()`

Get cart version number (for optimistic locking).

```php
Cart::getVersion(): ?int
```

**Returns:**
- `int|null`: Version number or null if not using database storage

**Example:**

```php
$version = Cart::getVersion();
// Used internally for concurrency control
```

---

#### `swap()`

Transfer cart ownership from one identifier to another.

```php
Cart::swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default'): bool
```

**Parameters:**
- `$oldIdentifier` (string): Old identifier (e.g., session ID)
- `$newIdentifier` (string): New identifier (e.g., user ID)
- `$instance` (string): Instance name (default: 'default')

**Returns:**
- `bool`: True if swap was successful

**Example:**

```php
// Transfer guest cart to authenticated user after login
Cart::swap(session()->getId(), auth()->id());
```

---

#### `store()`

Store cart to storage.

```php
Cart::store(): void
```

**Example:**

```php
Cart::store(); // Manually persist
```

---

#### `restore()`

Restore cart from storage.

```php
Cart::restore(): void
```

**Example:**

```php
Cart::restore(); // Manually load
```

---

#### `identifier()`

Get cart identifier.

```php
Cart::identifier(): string
```

**Returns:**
- `string`: Cart identifier (e.g., 'cart_session_abc123' or 'cart_user_456')

**Example:**

```php
$identifier = Cart::identifier();
Log::info("Cart ID: {$identifier}");
```

---

## CartItem Class

Represents an item in the cart.

```php
namespace AIArmada\Cart\Data;

class CartItem
```

### Properties

```php
public int|string $id;           // Product ID
public string $name;              // Product name
public Money $price;              // Price (Money object)
public int $quantity;             // Quantity
public array $attributes;         // Additional attributes
public Collection $conditions;    // Item-level conditions
```

### Methods

#### `subtotal()`

Get item subtotal (before conditions).

```php
public function subtotal(): Money
```

**Example:**

```php
$item = Cart::get(1);
echo $item->subtotal()->format(); // "$50.00"
```

---

#### `total()`

Get item total (after conditions).

```php
public function total(): Money
```

**Example:**

```php
echo $item->total()->format(); // "$54.50"
```

---

#### `tax()`

Get item tax amount.

```php
public function tax(): Money
```

---

#### `discount()`

Get item discount amount.

```php
public function discount(): Money
```

---

#### `addCondition()`

Add condition to item.

```php
public function addCondition(CartCondition|array $condition): self
```

---

#### `toArray()`

Convert to array.

```php
public function toArray(): array
```

**Example:**

```php
$data = $item->toArray();
/*
[
    'id' => 1,
    'name' => 'Product',
    'price' => 2500,
    'quantity' => 2,
    'attributes' => ['size' => 'L'],
    'conditions' => [...],
    'subtotal' => 5000,
    'total' => 5450,
]
*/
```

---

## CartCondition Class

Represents a condition (discount, tax, fee, etc.).

```php
namespace AIArmada\Cart\Data;

class CartCondition
```

### Properties

```php
public string $name;        // Condition name
public string $type;        // Type (discount, tax, shipping, fee)
public string $target;      // Target (subtotal, total)
public string $value;       // Value (percentage or fixed)
public int $order;          // Calculation order
public array $attributes;   // Additional attributes
```

### Methods

#### `make()`

Create condition instance.

```php
public static function make(array $attributes): self
```

**Example:**

```php
$condition = CartCondition::make([
    'name' => 'VAT',
    'type' => 'tax',
    'target' => 'cart@grand_total/aggregate',
    'value' => '20%',
    'order' => 1,
    'attributes' => [
        'rate' => 0.20,
        'region' => 'EU',
    ],
]);
```

---

#### `calculate()`

Calculate condition amount.

```php
public function calculate(Money $amount): Money
```

**Example:**

```php
$tax = $condition->calculate(Money::USD(10000)); // $2000 (20%)
```

---

## CartCollection Class

Collection of CartItem objects with additional methods.

```php
namespace AIArmada\Cart\Data;

class CartCollection extends Collection
```

### Methods

#### `subtotal()`

Get collection subtotal.

```php
public function subtotal(): Money
```

---

#### `total()`

Get collection total.

```php
public function total(): Money
```

---

#### `tax()`

Get total tax.

```php
public function tax(): Money
```

---

#### `discount()`

Get total discount.

```php
public function discount(): Money
```

---

#### `totalQuantity()`

Get total quantity.

```php
public function totalQuantity(): int
```

---

## Storage Interface

Implement custom storage drivers.

```php
namespace AIArmada\Cart\Contracts;

interface StorageInterface
{
    public function get(string $identifier): ?array;
    public function put(string $identifier, array $data): void;
    public function forget(string $identifier): void;
    public function flush(): void;
}
```

**Example Implementation:**

```php
use AIArmada\Cart\Contracts\StorageInterface;

class DynamoDbStorage implements StorageInterface
{
    public function __construct(
        private DynamoDbClient $client,
        private string $table
    ) {}

    public function get(string $identifier): ?array
    {
        $result = $this->client->getItem([
            'TableName' => $this->table,
            'Key' => ['id' => ['S' => $identifier]],
        ]);

        return $result['Item'] ?? null;
    }

    public function put(string $identifier, array $data): void
    {
        $this->client->putItem([
            'TableName' => $this->table,
            'Item' => [
                'id' => ['S' => $identifier],
                'data' => ['S' => json_encode($data)],
                'updated_at' => ['N' => (string) time()],
            ],
        ]);
    }

    public function forget(string $identifier): void
    {
        $this->client->deleteItem([
            'TableName' => $this->table,
            'Key' => ['id' => ['S' => $identifier]],
        ]);
    }

    public function flush(): void
    {
        // Implementation depends on DynamoDB scan + batch delete
    }
}
```

---

## Services

### CartService

Main service class handling cart logic.

```php
namespace AIArmada\Cart\Services;

class CartService
{
    public function add(int|string $id, string $name, $price, int $quantity, array $attributes): CartItem;
    public function get(int|string $itemId): ?CartItem;
    public function update(int|string $itemId, array $attributes): CartItem;
    public function remove(int|string $itemId): void;
    public function clear(): void;
    public function items(): CartCollection;
    public function count(): int;
    public function subtotal(): Money;
    public function total(): Money;
    // ... and all other Cart facade methods
}
```

---

### MigrationService

Handles guest-to-user cart migration.

```php
namespace AIArmada\Cart\Services;

class MigrationService
{
    public function migrate(
        string $fromIdentifier,
        string $toIdentifier,
        string $instance = 'default',
        ?string $strategy = null
    ): void;
    
    public function migrateAll(
        string $fromIdentifier,
        string $toIdentifier,
        ?string $strategy = null
    ): void;
}
```

**Example:**

```php
use AIArmada\Cart\Services\MigrationService;

$migration = app(MigrationService::class);

$migration->migrate(
    fromIdentifier: 'cart_session_abc123',
    toIdentifier: 'cart_user_456',
    instance: 'default',
    strategy: 'add_quantities'
);
```

---

## Exceptions

### InvalidCartItemException

Thrown when cart item validation fails.

```php
namespace AIArmada\Cart\Exceptions;

class InvalidCartItemException extends \Exception
```

**When thrown:**
- Invalid item ID
- Invalid quantity (< 1)
- Invalid price
- Missing required fields

**Example:**

```php
try {
    Cart::add(1, 'Product', -100, 1); // Negative price
} catch (InvalidCartItemException $e) {
    Log::error($e->getMessage());
}
```

---

### InvalidCartConditionException

Thrown when condition validation fails.

```php
namespace AIArmada\Cart\Exceptions;

class InvalidCartConditionException extends \Exception
```

**When thrown:**
- Invalid condition name
- Invalid type
- Invalid target
- Invalid value format

---

### CartConflictException

Thrown on optimistic locking conflicts.

```php
namespace AIArmada\Cart\Exceptions;

class CartConflictException extends \Exception
```

**When thrown:**
- Concurrent cart modifications detected
- Version mismatch in database driver

**Example:**

```php
use AIArmada\Cart\Exceptions\CartConflictException;

try {
    Cart::add(1, 'Product', 1000);
} catch (CartConflictException $e) {
    // Retry logic
    retry(3, function () {
        Cart::add(1, 'Product', 1000);
    }, 100);
}
```

---

## Console Commands

### `cart:clear`

Clear all carts from storage.

```bash
php artisan cart:clear
```

**Options:**
- `--driver=session`: Specify storage driver
- `--force`: Skip confirmation

**Example:**

```bash
# Clear all session carts
php artisan cart:clear --driver=session --force

# Clear database carts
php artisan cart:clear --driver=database
```

---

### `cart:inspect`

Inspect a cart's contents.

```bash
php artisan cart:inspect {identifier}
```

**Arguments:**
- `identifier`: Cart identifier (e.g., 'cart_user_123')

**Example:**

```bash
php artisan cart:inspect cart_user_456

# Output:
# Cart Identifier: cart_user_456
# Items: 3
# Subtotal: $125.00
# Total: $136.25
# 
# Items:
# - Product A (x2): $50.00
# - Product B (x1): $75.00
# 
# Conditions:
# - VAT (20%): +$25.00
```

---

### `cart:migrate`

Manually migrate a cart.

```bash
php artisan cart:migrate {from} {to}
```

**Arguments:**
- `from`: Source identifier
- `to`: Destination identifier

**Options:**
- `--strategy=add_quantities`: Merge strategy
- `--instance=default`: Cart instance

**Example:**

```bash
php artisan cart:migrate cart_session_abc123 cart_user_456 \
    --strategy=add_quantities \
    --instance=default
```

---

### `cart:prune`

Remove expired/abandoned carts.

```bash
php artisan cart:prune
```

**Options:**
- `--days=30`: Days of inactivity
- `--driver=database`: Storage driver

**Example:**

```bash
# Remove carts inactive for 60 days
php artisan cart:prune --days=60 --driver=database
```

---

## Method Chaining Examples

The Cart facade supports fluent method chaining:

```php
// Add items and conditions
Cart::add(1, 'Product A', 1000)
    ->add(2, 'Product B', 2000)
    ->addCondition([
        'name' => 'VAT',
        'type' => 'tax',
        'target' => 'cart@grand_total/aggregate',
        'value' => '20%',
    ])
    ->setMetadata(['coupon' => 'SAVE10'])
    ->store();

// Switch instance and operate
Cart::instance('wishlist')
    ->add(3, 'Favorite', 3000)
    ->setMetadata(['added_at' => now()]);

// Clear conditions
Cart::clearConditions()
    ->addCondition(['name' => 'Free Shipping', 'value' => '0'])
    ->store();
```

---

## Type Hints & Return Types

All methods use strict typing:

```php
// Correct
Cart::add(1, 'Product', 1000, 2, ['color' => 'Red']);

// Type errors
Cart::add('1', 'Product', '10.00', '2', 'red'); // ❌ Wrong types
```

**Tips:**
- Use `int` for quantities
- Use `Money` objects for currency safety
- Use `array` for attributes (not objects)
- Check return types for null safety

---

## Deprecations

None currently. All methods are stable in v1.x.

---

## Version Compatibility

- **Cart Package**: v1.x
- **Laravel**: 12.x
- **PHP**: 8.4+

---

## Additional Resources

- [Cart Operations Guide](cart-operations.md)
- [Conditions Guide](conditions.md)
- [Storage Drivers](storage.md)
- [Testing Guide](testing.md)
- [Examples](examples.md)

---

**Need help?** Check the [Troubleshooting Guide](troubleshooting.md) or [open an issue](https://github.com/aiarmada/cart/issues).

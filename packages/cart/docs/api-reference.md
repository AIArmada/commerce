# API Reference

Complete method reference for the Cart facade.

## Item Operations

### add()

Add an item to the cart.

```php
Cart::add(
    string $id,
    string $name,
    Money|string|int $price,
    int $quantity = 1,
    array $metadata = []
): CartItem
```

**Parameters:**
- `$id` – Unique item identifier (SKU, product ID)
- `$name` – Display name
- `$price` – Price as Money object, string ("19.99"), or cents (1999)
- `$quantity` – Number of items (default: 1)
- `$metadata` – Additional data (image, weight, etc.)

**Returns:** The created `CartItem`

---

### addBuyable()

Add a BuyableInterface model.

```php
Cart::addBuyable(
    BuyableInterface $buyable,
    int $quantity = 1,
    array $metadata = []
): CartItem
```

---

### update()

Update an existing item.

```php
Cart::update(string $id, array $attributes): CartItem
```

**Attributes:**
- `quantity` – New quantity
- `price` – New price
- `name` – New name
- `metadata` – Merge with existing metadata

---

### remove()

Remove an item.

```php
Cart::remove(string $id): void
```

---

### get()

Retrieve a single item.

```php
Cart::get(string $id): ?CartItem
```

---

### has()

Check if item exists.

```php
Cart::has(string $id): bool
```

---

### getContent()

Get all items.

```php
Cart::getContent(): Collection
```

**Returns:** `Collection<CartItem>`

---

### clear()

Remove all items and conditions.

```php
Cart::clear(): void
```

---

### count()

Total quantity of all items.

```php
Cart::count(): int
```

---

### isEmpty()

Check if cart is empty.

```php
Cart::isEmpty(): bool
```

---

## Totals

### subtotal()

Sum of item line totals (before conditions).

```php
Cart::subtotal(): Money
```

---

### total()

Final total after all conditions.

```php
Cart::total(): Money
```

---

### conditionsTotal()

Sum of all condition values.

```php
Cart::conditionsTotal(): Money
```

---

## Conditions

### applyCondition()

Apply a pricing condition.

```php
Cart::applyCondition(CartCondition $condition): void
```

---

### removeCondition()

Remove a condition by name.

```php
Cart::removeCondition(string $name): void
```

---

### getCondition()

Get a condition by name.

```php
Cart::getCondition(string $name): ?CartCondition
```

---

### getConditions()

Get all conditions.

```php
Cart::getConditions(): Collection
```

---

### clearConditions()

Remove all conditions.

```php
Cart::clearConditions(): void
```

---

### getConditionsByType()

Get conditions of a specific type.

```php
Cart::getConditionsByType(string $type): Collection
```

---

## Metadata

### setMetadata()

Set cart-level metadata.

```php
Cart::setMetadata(string $key, mixed $value): void
```

---

### getMetadata()

Get metadata value.

```php
Cart::getMetadata(string $key, mixed $default = null): mixed
```

---

### getAllMetadata()

Get all metadata.

```php
Cart::getAllMetadata(): array
```

---

### removeMetadata()

Remove a metadata key.

```php
Cart::removeMetadata(string $key): void
```

---

### clearMetadata()

Remove all metadata.

```php
Cart::clearMetadata(): void
```

---

## Instances

### instance()

Switch to a named instance.

```php
Cart::instance(string $name): Cart
```

**Examples:**
```php
Cart::instance('wishlist')->add(...);
Cart::instance('saved-for-later')->getContent();
```

---

### getCurrentInstance()

Get current instance name.

```php
Cart::getCurrentInstance(): string
```

---

## Identifiers

### setIdentifier()

Set cart identifier.

```php
Cart::setIdentifier(string $identifier): void
```

---

### getIdentifier()

Get current identifier.

```php
Cart::getIdentifier(): string
```

---

### migrate()

Migrate cart to new identifier.

```php
Cart::migrate(
    string $fromId,
    string $toId,
    string $strategy = 'replace'
): void
```

**Strategies:** `replace`, `merge`, `keep`

---

### exists()

Check if cart exists.

```php
Cart::exists(string $identifier): bool
```

---

## Storage

### store()

Persist cart to storage.

```php
Cart::store(): void
```

---

### restore()

Load cart from storage.

```php
Cart::restore(): void
```

---

### refresh()

Reload from storage (discard local changes).

```php
Cart::refresh(): void
```

---

### destroy()

Delete cart from storage.

```php
Cart::destroy(): void
```

---

## Payment Integration

### toCheckout()

Convert to CheckoutableInterface.

```php
Cart::toCheckout(): CheckoutableInterface
```

---

### toLineItems()

Get items as line item array.

```php
Cart::toLineItems(): array
```

**Returns:**
```php
[
    [
        'name' => 'Product Name',
        'quantity' => 2,
        'price' => 5000, // cents
    ],
    // ...
]
```

---

## Utilities

### transaction()

Execute operations atomically.

```php
Cart::transaction(Closure $callback): mixed
```

---

### withoutEvents()

Suppress events.

```php
Cart::withoutEvents(Closure $callback): mixed
```

---

### withoutLocking()

Disable concurrency locking.

```php
Cart::withoutLocking(Closure $callback): mixed
```

---

### getVersion()

Get current version (for concurrency).

```php
Cart::getVersion(): int
```

---

## CartItem Methods

### getIdentifier()

```php
$item->getIdentifier(): string
```

### getName()

```php
$item->getName(): string
```

### getPrice()

```php
$item->getPrice(): Money
```

### getQuantity()

```php
$item->getQuantity(): int
```

### getLineTotal()

Price × quantity.

```php
$item->getLineTotal(): Money
```

### getMetadata()

```php
$item->getMetadata(?string $key = null, mixed $default = null): mixed
```

### updateMetadata()

```php
$item->updateMetadata(array $data): void
```

### getBuyable()

Get original BuyableInterface model.

```php
$item->getBuyable(): ?BuyableInterface
```

---

## CartCondition Methods

### Constructor

```php
new CartCondition([
    'name' => 'Discount',
    'type' => 'discount',
    'target' => 'subtotal', // item, subtotal, total
    'value' => '-10%',      // or fixed: 500, -500
    'attributes' => [],
])
```

### getName()

```php
$condition->getName(): string
```

### getType()

```php
$condition->getType(): string
```

### getTarget()

```php
$condition->getTarget(): string
```

### getValue()

```php
$condition->getValue(): string|int|float
```

### getCalculatedValue()

Get actual amount applied.

```php
$condition->getCalculatedValue(Money $target): Money
```

---

## Events

| Event | Properties |
|-------|------------|
| `ItemAdded` | `$item`, `$cart` |
| `ItemUpdated` | `$item`, `$previousQuantity`, `$cart` |
| `ItemRemoved` | `$item`, `$cart` |
| `CartCleared` | `$previousItemCount`, `$cart` |
| `CartStored` | `$identifier`, `$cart` |
| `CartRestored` | `$identifier`, `$cart` |
| `ConditionAdded` | `$condition`, `$cart` |
| `ConditionRemoved` | `$condition`, `$cart` |

---

## Exceptions

| Exception | When Thrown |
|-----------|-------------|
| `InvalidItemException` | Invalid item data |
| `ItemNotFoundException` | Item not in cart |
| `InvalidConditionException` | Invalid condition data |
| `ConcurrencyException` | Version conflict (after retries) |
| `StorageException` | Storage driver error |

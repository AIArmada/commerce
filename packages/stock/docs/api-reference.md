# API Reference

Complete API reference for the stock package.

## HasStock Trait

Add to any Eloquent model to enable stock tracking.

### Relationships

#### stockTransactions()

```php
public function stockTransactions(): MorphMany
```

Returns all stock transactions for the model, ordered by date descending.

#### stockReservations()

```php
public function stockReservations(): MorphMany
```

Returns all stock reservations for the model.

### Stock Level Methods

#### getCurrentStock()

```php
public function getCurrentStock(): int
```

Returns the current stock level (sum of in - sum of out).

#### getAvailableStock()

```php
public function getAvailableStock(): int
```

Returns available stock accounting for active reservations.

#### hasStock()

```php
public function hasStock(int $quantity = 1): bool
```

Check if current stock is >= quantity.

#### hasAvailableStock()

```php
public function hasAvailableStock(int $quantity = 1): bool
```

Check if available stock (minus reservations) is >= quantity.

#### isLowStock()

```php
public function isLowStock(?int $threshold = null): bool
```

Check if stock is below threshold. Uses config default if not specified.

### Stock Modification Methods

#### addStock()

```php
public function addStock(
    int $quantity,
    string $reason = 'restock',
    ?string $note = null,
    ?string $userId = null
): StockTransaction
```

Add stock and return the transaction.

#### removeStock()

```php
public function removeStock(
    int $quantity,
    string $reason = 'adjustment',
    ?string $note = null,
    ?string $userId = null
): StockTransaction
```

Remove stock and return the transaction.

### Reservation Methods

#### reserveStock()

```php
public function reserveStock(
    int $quantity,
    string $cartId,
    int $ttlMinutes = 30
): ?StockReservation
```

Reserve stock for a cart. Returns null if insufficient stock.

#### releaseReservedStock()

```php
public function releaseReservedStock(string $cartId): bool
```

Release reservation for a cart.

#### getReservation()

```php
public function getReservation(string $cartId): ?StockReservation
```

Get reservation for a specific cart.

#### getReservedQuantity()

```php
public function getReservedQuantity(): int
```

Get total reserved quantity across all carts.

### History Methods

#### getStockHistory()

```php
public function getStockHistory(int $limit = 50): Collection
```

Get recent stock transactions with user relationship.

---

## Stock Facade

Access via `AIArmada\Stock\Facades\Stock`.

### addStock()

```php
Stock::addStock(
    Model $model,
    int $quantity,
    string $reason = 'restock',
    ?string $note = null,
    ?string $userId = null
): StockTransaction
```

### removeStock()

```php
Stock::removeStock(
    Model $model,
    int $quantity,
    string $reason = 'adjustment',
    ?string $note = null,
    ?string $userId = null
): StockTransaction
```

### adjustStock()

```php
Stock::adjustStock(
    Model $model,
    int $currentStock,
    int $actualStock,
    ?string $note = null,
    ?string $userId = null
): ?StockTransaction
```

Adjust stock from current to actual. Returns null if no change needed.

### getCurrentStock()

```php
Stock::getCurrentStock(Model $model): int
```

### getStockHistory()

```php
Stock::getStockHistory(Model $model, int $limit = 50): Collection
```

### hasStock()

```php
Stock::hasStock(Model $model, int $quantity = 1): bool
```

### isLowStock()

```php
Stock::isLowStock(Model $model, ?int $threshold = null): bool
```

---

## StockReservations Facade

Access via `AIArmada\Stock\Facades\StockReservations`.

### reserve()

```php
StockReservations::reserve(
    Model $stockable,
    int $quantity,
    string $cartId,
    int $ttlMinutes = 30
): ?StockReservation
```

Reserve stock. Returns null if insufficient available stock.

### release()

```php
StockReservations::release(Model $stockable, string $cartId): bool
```

Release a specific reservation.

### releaseAllForCart()

```php
StockReservations::releaseAllForCart(string $cartId): int
```

Release all reservations for a cart. Returns count released.

### commitReservations()

```php
StockReservations::commitReservations(
    string $cartId,
    ?string $orderId = null
): array
```

Convert reservations to stock deductions. Returns array of transactions.

### deductStock()

```php
StockReservations::deductStock(
    Model $stockable,
    int $quantity,
    string $reason = 'sale',
    ?string $orderId = null
): StockTransaction
```

Deduct stock directly without reservation.

### getAvailableStock()

```php
StockReservations::getAvailableStock(Model $stockable): int
```

Get stock minus active reservations.

### hasAvailableStock()

```php
StockReservations::hasAvailableStock(Model $stockable, int $quantity = 1): bool
```

### getReservedQuantity()

```php
StockReservations::getReservedQuantity(Model $stockable): int
```

### getReservation()

```php
StockReservations::getReservation(Model $stockable, string $cartId): ?StockReservation
```

### extend()

```php
StockReservations::extend(
    Model $stockable,
    string $cartId,
    int $minutes = 30
): ?StockReservation
```

Extend a reservation's expiry.

### cleanupExpired()

```php
StockReservations::cleanupExpired(): int
```

Delete expired reservations. Returns count deleted.

---

## Models

### StockTransaction

| Property | Type | Description |
|----------|------|-------------|
| `id` | string (UUID) | Primary key |
| `stockable_type` | string | Polymorphic type |
| `stockable_id` | string | Polymorphic ID |
| `user_id` | ?string | User who made transaction |
| `quantity` | int | Amount |
| `type` | string | 'in' or 'out' |
| `reason` | ?string | Transaction reason |
| `note` | ?string | Additional notes |
| `transaction_date` | Carbon | When it occurred |

**Relationships:**
- `stockable()` - MorphTo
- `user()` - BelongsTo

**Methods:**
- `isInbound()` - Check if type is 'in'
- `isOutbound()` - Check if type is 'out'
- `isSale()` - Check if reason is 'sale'
- `isAdjustment()` - Check if reason is 'adjustment'

### StockReservation

| Property | Type | Description |
|----------|------|-------------|
| `id` | string (UUID) | Primary key |
| `stockable_type` | string | Polymorphic type |
| `stockable_id` | string | Polymorphic ID |
| `cart_id` | string | Cart identifier |
| `quantity` | int | Reserved amount |
| `expires_at` | Carbon | Expiry time |

**Relationships:**
- `stockable()` - MorphTo

**Methods:**
- `isValid()` - Check if not expired
- `isExpired()` - Check if expired
- `extend(int $minutes)` - Extend expiry

**Scopes:**
- `active()` - Only non-expired
- `expired()` - Only expired
- `forCart(string $cartId)` - Filter by cart
- `forStockable(Model $model)` - Filter by model

---

## StockableInterface

Interface for stockable models. Implemented by `HasStock` trait.

```php
interface StockableInterface
{
    public function getCurrentStock(): int;
    public function hasStock(int $quantity = 1): bool;
    public function isLowStock(?int $threshold = null): bool;
    public function addStock(int $quantity, string $reason = 'restock', ?string $note = null, ?string $userId = null): StockTransaction;
    public function removeStock(int $quantity, string $reason = 'adjustment', ?string $note = null, ?string $userId = null): StockTransaction;
    public function getAvailableStock(): int;
    public function reserveStock(int $quantity, string $cartId, int $ttlMinutes = 30): ?StockReservation;
    public function releaseReservedStock(string $cartId): bool;
}
```

---

## Commands

### stock:cleanup-reservations

```bash
php artisan stock:cleanup-reservations
```

Cleans up expired reservations. Respects `cleanup.keep_expired_for_minutes` config.

Schedule in Kernel:

```php
$schedule->command('stock:cleanup-reservations')->everyFiveMinutes();
```

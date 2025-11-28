# Storage Drivers

Choose the right storage backend for your cart architecture.

## Driver Comparison

| Feature | Session | Cache | Database |
|---------|---------|-------|----------|
| Setup | None | Minimal | Migration required |
| Performance | Fast | Fastest | Good |
| Multi-Device | No | Yes | Yes |
| Persistence | Session lifetime | TTL-based | Permanent |
| Concurrency | Basic | Basic | Optimistic locking |
| Queryable | No | No | Yes |

## Choosing a Driver

| Scenario | Driver |
|----------|--------|
| Development/MVP | Session |
| API-driven apps | Cache |
| Multi-device shopping | Cache or Database |
| E-commerce production | Database |
| Abandoned cart recovery | Database |

## Session Driver

Best for simple apps where users shop from a single device.

```php
// config/cart.php
'storage' => 'session',

'session' => [
    'key' => 'cart',
],
```

**Pros:** Zero configuration, works immediately  
**Cons:** Single-device only, lost if session expires

## Cache Driver

Best for high-traffic apps and API backends.

```php
'storage' => 'cache',

'cache' => [
    'prefix' => 'cart',
    'ttl' => 86400, // 24 hours
    'store' => 'redis',
],
```

**Pros:** Fast, scales horizontally, multi-device  
**Cons:** Carts expire, lost on cache flush

### TTL Guidelines

| Cart Type | TTL |
|-----------|-----|
| Guest carts | 12 hours |
| User carts | 7 days |
| Wishlist | 30 days |

## Database Driver

Best for e-commerce with persistent carts and analytics.

```php
'storage' => 'database',

'database' => [
    'table' => 'carts',
    'connection' => null,
    'lock_for_update' => false,
],
```

Run migrations:

```bash
php artisan vendor:publish --tag=cart-migrations
php artisan migrate
```

**Pros:** Persistent, queryable, optimistic locking  
**Cons:** Slower than cache, requires migration

### Optimistic Locking

The database driver uses version numbers to detect concurrent modifications:

```php
use AIArmada\Cart\Exceptions\CartConflictException;

try {
    Cart::update('item-1', ['quantity' => 5]);
} catch (CartConflictException $e) {
    // Reload and retry
    Cart::getCurrentCart()->reload();
    Cart::update('item-1', ['quantity' => 5]);
}
```

### Query Abandoned Carts

```php
$abandoned = DB::table('carts')
    ->where('updated_at', '<', now()->subDays(7))
    ->get();
```

## Switching Drivers

Changing storage config doesn't migrate data. Transfer manually:

```php
// Session → Database
$sessionData = Session::get('cart');
// Change config to database
// Re-add items to new storage
```

## Custom Drivers

Implement `StorageInterface`:

```php
use AIArmada\Cart\Storage\StorageInterface;

class DynamoDBStorage implements StorageInterface
{
    public function has(string $identifier, string $instance = 'default'): bool;
    public function forget(string $identifier, string $instance = 'default'): void;
    public function getItems(string $identifier, string $instance = 'default'): array;
    public function putItems(string $identifier, string $instance, array $items): void;
    public function getConditions(string $identifier, string $instance = 'default'): array;
    public function putConditions(string $identifier, string $instance, array $conditions): void;
    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void;
    public function putMetadata(string $identifier, string $instance, array $metadata): void;
    public function getMetadata(string $identifier, string $instance): array;
    public function getInstances(string $identifier): array;
    public function forgetIdentifier(string $identifier): void;
    public function swapIdentifier(string $oldIdentifier, string $newIdentifier): void;
    // ...
}
```

## Performance Benchmarks

Average of 10,000 operations:

| Operation | Session | Cache (Redis) | Database (PostgreSQL) |
|-----------|---------|---------------|-----------------------|
| Add Item | 0.8ms | 1.2ms | 4.5ms |
| Get Cart | 0.5ms | 0.9ms | 3.2ms |
| Update Item | 0.9ms | 1.3ms | 5.1ms |

## Next Steps

- [Configuration](configuration.md) – Storage configuration options
- [Concurrency](concurrency.md) – Handling conflicts
- [User Migration](identifiers-and-migration.md) – Guest-to-user flow

# Concurrency

Handle concurrent cart modifications with optimistic locking.

## The Problem

Multiple requests modifying the same cart simultaneously can cause:

- Lost updates (changes overwritten)
- Inconsistent totals
- Duplicate items

```php
// Tab 1: Add item A
Cart::add('item-a', 'Product A', Money::MYR(1000), 1);

// Tab 2: Add item B (same time)
Cart::add('item-b', 'Product B', Money::MYR(2000), 1);

// Without locking, one add may be lost
```

## Solution: Optimistic Locking

The cart uses version-based optimistic locking:

1. Each cart has a `version` number
2. On load, current version is stored
3. On save, version is checked and incremented
4. If versions don't match, a conflict is detected

## Configuration

```php
// config/cart.php
'concurrency' => [
    'enabled' => true,
    'max_retries' => 3,
    'retry_delay' => 100, // milliseconds
],
```

## Handling Conflicts

### Automatic Retry (Default)

The cart automatically retries failed operations:

```php
// This will retry up to 3 times if conflicts occur
Cart::add('sku-123', 'Product', Money::MYR(5000), 1);
```

### Manual Handling

```php
use AIArmada\Cart\Exceptions\ConcurrencyException;

try {
    Cart::add('sku-123', 'Product', Money::MYR(5000), 1);
} catch (ConcurrencyException $e) {
    // Refresh cart and retry manually
    Cart::refresh();
    Cart::add('sku-123', 'Product', Money::MYR(5000), 1);
}
```

### Transaction Wrapper

```php
Cart::transaction(function ($cart) {
    $cart->add('item-1', 'Product 1', Money::MYR(1000), 1);
    $cart->add('item-2', 'Product 2', Money::MYR(2000), 1);
    $cart->applyCondition(new CartCondition([...]));
});
// All changes committed atomically or rolled back
```

## Storage Driver Support

| Driver | Concurrency Support | Mechanism |
|--------|---------------------|-----------|
| Database | ✅ Full | Version column with atomic UPDATE |
| Redis | ✅ Full | WATCH/MULTI transactions |
| Session | ⚠️ Limited | Session locking |
| Array | ❌ None | Single-process only |

### Database Implementation

```sql
-- Atomic update with version check
UPDATE carts 
SET content = ?, version = version + 1 
WHERE identifier = ? AND version = ?

-- If rows_affected = 0, conflict detected
```

### Redis Implementation

```php
// WATCH key for changes
$redis->watch("cart:{$identifier}");

// Start transaction
$redis->multi();
$redis->set("cart:{$identifier}", $serialized);
$redis->incr("cart:{$identifier}:version");

// Execute (fails if key changed)
$result = $redis->exec();
```

## Conflict Strategies

### Last Write Wins

```php
// config/cart.php
'concurrency' => [
    'strategy' => 'last-write-wins',
],
```

Fastest option, but may lose data. Suitable for low-conflict scenarios.

### Optimistic Retry

```php
// config/cart.php
'concurrency' => [
    'strategy' => 'optimistic',
    'max_retries' => 3,
],
```

Default. Retries with exponential backoff.

### Merge

```php
// config/cart.php
'concurrency' => [
    'strategy' => 'merge',
],
```

Attempts to merge conflicting changes automatically.

## Real-World Scenarios

### AJAX Add to Cart

```javascript
// Client-side debounce
const addToCart = debounce(async (sku, qty) => {
    const response = await fetch('/cart/add', {
        method: 'POST',
        body: JSON.stringify({ sku, quantity: qty }),
    });
    
    if (response.status === 409) {
        // Conflict - refresh and retry
        location.reload();
    }
}, 300);
```

### Bulk Operations

```php
// Disable concurrency for bulk imports
Cart::withoutLocking(function () {
    foreach ($items as $item) {
        Cart::add($item['sku'], $item['name'], $item['price'], $item['qty']);
    }
});
```

### Background Jobs

```php
class ProcessCartJob implements ShouldQueue
{
    public $tries = 3;
    
    public function handle(): void
    {
        Cart::setIdentifier($this->cartId);
        
        try {
            Cart::applyCondition($this->condition);
        } catch (ConcurrencyException $e) {
            // Release back to queue
            $this->release(5);
        }
    }
}
```

## Monitoring Conflicts

```php
use AIArmada\Cart\Events\ConcurrencyConflict;

Event::listen(ConcurrencyConflict::class, function ($event) {
    Log::warning('Cart conflict', [
        'identifier' => $event->identifier,
        'expected_version' => $event->expectedVersion,
        'actual_version' => $event->actualVersion,
        'retries' => $event->retryCount,
    ]);
    
    // Track metrics
    Metrics::increment('cart.conflicts');
});
```

## Testing

```php
it('handles concurrent modifications', function () {
    Cart::setIdentifier('test-cart');
    Cart::add('sku-1', 'Product', Money::MYR(1000), 1);
    
    // Simulate concurrent modification
    $version = Cart::getVersion();
    
    // Another process updates
    DB::table('carts')
        ->where('identifier', 'test-cart')
        ->update(['version' => $version + 1]);
    
    // This should retry and succeed
    Cart::add('sku-2', 'Product 2', Money::MYR(2000), 1);
    
    expect(Cart::count())->toBe(2);
});

it('throws after max retries', function () {
    Cart::setIdentifier('test-cart');
    
    // Mock persistent conflicts
    Cart::shouldReceive('save')
        ->andThrow(ConcurrencyException::class);
    
    Cart::add('sku-1', 'Product', Money::MYR(1000), 1);
})->throws(ConcurrencyException::class);
```

## Best Practices

1. **Keep operations atomic** - Group related changes in transactions
2. **Use short-lived carts** - Clear after checkout
3. **Monitor conflict rates** - High rates indicate architecture issues
4. **Prefer database/Redis** - For multi-server deployments
5. **Implement client-side debouncing** - Reduce rapid-fire requests

## Next Steps

- [Storage](storage.md) – Driver configuration
- [Events](events.md) – Monitor cart changes
- [Troubleshooting](troubleshooting.md) – Common issues

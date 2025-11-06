# ⚡ Concurrency & Conflict Resolution

> **Handle concurrent cart modifications with optimistic locking—prevent lost updates in high-traffic scenarios.**

When multiple requests modify the same cart simultaneously, conflicts can occur. The cart package uses optimistic locking with version numbers to detect and handle these conflicts.

## 📋 Table of Contents

- [Understanding Concurrency](#understanding-concurrency)
- [Optimistic Locking](#optimistic-locking)
- [CartConflictException](#cartconflictexception)
- [Retry Patterns](#retry-patterns)
- [Testing Concurrency](#testing-concurrency)
- [Troubleshooting](#troubleshooting)

---

## Understanding Concurrency

### What Is Concurrency?

Concurrency occurs when multiple operations attempt to modify the same cart at the same time:

```
Time →

[Request A] ─────▶ Read Cart (v1) ─────▶ Update Qty ─────▶ Save (v2) ✅
                      │
                      └───────────────▶ Read Cart (v1) ─────▶ Remove Item ─────▶ Save (v2?) ❌ CONFLICT!
[Request B]
```

**Without concurrency control:** Request B's changes might overwrite Request A's changes (lost update problem).

**With concurrency control:** Request B detects the conflict and retries with the latest data.

### Why Concurrency Matters

| Scenario | Without Control | With Control |
|----------|----------------|--------------|
| User adds item twice (rapid clicks) | Quantity becomes 1 (lost update) | Quantity becomes 2 (correct) |
| API + Frontend updates | One update lost | Both updates applied |
| Multiple devices (same user) | Data inconsistency | Consistent across devices |

---

## Optimistic Locking

### How It Works

The database storage driver uses a `version` column to track cart changes:

1. Read cart (version = 5)
2. Modify cart in memory
3. Save with version check: `UPDATE carts SET ... WHERE id = ? AND version = 5`
4. If no rows updated → conflict detected → throw `CartConflictException`

### Database Schema

```sql
CREATE TABLE carts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    instance VARCHAR(255) NOT NULL DEFAULT 'default',
    items JSON NOT NULL,
    conditions JSON NOT NULL,
    metadata JSON NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY carts_identifier_instance_unique (identifier, instance),
    INDEX carts_identifier_index (identifier)
);
```

### Configuration

```php
// config/cart.php
return [
    'storage' => [
        'driver' => 'database', // Required for optimistic locking
    ],
    
    'database' => [
        'connection' => null, // Use default connection
        'table' => 'carts',
    ],
];
```

### When Conflicts Occur

Conflicts happen when:
- User clicks "Add to Cart" rapidly (double-click)
- Same cart modified from multiple devices/sessions
- Background jobs update cart while user is active
- High-traffic checkout pages

---

## CartConflictException

### Exception Details

```php
namespace AIArmada\Cart\Exceptions;

final class CartConflictException extends CartException
{
    // Thrown when optimistic lock fails
    // Message: "Cart conflict detected. Another process modified the cart."
}
```

### Handling Conflicts

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Exceptions\CartConflictException;

try {
    Cart::add('product-1', 'Product 1', 1999, 1);
} catch (CartConflictException $e) {
    // Cart was modified by another process
    // Retry the operation with fresh data
    Cart::refresh(); // Re-fetch latest cart state
    Cart::add('product-1', 'Product 1', 1999, 1);
}
```

---

## Retry Patterns

### Simple Retry

```php
$maxAttempts = 3;
$attempt = 0;

while ($attempt < $maxAttempts) {
    try {
        Cart::add('product-1', 'Product 1', 1999, 1);
        break; // Success
    } catch (CartConflictException $e) {
        $attempt++;
        if ($attempt >= $maxAttempts) {
            throw $e; // Give up after max attempts
        }
        usleep(100000); // Wait 100ms before retry
    }
}
```

### Exponential Backoff

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Exceptions\CartConflictException;

function retryWithBackoff(callable $operation, int $maxAttempts = 3): mixed
{
    $attempt = 0;
    
    while ($attempt < $maxAttempts) {
        try {
            return $operation();
        } catch (CartConflictException $e) {
            $attempt++;
            if ($attempt >= $maxAttempts) {
                throw $e;
            }
            
            // Exponential backoff: 100ms, 200ms, 400ms
            $delay = 100000 * (2 ** ($attempt - 1));
            usleep($delay);
        }
    }
}

// Usage
retryWithBackoff(fn() => Cart::add('product-1', 'Product 1', 1999, 1));
```

### Controller Example

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Exceptions\CartConflictException;

class CartController extends Controller
{
    public function add(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'name' => 'required|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
        ]);

        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                Cart::add(
                    $validated['id'],
                    $validated['name'],
                    $validated['price'],
                    $validated['quantity']
                );
                
                return response()->json([
                    'success' => true,
                    'cart' => [
                        'count' => Cart::count(),
                        'total' => Cart::total()->format(),
                    ],
                ]);
            } catch (CartConflictException $e) {
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cart is busy. Please try again.',
                    ], 409);
                }
                usleep(100000 * $attempt); // Backoff
            }
        }
    }
}
```

---

## Testing Concurrency

### Simulate Conflicts

```php
use Illuminate\Support\Facades\DB;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Exceptions\CartConflictException;

it('detects cart conflicts', function () {
    // Requires database driver
    config(['cart.storage.driver' => 'database']);
    
    Cart::add('product-1', 'Product 1', 1000, 1);
    
    // Simulate concurrent modification by manually updating version
    DB::table('carts')
        ->where('identifier', Cart::getIdentifier())
        ->where('instance', 'default')
        ->update(['version' => DB::raw('version + 1')]);
    
    // Next cart operation should detect conflict
    expect(fn() => Cart::add('product-2', 'Product 2', 2000, 1))
        ->toThrow(CartConflictException::class);
});
```

### Test Retry Logic

```php
it('retries on conflict', function () {
    $attempts = 0;
    $maxAttempts = 3;
    
    while ($attempts < $maxAttempts) {
        try {
            Cart::add('product-1', 'Product 1', 1000, 1);
            break;
        } catch (CartConflictException $e) {
            $attempts++;
            if ($attempts >= $maxAttempts) {
                throw $e;
            }
        }
    }
    
    expect(Cart::has('product-1'))->toBeTrue();
    expect($attempts)->toBeLessThan($maxAttempts);
});
```

---

## Troubleshooting

### Issue: Frequent Conflicts

**Symptoms:**
- Many `CartConflictException` errors
- Users report "cart is busy" messages

**Solutions:**

1. **Reduce rapid clicks:**
```javascript
// Debounce add-to-cart button
let addToCartTimer;
document.querySelector('#add-to-cart').addEventListener('click', (e) => {
    clearTimeout(addToCartTimer);
    addToCartTimer = setTimeout(() => {
        fetch('/cart/add', { method: 'POST', /* ... */ });
    }, 300); // Wait 300ms between clicks
});
```

2. **Increase retry attempts:**
```php
// config/cart.php (custom config)
'concurrency' => [
    'max_retries' => 5, // Increase from 3
],
```

3. **Check database performance:**
```bash
# Monitor slow queries
php artisan db:show
php artisan db:monitor
```

### Issue: Version Mismatch

**Symptoms:**
- Cart operations fail silently
- Version column stuck at 0

**Solutions:**

1. **Verify migration ran:**
```bash
php artisan migrate:status
```

2. **Check table schema:**
```sql
DESCRIBE carts;
-- Ensure 'version' column exists and is INT UNSIGNED NOT NULL DEFAULT 1
```

3. **Reset cart:**
```php
Cart::clear();
Cart::clearMetadata();
```

### Issue: Conflicts with Session Driver

**Symptoms:**
- `CartConflictException` thrown with session driver

**Solution:**

Optimistic locking only works with the **database driver**:

```php
// config/cart.php
'storage' => [
    'driver' => 'database', // Required for concurrency control
],
```

Session and cache drivers don't support versioning. Switch to database driver for high-traffic scenarios.

---

## Best Practices

1. **Use database driver** for production (enables optimistic locking)
2. **Implement retry logic** with exponential backoff in controllers
3. **Debounce user interactions** (buttons, rapid clicks)
4. **Monitor conflict rate** - high rates indicate performance issues
5. **Test concurrency scenarios** in your test suite
6. **Handle exceptions gracefully** - show user-friendly messages

---

## Additional Resources

- [Storage Drivers](storage.md) – Choose the right storage backend
- [Configuration](configuration.md) – Database storage configuration
- [Troubleshooting](troubleshooting.md) – General cart issues

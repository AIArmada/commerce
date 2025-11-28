# Troubleshooting

Common issues and solutions.

## Items Not Persisting

**Symptom:** Cart items disappear after page reload.

**Cause:** Session not started or storage misconfigured.

**Solutions:**

```php
// 1. Ensure session middleware is active
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        \Illuminate\Session\Middleware\StartSession::class,
        // ...
    ],
];

// 2. Check storage driver
// config/cart.php
'storage' => [
    'driver' => 'session', // or 'database', 'cache'
],

// 3. For database storage, run migrations
php artisan migrate
```

---

## Inconsistent Totals

**Symptom:** Total doesn't match expected calculation.

**Cause:** Conditions applying in unexpected order or stale data.

**Solutions:**

```php
// 1. Check condition order
$conditions = Cart::getConditions();
foreach ($conditions as $condition) {
    dump($condition->getName(), $condition->getTarget(), $condition->getValue());
}

// 2. Verify target levels
// 'item' → applies to each item
// 'subtotal' → applies to sum of items
// 'total' → applies after other conditions

// 3. Refresh cart from storage
Cart::refresh();
```

---

## Money Format Errors

**Symptom:** "Currency mismatch" or format exceptions.

**Cause:** Mixing currencies or passing invalid formats.

**Solutions:**

```php
// ❌ Wrong - mixing currencies
$myr = Money::MYR(1000);
$usd = Money::USD(500);
$total = $myr->add($usd); // Throws exception

// ✅ Correct - consistent currency
Cart::add('item-1', 'Product', Money::MYR(5000), 1);
Cart::add('item-2', 'Product', Money::MYR(3000), 1);

// ✅ Or use strings (auto-converted to default currency)
Cart::add('item-1', 'Product', '50.00', 1);
```

---

## Cart Not Found After Login

**Symptom:** Guest cart empty after authentication.

**Cause:** Cart identifier changed but not migrated.

**Solutions:**

```php
// Migrate in auth listener
use Illuminate\Auth\Events\Login;

class MigrateCartOnLogin
{
    public function handle(Login $event): void
    {
        $guestId = session()->getId();
        $userId = "user-{$event->user->id}";
        
        if (Cart::exists($guestId)) {
            Cart::migrate($guestId, $userId, 'merge');
        }
        
        Cart::setIdentifier($userId);
    }
}
```

---

## Concurrency Errors

**Symptom:** `ConcurrencyException` thrown.

**Cause:** Multiple simultaneous cart modifications.

**Solutions:**

```php
// 1. Increase retry attempts
// config/cart.php
'concurrency' => [
    'max_retries' => 5,
    'retry_delay' => 200,
],

// 2. Handle in controller
use AIArmada\Cart\Exceptions\ConcurrencyException;

try {
    Cart::add('sku', 'Product', Money::MYR(1000), 1);
} catch (ConcurrencyException $e) {
    return back()->with('error', 'Please try again.');
}

// 3. Use transactions for multiple operations
Cart::transaction(function ($cart) {
    $cart->add(...);
    $cart->applyCondition(...);
});
```

---

## Database Storage Not Working

**Symptom:** "Table not found" or storage errors.

**Solutions:**

```bash
# 1. Publish and run migrations
php artisan vendor:publish --tag=cart-migrations
php artisan migrate

# 2. Check table exists
php artisan tinker
>>> Schema::hasTable('carts')
```

```php
// 3. Verify configuration
// config/cart.php
'storage' => [
    'driver' => 'database',
    'table' => 'carts',
    'connection' => null, // or specific connection
],
```

---

## Events Not Firing

**Symptom:** Listeners not triggered.

**Solutions:**

```php
// 1. Verify event registration
// EventServiceProvider
protected $listen = [
    \AIArmada\Cart\Events\ItemAdded::class => [
        \App\Listeners\YourListener::class,
    ],
];

// 2. Check listener is not throwing
class YourListener
{
    public function handle(ItemAdded $event): void
    {
        try {
            // your code
        } catch (\Exception $e) {
            Log::error('Listener failed', ['error' => $e->getMessage()]);
        }
    }
}

// 3. Ensure events are not suppressed
// DON'T wrap in withoutEvents if you need events
Cart::withoutEvents(function () {
    Cart::add(...); // No events fired here
});
```

---

## Condition Not Applying

**Symptom:** Discount/tax not reflected in total.

**Solutions:**

```php
// 1. Check condition is added
$exists = Cart::getCondition('MyDiscount');
if (!$exists) {
    Cart::applyCondition(new CartCondition([...]));
}

// 2. Verify target is correct
new CartCondition([
    'name' => 'Discount',
    'type' => 'discount',
    'target' => 'subtotal', // Not 'item' for cart-wide discount
    'value' => '-10%',
]);

// 3. Check value format
'-10%'  // 10% off
'-1000' // RM10 off (cents)
'1000'  // RM10 added (shipping/fee)
'8%'    // 8% added (tax)
```

---

## Redis Cache Issues

**Symptom:** Cart data stale or missing with Redis.

**Solutions:**

```php
// 1. Check Redis connection
// config/database.php
'redis' => [
    'cache' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
    ],
],

// 2. Use dedicated connection
// config/cart.php
'storage' => [
    'driver' => 'cache',
    'store' => 'redis',
    'ttl' => 43200, // 12 hours
],

// 3. Clear cache if corrupted
php artisan cache:clear
```

---

## Performance Issues

**Symptom:** Slow cart operations.

**Solutions:**

```php
// 1. Use database/Redis for production
'storage' => [
    'driver' => 'database', // or 'cache' with Redis
],

// 2. Reduce condition recalculation
// Cache complex calculations
$shippingCost = Cache::remember("shipping:{$zipcode}", 3600, fn () =>
    $this->calculateShipping($zipcode)
);

// 3. Batch updates
Cart::transaction(function ($cart) {
    foreach ($items as $item) {
        $cart->add($item['sku'], $item['name'], $item['price'], $item['qty']);
    }
});

// 4. Use eager loading for buyables
$products = Product::whereIn('id', $cartItemIds)->get()->keyBy('id');
```

---

## Serialization Errors

**Symptom:** "Cannot serialize Closure" or similar.

**Cause:** Attempting to serialize closures or resources.

**Solutions:**

```php
// ❌ Wrong - closure in metadata
Cart::add('sku', 'Product', Money::MYR(1000), 1, [
    'formatter' => fn ($price) => number_format($price),
]);

// ✅ Correct - only serializable data
Cart::add('sku', 'Product', Money::MYR(1000), 1, [
    'format' => 'currency', // Store config, not closure
]);
```

---

## Testing Issues

**Symptom:** Tests interfering with each other.

**Solutions:**

```php
// 1. Clear cart in setUp
protected function setUp(): void
{
    parent::setUp();
    Cart::clear();
    Cart::clearConditions();
    Cart::clearMetadata();
}

// 2. Use array driver for tests
// phpunit.xml
<env name="CART_STORAGE_DRIVER" value="array"/>

// 3. Use RefreshDatabase trait
use Illuminate\Foundation\Testing\RefreshDatabase;

class CartTest extends TestCase
{
    use RefreshDatabase;
}
```

---

## Getting Help

If issues persist:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Enable debug mode: `APP_DEBUG=true`
3. Review configuration: `php artisan config:show cart`
4. Open an issue with:
   - Laravel version
   - Cart package version
   - Storage driver
   - Steps to reproduce

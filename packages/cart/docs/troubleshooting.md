# 🔧 Troubleshooting Guide

Common cart issues and their solutions. Each issue includes symptoms, causes, and fixes.

## 📋 Table of Contents

- [Setup & Installation](#-setup--installation)
- [Cart Operations](#-cart-operations)
- [Storage Issues](#-storage-issues)
- [Migration Problems](#-migration-problems)
- [Concurrency Errors](#-concurrency-errors)
- [Money & Currency](#-money--currency)
- [Conditions](#-conditions)
- [Events](#-events)
- [Getting Help](#-getting-help)

---

## 🚀 Setup & Installation

### Issue 1: Package Not Found

**Symptoms:**
```
Package aiarmada/cart could not be found
```

**Solutions:**

1. **Verify composer.json:**
```json
{
    "require": {
        "aiarmada/cart": "^1.0"
    }
}
```

2. **Update Composer:**
```bash
composer update aiarmada/cart
```

3. **Check repository access** (if private package):
```bash
composer config --list
```

### Issue 2: Service Provider Not Registered

**Symptoms:**
```
Class 'Cart' not found
```

**Solutions:**

1. **Auto-discovery (Laravel 11+):**
```bash
composer dump-autoload
php artisan config:clear
```

2. **Manual registration** (if needed):
```php
// config/app.php
'providers' => [
    AIArmada\Cart\CartServiceProvider::class,
],

'aliases' => [
    'Cart' => AIArmada\Cart\Facades\Cart::class,
],
```

### Issue 3: Migration Not Running

**Symptoms:**
```
Table 'carts' doesn't exist
```

**Solutions:**

1. **Publish and run migrations:**
```bash
php artisan vendor:publish --tag=cart-migrations
php artisan migrate
```

2. **Check migration file exists:**
```bash
ls -la database/migrations/*_create_carts_table.php
```

3. **Force migration (development only):**
```bash
php artisan migrate:fresh
```

### Issue 4: Config Not Published

**Symptoms:**
```
Undefined array key "storage"
```

**Solutions:**

1. **Publish config:**
```bash
php artisan vendor:publish --tag=cart-config
```

2. **Verify file exists:**
```bash
ls -la config/cart.php
```

3. **Clear config cache:**
```bash
php artisan config:clear
php artisan config:cache
```

---

## 🛒 Cart Operations

### Issue 5: Items Not Persisting

**Symptoms:** Cart empty after page refresh.

**Causes:**
- Session driver with expired session
- Cache driver with short TTL
- Identifier mismatch

**Solutions:**

1. **Check storage driver:**
```php
// config/cart.php
'storage' => [
    'driver' => 'database', // Not 'session'
],
```

2. **Increase cache TTL:**
```php
'cache' => [
    'ttl' => 604800, // 7 days
],
```

3. **Verify identifier:**
```php
Log::info('Cart identifier', ['identifier' => Cart::getIdentifier()]);
```

### Issue 6: Duplicate Items

**Symptoms:** Same product appears multiple times.

**Causes:**
- Different product IDs used (e.g., `"123"` vs `123`)
- Inconsistent ID generation

**Solutions:**

1. **Use consistent IDs:**
```php
// ❌ BAD: Different types
Cart::add('123', ...);   // String
Cart::add(123, ...);     // Integer (creates duplicate!)

// ✅ GOOD: Same type
Cart::add('product-123', ...);
Cart::add('product-123', ...); // Updates existing item
```

2. **Check item IDs:**
```php
Cart::all()->pluck('id')->dd();
```

### Issue 7: Quantity Not Updating

**Symptoms:** `Cart::update()` doesn't change quantity.

**Causes:**
- Wrong item ID
- Concurrency conflict
- Validation failing silently

**Solutions:**

1. **Verify item exists:**
```php
if (!Cart::has('product-123')) {
    throw new \Exception('Item not found');
}

Cart::update('product-123', 5);
```

2. **Check for conflicts:**
```php
try {
    Cart::update('product-123', 5);
} catch (\AIArmada\Cart\Exceptions\CartConflictException $e) {
    retry(3, fn() => Cart::update('product-123', 5), 100);
}
```

3. **Enable debug logging:**
```php
Log::info('Before update', ['qty' => Cart::get('product-123')->quantity]);
Cart::update('product-123', 5);
Log::info('After update', ['qty' => Cart::get('product-123')->quantity]);
```

---

## 💾 Storage Issues

### Issue 8: Database Connection Failed

**Symptoms:**
```
SQLSTATE[HY000] [2002] Connection refused
```

**Solutions:**

1. **Check database config:**
```bash
php artisan db:show
```

2. **Test connection:**
```php
DB::connection()->getPdo();
```

3. **Verify .env:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Issue 9: Redis Connection Failed

**Symptoms:**
```
Connection refused [tcp://127.0.0.1:6379]
```

**Solutions:**

1. **Start Redis:**
```bash
redis-server
```

2. **Check Redis connection:**
```bash
redis-cli ping
# Should return: PONG
```

3. **Verify Redis config:**
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Issue 10: Version Column Missing

**Symptoms:**
```
Column not found: 1054 Unknown column 'version'
```

**Solutions:**

1. **Run migration:**
```bash
php artisan migrate
```

2. **Add column manually (if needed):**
```sql
ALTER TABLE carts ADD COLUMN version BIGINT DEFAULT 1;
```

3. **Verify column exists:**
```bash
php artisan db:table carts
```

---

## 🔄 Migration Problems

### Issue 11: Cart Not Migrating on Login

**Symptoms:** Items disappear after login.

**Causes:**
- Event listener not registered
- Migration disabled in config
- Session ID mismatch

**Solutions:**

1. **Register listener:**
```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \Illuminate\Auth\Events\Login::class => [
        \App\Listeners\MigrateGuestCart::class,
    ],
];
```

2. **Clear event cache:**
```bash
php artisan event:clear
php artisan event:cache
```

3. **Enable migration:**
```php
// config/cart.php
'migration' => [
    'enabled' => true,
],
```

4. **Log session IDs:**
```php
Log::info('Session ID', [
    'guest' => 'guest:' . session()->getId(),
    'user' => 'user:' . auth()->id(),
]);
```

### Issue 12: Merge Strategy Not Working

**Symptoms:** Items lost during merge.

**Solutions:**

1. **Check strategy:**
```php
dd(config('cart.migration.strategy'));
// Should be: 'add_quantities', 'keep_highest_quantity', etc.
```

2. **Test manually:**
```php
$guestId = 'guest:abc';
$userId = 'user:123';

// Guest cart
Cart::setIdentifier($guestId)->add('A', 'Product A', Money::MYR(1000), 2);

// User cart
Cart::setIdentifier($userId)->add('A', 'Product A', Money::MYR(1000), 3);

// Merge
Cart::setIdentifier($userId, $guestId);

// Check result
dd(Cart::get('A')->quantity); // Should be 5 with 'add_quantities'
```

---

## ⚡ Concurrency Errors

### Issue 13: CartConflictException

**Symptoms:**
```
CartConflictException: Version mismatch
```

**Causes:** Multiple requests updating cart simultaneously.

**Solutions:**

1. **Implement retry:**
```php
retry(3, function () {
    Cart::add('product-1', 'Product 1', Money::MYR(1000), 1);
}, 100);
```

2. **Switch to pessimistic locking:**
```php
// config/cart.php
'database' => [
    'locking' => 'pessimistic',
],
```

3. **Use distributed locking:**
```php
Cache::lock("cart:user:123")->block(5, function () {
    Cart::add('product-1', 'Product 1', Money::MYR(1000), 1);
});
```

### Issue 14: High Conflict Rate

**Symptoms:** Many conflicts (> 5%).

**Solutions:**

1. **Profile contention:**
```php
Log::info('Conflict detected', [
    'identifier' => Cart::getIdentifier(),
    'timestamp' => microtime(true),
]);
```

2. **Reduce transaction scope:**
```php
// ❌ BAD
DB::transaction(function () {
    Cart::add(...);
    Product::update(...);
    sendEmail(...);
});

// ✅ GOOD
DB::transaction(fn() => Cart::add(...));
Product::update(...);
sendEmail(...);
```

3. **Add indexes:**
```sql
CREATE INDEX idx_carts_identifier_instance ON carts(identifier, instance);
```

---

## 🚀 Performance Issues

### Issue 15: Slow Cart Operations

**Symptoms:** Operations take > 500ms.

**Solutions:**

1. **Profile queries:**
```php
DB::enableQueryLog();
Cart::add('product-1', 'Product 1', Money::MYR(1000), 1);
dd(DB::getQueryLog());
```

2. **Add missing indexes:**
```sql
CREATE INDEX idx_carts_identifier_instance ON carts(identifier, instance);
CREATE INDEX idx_carts_updated_at ON carts(updated_at);
```

3. **Switch to cache driver:**
```php
// config/cart.php
'storage' => [
    'driver' => 'cache',
],
```

---

## 💰 Money & Currency

### Issue 17: Incorrect Total Calculation

**Symptoms:** Total doesn't match item prices.

**Causes:**
- Floating-point arithmetic
- Conditions applied incorrectly
- Currency mismatch

**Solutions:**

1. **Always use Money objects:**
```php
// ❌ BAD
Cart::add('A', 'Product A', 19.99, 1); // Float!

// ✅ GOOD
Cart::add('A', 'Product A', Money::MYR(1999), 1); // Integer cents
```

2. **Check condition order:**
```php
Cart::getConditions()->sortBy('order')->dd();
```

3. **Verify currency:**
```php
Cart::all()->each(function ($item) {
    expect($item->price->getCurrency()->getCode())->toBe('MYR');
});
```

### Issue 18: Currency Mismatch Error

**Symptoms:**
```
Cannot add money with different currencies
```

**Solutions:**

1. **Use single currency per cart:**
```php
// All items must use same currency
Cart::add('A', 'Product A', Money::MYR(1000), 1);
Cart::add('B', 'Product B', Money::MYR(2000), 1);
// Don't mix: Money::USD(1000)
```

2. **Convert currencies before adding:**
```php
$priceInMYR = convertCurrency($priceUSD, 'USD', 'MYR');
Cart::add('A', 'Product A', $priceInMYR, 1);
```

3. **Use separate instances:**
```php
Cart::setInstance('cart-myr')->add(..., Money::MYR(1000), ...);
Cart::setInstance('cart-usd')->add(..., Money::USD(100), ...);
```

---

## 🎯 Conditions

### Issue 19: Condition Not Applied

**Symptoms:** Discount doesn't reduce total.

**Solutions:**

1. **Check condition added:**
```php
Cart::getConditions()->dd();
```

2. **Verify condition parameters:**
```php
$condition = new CartCondition('discount', -10, 'percentage');
dd([
    'name' => $condition->getName(),
    'value' => $condition->getValue(),
    'type' => $condition->getType(),
]);
```

3. **Check condition order:**
```php
$condition = new CartCondition('discount', -10, 'percentage', 1); // order = 1
Cart::addCondition($condition);
```

4. **Test calculation:**
```php
Cart::add('A', 'Product A', Money::MYR(10000), 1); // RM 100.00
Cart::addCondition(new CartCondition('discount', -10, 'percentage'));
dd(Cart::total()->format()); // Should be: RM 90.00
```

### Issue 20: Wrong Condition Order

**Symptoms:** Calculations incorrect with multiple conditions.

**Solutions:**

1. **Set explicit order:**
```php
Cart::addCondition(new CartCondition('tax', 6, 'percentage', 2));        // Apply last
Cart::addCondition(new CartCondition('discount', -10, 'percentage', 1)); // Apply first
```

2. **Check effective order:**
```php
Cart::getConditions()->sortBy('order')->pluck('name')->dd();
// Should be: ['discount', 'tax']
```

---

## 📡 Events

### Issue 21: Events Not Firing

**Symptoms:** Event listeners not called.

**Solutions:**

1. **Enable events:**
```php
// config/cart.php
'events' => [
    'enabled' => true,
],
```

2. **Clear event cache:**
```bash
php artisan event:clear
php artisan event:cache
```

3. **Verify listener registered:**
```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \AIArmada\Cart\Events\CartItemAdded::class => [
        \App\Listeners\NotifyCartUpdate::class,
    ],
];
```

4. **Test manually:**
```php
Event::fake();
Cart::add('A', 'Product A', Money::MYR(1000), 1);
Event::assertDispatched(\AIArmada\Cart\Events\CartItemAdded::class);
```

### Issue 22: Queued Listeners Not Processing

**Symptoms:** Listener implements `ShouldQueue` but doesn't run.

**Solutions:**

1. **Start queue worker:**
```bash
php artisan queue:work
```

2. **Check queue connection:**
```env
QUEUE_CONNECTION=database
```

3. **Verify job exists:**
```bash
php artisan queue:failed
```

4. **Retry failed jobs:**
```bash
```bash
php artisan queue:retry all
```

---

## 📚 Related Documentation

- [Getting Started](getting-started.md) – Setup guide
- [Storage Drivers](storage.md) – Driver troubleshooting
- [Concurrency](concurrency.md) – Conflict resolution
- [Configuration](configuration.md) – Config reference

---

**Need help?** Check the examples in the documentation or open an issue with details about your setup.
```

---

## 🧪 Testing

### Issue 23: Tests Failing with "Cart Not Found"

**Symptoms:**
```
Item not found in cart
```

**Solutions:**

1. **Clear cart between tests:**
```php
beforeEach(function () {
    Cart::clear();
    Cart::clearMetadata();
});
```

2. **Set identifier explicitly:**
```php
Cart::setIdentifier('test-cart');
Cart::add('A', 'Product A', Money::MYR(1000), 1);
```

3. **Use RefreshDatabase:**
```php
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
```

### Issue 24: Flaky Tests (Random Failures)

**Symptoms:** Tests pass sometimes, fail other times.

**Solutions:**

1. **Isolate tests:**
```php
afterEach(function () {
    Cart::setInstance('default');
    Cart::clear();
    Cache::flush();
});
```

2. **Use factories:**
```php
$product = Product::factory()->create(['price' => 1000]);
Cart::add($product->id, $product->name, Money::MYR(1000), 1);
```

3. **Disable parallelization (debugging):**
```bash
php artisan test --without-parallel
```

---

## 🏭 Production Issues

### Issue 25: Cart Loss in Production

**Symptoms:** Carts disappear randomly.

**Solutions:**

1. **Use database driver:**
```php
// config/cart.php
'storage' => [
    'driver' => 'database',
],
```

2. **Increase session lifetime:**
```php
// config/session.php
'lifetime' => 10080, // 7 days
```

3. **Monitor cart deletions:**
```php
Cart::deleted(function ($cart) {
    Log::warning('Cart deleted', ['identifier' => $cart->identifier]);
});
```

### Issue 26: Performance Degradation

**Symptoms:** Slow response times under load.

**Solutions:**

1. **Enable query caching:**
```php
Cart::remember(60)->all(); // Cache for 60 seconds
```

2. **Use Redis for cache:**
```env
CACHE_DRIVER=redis
```

3. **Profile with Telescope:**
```bash
php artisan telescope:install
```

---

## 🆘 Getting Help

### Debug Checklist

Before asking for help, gather this information:

- [ ] Laravel version: `php artisan --version`
- [ ] PHP version: `php -v`
- [ ] Cart package version: `composer show aiarmada/cart`
- [ ] Storage driver: `config('cart.storage.driver')`
- [ ] Error message (full stack trace)
- [ ] Steps to reproduce
- [ ] Expected vs actual behavior

### Enable Full Logging

```php
// config/cart.php
'logging' => [
    'enabled' => true,
    'channel' => 'stack',
    'level' => 'debug',
],
```

### Export Diagnostics

```php
// Generate diagnostic report
php artisan cart:diagnostics > cart-diagnostics.txt
```

### Community Resources

- **GitHub Issues:** [github.com/aiarmada/cart/issues](https://github.com/aiarmada/cart/issues)
- **Documentation:** Full guides in `docs/` directory
- **Stack Overflow:** Tag with `laravel` and `shopping-cart`

### Filing Bug Reports

Include:

1. **Environment:**
```
Laravel: 12.x
PHP: 8.4
Cart: 1.x
Storage: database (PostgreSQL 16)
```

2. **Minimal reproduction:**
```php
// Fails with CartConflictException
Cart::add('A', 'Product A', Money::MYR(1000), 1);
Cart::update('A', 2);
```

3. **Full error:**
```
AIArmada\Cart\Exceptions\CartConflictException: Version mismatch
at vendor/aiarmada/cart/src/Services/CartService.php:123
```

---

## �� Related Documentation

- **[Getting Started](getting-started.md)** – Setup guide
- **[Storage Drivers](storage.md)** – Driver troubleshooting
- **[Concurrency](concurrency-and-retry.md)** – Conflict resolution
- **[Testing](testing.md)** – Test troubleshooting
- **[Configuration](configuration.md)** – Config reference

---

## 🔗 Quick Fixes

### Clear All Caches

```bash
php artisan optimize:clear
```

### Reset Cart State

```php
Cart::setInstance('default');
Cart::clear();
Cart::clearMetadata();
Cache::flush();
```

### Force Refresh

```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

---

**Still stuck?** [Open an issue](https://github.com/aiarmada/cart/issues/new) with diagnostic information.

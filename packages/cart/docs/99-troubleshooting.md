---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions for the Cart package.

## Installation Issues

### "Cart table not found"

**Cause:** Migrations haven't been run.

**Solution:**
```bash
php artisan migrate
```

### "Class Cart not found"

**Cause:** Configuration cached with old data.

**Solution:**
```bash
php artisan config:clear
php artisan cache:clear
```

### "Money currency not found"

**Cause:** Invalid currency code in configuration.

**Solution:**
```php
// config/cart.php
'money' => [
    'default_currency' => 'MYR', // Use valid ISO 4217 code
],
```

Money display values are formatted from integer minor units. If a condition
value is a decimal string, it is normalized before display; check the
configured ISO 4217 currency when normalization fails.

## Runtime Issues

### CartConflictException

**Cause:** A concurrent write modified the same cart between a snapshot read and a revision-checked write. Domain operations retry against a fresh snapshot internally, so this surfaces only after those bounded retries are exhausted under sustained contention.

**Solution:**

Retrying the domain call is safe: it re-reads a fresh snapshot and recomputes.

```php
use AIArmada\Cart\Exceptions\CartConflictException;

try {
    Cart::add('SKU-001', 'Product', 999, 1);
} catch (CartConflictException $e) {
    // Option 1: Retry
    Cart::add('SKU-001', 'Product', 999, 1);
    
    // Option 2: Refresh and retry
    $cart = Cart::getContent();
    // Re-apply changes
}
```

### "Cannot serialize Closure"

**Cause:** Attempting to store closures in cart metadata or dynamic conditions without factory keys.

**Solution:**
```php
// WRONG - closures can't be serialized
Cart::setMetadata('callback', fn() => 'test');

// CORRECT - use factory keys for dynamic conditions
Cart::registerDynamicCondition(
    condition: $condition,
    ruleFactoryKey: 'subtotal-at-least', // Factory key instead of closure
    metadata: ['amount' => 5000]
);
```

### Dynamic Conditions Not Applying

**Cause:** Rules factory not set or conditions not evaluated.

**Solution:**
```php
use AIArmada\Cart\Contracts\RulesFactoryInterface;

// Ensure factory is set
Cart::setRulesFactory(app(RulesFactoryInterface::class));

// Force evaluation
Cart::evaluateDynamicConditions();

// Or ensure totals trigger evaluation
$total = Cart::total(); // This evaluates if dirty
```

### Incorrect Totals After Item Updates

**Cause:** Pipeline cache not invalidated.

**Solution:**
```php
// Force cache invalidation
Cart::invalidatePipelineCache();

// Or disable lazy pipeline temporarily
Cart::withoutLazyPipeline();
$total = Cart::total();
Cart::withLazyPipeline();
```

Stale owner after an Octane request usually indicates that an integration
retained a CartFactory instance outside its request/job lifecycle. Resolve the
factory again inside the current request; the container binding is scoped.

Global condition validation or application throws in owner mode when no owner
context is available. Resolve the tenant or wrap deliberate global work in
OwnerContext::withOwner(null, ...).

## Multi-Tenancy Issues

### Carts Not Scoped to Tenant

**Cause:** Owner not set before operations.

**Solution:**
```php
// Option 1 — ambient owner context: every public Cart entry point
// re-resolves the current owner
OwnerContext::withOwner($tenant, function () {
    Cart::add('SKU-001', 'Product', 999, 1);
});

// Option 2 — pinned manager for admin / cross-tenant work
$tenantCart = Cart::forOwner($tenant);
$tenantCart->add('SKU-001', 'Product', 999, 1);
```

Use the pinned manager returned by `Cart::forOwner($tenant)` when operating
outside that owner's ambient context; calling the facade after discarding the
result uses the ambient owner instead.

### Snapshot Sync Throws AuthorizationException

**Cause:** A cart owned by one owner is being mutated while a different owner
(or no owner) is ambient, so the snapshot synchronizer refuses to label the
snapshot with the wrong owner.

**Solution:** Run the mutation inside the owning context or against the pinned
manager, for example:

```php
$tenantCart = Cart::forOwner($tenant);

OwnerContext::withOwner($tenant, function () use ($tenantCart) {
    $tenantCart->add('SKU-001', 'Product', 999, 1);
});
```

### Cross-Tenant Data Leakage

**Cause:** `include_global` enabled when it shouldn't be.

**Solution:**
```php
// config/cart.php
'owner' => [
    'enabled' => true,
    'include_global' => false, // Strict isolation
],
```

Login migration discovers all guest instances. Check
StorageInterface::getInstances($sessionId) and remember that itemsMerged is the
total quantity migrated across those instances.

## Performance Issues

### Slow Total Calculations

**Cause:** Large cart with many conditions, lazy pipeline disabled.

**Solution:**
```php
// Enable lazy pipeline
'performance' => [
    'lazy_pipeline' => true,
],

// Check cache stats
$stats = Cart::getPipelineCacheStats();
// If 'is_stale' is frequently true, investigate what's invalidating cache
```

### Database Connection Exhaustion

**Cause:** Many concurrent requests with `lock_for_update` enabled.

**Solution:**
```php
// Disable pessimistic locking (use revision-checked writes only)
'database' => [
    'lock_for_update' => false,
],
```

## Login Migration Issues

### Guest Cart Not Migrating

**Cause:** Session ID not captured before auth regenerates it.

**Solution:**

Ensure the listeners are registered in the correct order:

```php
// CartServiceProvider registers:
// 1. HandleUserLoginAttempt on Attempting (captures session ID)
// 2. HandleUserLogin on Login (migrates cart)
```

Check session ID is being captured:

```php
Event::listen(Attempting::class, function ($event) {
    $sessionId = session()->getId();
    logger('Session before auth: ' . $sessionId);
});
```

### Merge Conflicts

**Cause:** User and guest both have same item.

**Solution:**
```php
// Configure merge strategy
'migration' => [
    'merge_strategy' => 'add_quantities', // or 'keep_highest_quantity'
],
```

### Migration Fails With "the user has no usable ID"

**Cause:** The user key passed to the migration helpers is missing or empty —
for example an unsaved model (`getKey()` returns `null`), an empty string, or
an authenticated guard whose user exposes no ID.

**Solution:**

Resolve the authenticated user before migrating and pass its scalar key
(`int` or UUID string). Identifiers are preserved byte-for-byte and are never
truncated to a numeric prefix, so UUID keys migrate to their exact cart
identifier:

```php
$userId = auth()->user()?->getKey();

if ($userId === null || $userId === '') {
    // Handle the unauthenticated / unsaved user explicitly
    return;
}

$action->execute(userId: $userId, instance: 'default', sessionId: $sessionId);
```

## JSON Column Issues (SQLite)

### JSON Queries Failing

**Cause:** SQLite has limited JSON support.

**Solution:**
1. Use PostgreSQL for production
2. Upgrade to SQLite 3.38+ for better JSON support

## Debugging Tools

### View Cart State

```php
// Full cart content
dump(Cart::content());

// Pipeline cache status
dump(Cart::getPipelineCacheStats());

// Dynamic conditions state
dump(Cart::getDynamicConditions());
dump(Cart::isDynamicConditionsDirty());

// Storage info
dump([
    'id' => Cart::getId(),
    'version' => Cart::getVersion(),
    'identifier' => Cart::getIdentifier(),
]);
```

### Enable Debug Logging

```php
// In a listener or middleware
Event::listen('*', function ($eventName, $data) {
    if (str_starts_with($eventName, 'AIArmada\Cart\Events')) {
        logger($eventName, ['data' => $data]);
    }
});
```

### Test Storage Directly

```php
$storage = Cart::storage();

// Check if cart exists
dump($storage->has('user-123', 'default'));

// Get raw data
dump($storage->getItems('user-123', 'default'));
dump($storage->getConditions('user-123', 'default'));
dump($storage->getAllMetadata('user-123', 'default'));
```

## Getting Help

1. Check the [GitHub Issues](https://github.com/ai-armada/commerce/issues)
2. Review test cases in `tests/src/Cart/`
3. Enable verbose logging and capture the output
4. Provide cart state and configuration when reporting issues

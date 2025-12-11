# Deferred Execution with `defer()`

> **Document:** Performance Patterns  
> **Package:** `aiarmada/commerce-support`  
> **Status:** Implementation Guide  
> **Laravel Version:** 11.23+ (native `defer()` helper)

---

## Overview

Laravel 11.23 introduced the `defer()` helper function that executes closures **after the HTTP response has been sent** to the user. This allows you to run non-critical tasks without making users wait, improving perceived performance without the overhead of a full queue system.

---

## The Problem

Many commerce operations perform tasks the user doesn't need to wait for:

```php
// Traditional approach - user waits for everything
public function checkout(CheckoutRequest $request): Response
{
    $order = Order::create($request->validated());
    
    // These slow things down, but user doesn't need to wait
    $this->updateInventoryLevels($order);      // 50ms
    $this->updateCustomerAnalytics($order);    // 30ms
    $this->notifyAffiliates($order);           // 40ms
    $this->syncToExternalSystems($order);      // 100ms
    
    return redirect()->route('orders.confirmation', $order);
}
```

**Result:** User waits 220+ extra milliseconds for things they don't care about.

---

## The Solution: `defer()`

```php
public function checkout(CheckoutRequest $request): Response
{
    $order = Order::create($request->validated());
    
    // These run AFTER response is sent
    defer(fn () => $this->updateInventoryLevels($order));
    defer(fn () => $this->updateCustomerAnalytics($order));
    defer(fn () => $this->notifyAffiliates($order));
    defer(fn () => $this->syncToExternalSystems($order));
    
    return redirect()->route('orders.confirmation', $order);
}
```

**Result:** User gets instant response, tasks complete in background.

---

## How It Works

1. User hits the endpoint
2. Laravel stores closures in `DeferredCallbackCollection`
3. Response is sent to user immediately
4. PHP process stays open (FastCGI)
5. `InvokeDeferredCallbacks` middleware's `terminate()` method runs
6. Closures execute in the same PHP process

```
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│   Request    │───>│   Response   │───>│   Deferred   │
│   Handling   │    │   Sent       │    │   Callbacks  │
└──────────────┘    └──────────────┘    └──────────────┘
      ~50ms              instant            200ms+
                                        (user doesn't wait)
```

---

## Key Features

### Conditional Execution

By default, deferred tasks only run on **successful responses** (2xx, 3xx status codes):

```php
defer(function () {
    // Only runs if response is successful
    $this->trackAnalytics();
});
```

### Always Execute (Even on Errors)

Use `->always()` for tasks that must run regardless of response status:

```php
defer(function () {
    // Runs even on 4xx/5xx responses
    $this->logAttempt();
})->always();
```

---

## When to Use `defer()` vs Queues

| Use `defer()` | Use Queues |
|--------------|------------|
| Small, quick tasks (< 1 second) | Long-running tasks |
| Database updates, cache warming | Video encoding, CSV imports |
| Analytics tracking | Email sending (retries needed) |
| Notification to internal systems | External API calls (unreliable) |
| No retry logic needed | Needs retry/failure handling |
| Same server context needed | Can run on different server |

---

## Commerce Package Use Cases

### Orders Package

```php
// After order creation
defer(fn () => $this->reserveInventory($order));
defer(fn () => event(new OrderCreated($order)));
defer(fn () => $this->updateCustomerMetrics($order->customer));
```

### Affiliates Package

```php
// After commission calculation
defer(fn () => $this->updateAffiliateVolume($affiliate));
defer(fn () => $this->checkRankQualification($affiliate));
defer(fn () => $this->notifyUpline($affiliate, $commission));
```

### Cart Package

```php
// After cart operations
defer(fn () => $this->syncCartToSession($cart));
defer(fn () => $this->warmPriceCache($cart->items));
```

### Shipping Package

```php
// After rate calculation
defer(fn () => $this->cacheCarrierRates($rates));
defer(fn () => $this->logRateShoppingRequest($origin, $destination));
```

### Products Package

```php
// After product view
defer(fn () => $this->incrementViewCount($product));
defer(fn () => $this->trackBrowsingHistory($user, $product));
defer(fn () => $this->updatePopularityScore($product));
```

### Customers Package

```php
// After profile updates
defer(fn () => $this->recalculateSegments($customer));
defer(fn () => $this->updateSearchIndex($customer));
```

---

## Pattern: Deferrable Service Trait

A reusable trait for services that need deferred execution:

```php
namespace AIArmada\CommerceSupport\Traits;

trait DefersExecution
{
    /**
     * Execute a callback after the response is sent.
     *
     * @param  \Closure  $callback
     * @param  bool  $always  Run even on error responses
     */
    protected function deferCallback(\Closure $callback, bool $always = false): void
    {
        if ($always) {
            defer($callback)->always();
        } else {
            defer($callback);
        }
    }

    /**
     * Execute multiple callbacks after the response is sent.
     *
     * @param  array<\Closure>  $callbacks
     */
    protected function deferCallbacks(array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            defer($callback);
        }
    }
}
```

---

## Testing Considerations

Deferred callbacks run after the response, which can complicate testing:

```php
it('updates analytics after checkout', function () {
    // This won't work - deferred callback hasn't run yet
    post('/checkout', $data);
    expect($analytics->wasUpdated)->toBeTrue(); // Fails!
});
```

**Solution:** Use the `DeferredCallbackCollection` facade in tests:

```php
use Illuminate\Support\Facades\Defer;

it('updates analytics after checkout', function () {
    post('/checkout', $data);
    
    // Manually invoke deferred callbacks
    Defer::flush();
    
    expect($analytics->wasUpdated)->toBeTrue();
});
```

---

## Best Practices

### ✅ Do

- Use for fire-and-forget operations
- Keep deferred tasks under 1 second
- Use `->always()` for logging/auditing
- Group related defers for readability

### ❌ Don't

- Use for tasks needing retry logic
- Use for long-running operations
- Depend on deferred result in response
- Use when queue workers are available and appropriate

---

## Comparison with `once()`

| Feature | `once()` | `defer()` |
|---------|----------|-----------|
| **When it runs** | Immediately, cached for request | After response sent |
| **Purpose** | Avoid repeated calculations | Improve response time |
| **Result accessible** | Yes | No |
| **Best for** | Expensive computations | Side effects |

They can be combined:

```php
public function getTotal(): Money
{
    return once(function () {
        $total = $this->calculateTotal();
        
        // Track analytics after response
        defer(fn () => $this->trackTotalCalculation($total));
        
        return $total;
    });
}
```

---

## Navigation

**Previous:** [01-request-scoped-caching.md](01-request-scoped-caching.md)  
**Next:** [03-concurrent-execution.md](03-concurrent-execution.md)  
**Back to:** [PROGRESS.md](PROGRESS.md)

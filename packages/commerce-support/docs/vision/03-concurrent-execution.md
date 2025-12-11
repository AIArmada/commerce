# Concurrent Execution with Laravel Concurrency

> **Document:** Performance Patterns  
> **Package:** `aiarmada/commerce-support`  
> **Status:** Implementation Guide  
> **Laravel Version:** 11.x+ (Concurrency facade)

---

## Overview

Laravel's `Concurrency` facade enables executing multiple independent tasks **simultaneously** instead of sequentially. This dramatically improves performance when fetching data from multiple sources or performing independent calculations.

---

## The Problem

Commerce operations often need data from multiple independent sources:

```php
// Sequential execution - slow!
public function getDashboardData(): array
{
    $userCount = DB::table('users')->count();           // 50ms
    $orderCount = DB::table('orders')->count();         // 60ms
    $revenue = DB::table('orders')->sum('total');       // 80ms
    $topProducts = Product::popular()->limit(10)->get(); // 100ms
    $recentActivity = Activity::recent()->limit(20)->get(); // 70ms
    
    return compact('userCount', 'orderCount', 'revenue', 'topProducts', 'recentActivity');
}
```

**Total Time:** 360ms (sum of all queries)

---

## The Solution: `Concurrency::run()`

```php
use Illuminate\Support\Facades\Concurrency;

public function getDashboardData(): array
{
    [$userCount, $orderCount, $revenue, $topProducts, $recentActivity] = Concurrency::run([
        fn () => DB::table('users')->count(),
        fn () => DB::table('orders')->count(),
        fn () => DB::table('orders')->sum('total'),
        fn () => Product::popular()->limit(10)->get(),
        fn () => Activity::recent()->limit(20)->get(),
    ]);
    
    return compact('userCount', 'orderCount', 'revenue', 'topProducts', 'recentActivity');
}
```

**Total Time:** ~100ms (slowest query only)

---

## How It Works

Laravel serializes closures and dispatches them to separate PHP processes via a hidden Artisan command. Each process:

1. Unserializes the closure
2. Executes it independently  
3. Serializes the result back to the parent

```
┌─────────────┐
│   Parent    │
│   Process   │
└──────┬──────┘
       │ spawn
       ├──────────────┬──────────────┬──────────────┐
       ▼              ▼              ▼              ▼
┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐
│  Process 1  │ │  Process 2  │ │  Process 3  │ │  Process 4  │
│  (50ms)     │ │  (60ms)     │ │  (80ms)     │ │  (100ms)    │
└─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘
       │              │              │              │
       └──────────────┴──────────────┴──────────────┘
                              │
                              ▼
                    Results collected: ~100ms
```

---

## Available Drivers

| Driver | Description | Best For |
|--------|-------------|----------|
| `process` | Default. Spawns child processes | Web requests |
| `fork` | Uses PHP forking (CLI only) | Console commands, better performance |
| `sync` | Sequential execution | Testing |

```php
// Use fork driver for CLI commands
$results = Concurrency::driver('fork')->run([...]);

// Test mode - execute sequentially
$results = Concurrency::driver('sync')->run([...]);
```

> **Note:** The `fork` driver requires `spatie/fork` package and only works in CLI context.

---

## Deferred Concurrent Execution

Use `Concurrency::defer()` for concurrent tasks where you don't need the results:

```php
use Illuminate\Support\Facades\Concurrency;

// Run concurrently AFTER response is sent
Concurrency::defer([
    fn () => Metrics::report('users'),
    fn () => Metrics::report('orders'),
    fn () => Metrics::report('products'),
]);
```

This combines `defer()` and `Concurrency` - tasks run concurrently, but only after the response is sent.

---

## Commerce Package Use Cases

### Multi-Carrier Rate Shopping (Shipping)

```php
class RateShoppingEngine
{
    public function getAllCarrierRates(AddressData $origin, AddressData $destination): array
    {
        $carriers = $this->shippingManager->getActiveCarriers();
        
        // Fetch rates from all carriers concurrently
        return Concurrency::run(
            collect($carriers)->mapWithKeys(fn ($carrier) => [
                $carrier->code => fn () => $carrier->getRates($origin, $destination)
            ])->all()
        );
    }
}
```

**Before:** 5 carriers × 500ms each = 2.5 seconds  
**After:** ~500ms (slowest carrier)

### Dashboard Analytics (Orders)

```php
class OrderDashboardService
{
    public function getMetrics(Carbon $from, Carbon $to): array
    {
        [$totalOrders, $totalRevenue, $avgOrderValue, $topProducts, $ordersByStatus] = 
            Concurrency::run([
                fn () => Order::whereBetween('created_at', [$from, $to])->count(),
                fn () => Order::whereBetween('created_at', [$from, $to])->sum('total_minor'),
                fn () => Order::whereBetween('created_at', [$from, $to])->avg('total_minor'),
                fn () => $this->getTopSellingProducts($from, $to),
                fn () => Order::whereBetween('created_at', [$from, $to])
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),
            ]);
        
        return compact('totalOrders', 'totalRevenue', 'avgOrderValue', 'topProducts', 'ordersByStatus');
    }
}
```

### Affiliate Network Metrics

```php
class NetworkService
{
    public function getAffiliateOverview(Affiliate $affiliate): array
    {
        [$personalSales, $teamSales, $activeDownlines, $pendingCommissions, $rankProgress] = 
            Concurrency::run([
                fn () => $this->calculatePersonalSales($affiliate),
                fn () => $this->calculateTeamSales($affiliate),
                fn () => $this->getActiveDownlineCount($affiliate),
                fn () => $this->getPendingCommissions($affiliate),
                fn () => $this->calculateRankProgress($affiliate),
            ]);
        
        return compact('personalSales', 'teamSales', 'activeDownlines', 'pendingCommissions', 'rankProgress');
    }
}
```

### Customer 360 View

```php
class CustomerProfileService
{
    public function get360View(Customer $customer): array
    {
        return Concurrency::run([
            'orders' => fn () => $customer->orders()->recent()->limit(10)->get(),
            'lifetime_value' => fn () => $customer->orders()->sum('total_minor'),
            'segments' => fn () => $customer->segments()->get(),
            'addresses' => fn () => $customer->addresses()->get(),
            'activity' => fn () => $customer->activities()->recent()->limit(20)->get(),
            'subscriptions' => fn () => $customer->subscriptions()->active()->get(),
        ]);
    }
}
```

### Inventory Multi-Warehouse Check

```php
class InventoryService
{
    public function checkStockAcrossWarehouses(Product $product): array
    {
        $warehouses = Warehouse::active()->get();
        
        return Concurrency::run(
            $warehouses->mapWithKeys(fn ($warehouse) => [
                $warehouse->id => fn () => $warehouse->getStockLevel($product)
            ])->all()
        );
    }
}
```

---

## When to Use Concurrency

### ✅ Good Candidates

| Scenario | Example |
|----------|---------|
| **Independent data fetches** | Dashboard with multiple widgets |
| **Multi-source aggregation** | Carrier rate shopping |
| **Parallel API calls** | Fetching from multiple external services |
| **Heavy computations** | Price calculations for many products |
| **Report generation** | Gathering data for PDFs |

### ❌ Not Suitable

| Scenario | Why |
|----------|-----|
| **Sequential dependencies** | Task B depends on Task A's result |
| **Shared state** | Tasks modify same data |
| **Simple queries** | Overhead exceeds benefit |
| **Single data source** | No parallelism benefit |

---

## Performance Considerations

### Process Overhead

Each concurrent task spawns a new PHP process. For very fast tasks, the overhead may exceed the benefit:

```php
// BAD: Overhead likely exceeds benefit
Concurrency::run([
    fn () => 1 + 1,
    fn () => 2 + 2,
]);

// GOOD: Significant time savings
Concurrency::run([
    fn () => Http::get('external-api-1.com/data'),
    fn () => Http::get('external-api-2.com/data'),
]);
```

**Rule of thumb:** Use concurrency when tasks take > 50ms each.

### Memory Considerations

Results are serialized between processes. Be mindful of large result sets:

```php
// BAD: Serializing 10000 Eloquent models
Concurrency::run([
    fn () => Product::all(), // 10000 products
]);

// GOOD: Return only what you need
Concurrency::run([
    fn () => Product::pluck('id', 'name'), // Just IDs and names
]);
```

### ⚠️ Serialization Limitations

Closures are serialized to child processes, which has important implications:

```php
// WORKS: Simple closure with primitives
Concurrency::run([
    fn () => DB::table('orders')->count(),
]);

// PROBLEMATIC: Closure capturing Eloquent model
$order = Order::find(1);
Concurrency::run([
    fn () => $order->items()->count(), // Model may not serialize properly!
]);

// SOLUTION: Pass primitives, re-fetch in child
$orderId = 1;
Concurrency::run([
    fn () => Order::find($orderId)->items()->count(), // Works!
]);

// PROBLEMATIC: Closures using $this with complex dependencies
class MyService {
    public function getData() {
        return Concurrency::run([
            fn () => $this->complexDependency->doWork(), // May fail!
        ]);
    }
}
```

**Best Practices for Serialization:**

| Do | Don't |
|----|-------|
| Pass primitive values (IDs, strings, dates) | Pass Eloquent models directly |
| Re-fetch models inside the closure | Rely on `$this` with complex deps |
| Return simple arrays/collections | Return huge datasets |
| Use stateless closures | Use closures with database connections |

---

## Combining with `once()` and `defer()`

All three patterns can work together:

```php
class DashboardService
{
    public function getMetrics(): array
    {
        // Cache the concurrent fetch for the request
        return once(function () {
            $metrics = Concurrency::run([
                'orders' => fn () => Order::count(),
                'revenue' => fn () => Order::sum('total_minor'),
                'customers' => fn () => Customer::count(),
            ]);
            
            // Update analytics after response
            defer(fn () => $this->trackDashboardView($metrics));
            
            return $metrics;
        });
    }
}
```

---

## Testing

Use the `sync` driver to test concurrent code sequentially:

```php
use Illuminate\Support\Facades\Concurrency;

beforeEach(function () {
    Concurrency::fake(); // or use sync driver
});

it('fetches dashboard metrics', function () {
    $service = new DashboardService();
    $metrics = $service->getMetrics();
    
    expect($metrics)->toHaveKeys(['orders', 'revenue', 'customers']);
});
```

---

## Summary: Performance Pattern Selection

| Pattern | Purpose | When to Use |
|---------|---------|-------------|
| `once()` | Cache computation | Same calculation called multiple times |
| `defer()` | Defer execution | Tasks user doesn't need to wait for |
| `Concurrency::run()` | Parallel execution | Independent tasks that can run simultaneously |
| `Concurrency::defer()` | Deferred parallel | Multiple deferred tasks that can run together |

---

## Navigation

**Previous:** [02-deferred-execution.md](02-deferred-execution.md)  
**Back to:** [PROGRESS.md](PROGRESS.md)

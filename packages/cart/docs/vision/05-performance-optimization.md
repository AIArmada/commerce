# Cart Package Vision - Performance Optimization

> **Document:** 05-performance-optimization.md  
> **Series:** Cart Package Vision  
> **Focus:** Multi-tier Caching, Lazy Evaluation, Query Optimization

---

## Table of Contents

1. [Multi-Tier Caching Architecture](#1-multi-tier-caching-architecture)
2. [Lazy Condition Pipeline Evaluation](#2-lazy-condition-pipeline-evaluation)
3. [Database Query Optimization](#3-database-query-optimization)
4. [Memory Management](#4-memory-management)

---

## 1. Multi-Tier Caching Architecture

### Vision Statement

Implement **multi-tier caching** to serve cart data with sub-millisecond latency for hot paths while maintaining consistency.

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│            MULTI-TIER CART CACHING ARCHITECTURE                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                     L1: IN-MEMORY                        │   │
│  │              (Octane / Swoole / RoadRunner)              │   │
│  │                     TTL: Request lifetime                │   │
│  │                  Latency: < 0.1ms                        │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │ Miss                            │
│                              ▼                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                     L2: REDIS CACHE                      │   │
│  │                   (Local Redis Cluster)                  │   │
│  │                     TTL: 5-15 minutes                    │   │
│  │                    Latency: < 2ms                        │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │ Miss                            │
│                              ▼                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                   L3: PERSISTENT STORE                   │   │
│  │                  (PostgreSQL + PgBouncer)                │   │
│  │                   TTL: Permanent                         │   │
│  │                    Latency: < 20ms                       │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  CACHE INVALIDATION STRATEGY:                                  │
│  • Write-through on mutations                                  │
│  • Event-driven invalidation via Redis Pub/Sub                 │
│  • Version-based cache keys for optimistic invalidation        │
└─────────────────────────────────────────────────────────────────┘
```

### Implementation

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Infrastructure\Caching;

use AIArmada\Cart\Domain\Aggregates\Cart;
use AIArmada\Cart\Domain\Contracts\CartRepositoryInterface;
use AIArmada\Cart\Domain\ValueObjects\CartId;

/**
 * Multi-tier caching decorator for Cart Repository.
 */
final class CachedCartRepository implements CartRepositoryInterface
{
    private array $l1Cache = []; // In-memory (request-scoped)
    
    public function __construct(
        private CartRepositoryInterface $inner,
        private \Redis $redis,
        private int $l2TtlSeconds = 300, // 5 minutes
        private string $cachePrefix = 'cart:v2:',
    ) {}
    
    public function getById(CartId $id): ?Cart
    {
        $key = $id->toString();
        
        // L1: In-memory check
        if (isset($this->l1Cache[$key])) {
            return $this->l1Cache[$key];
        }
        
        // L2: Redis check
        $cached = $this->redis->get($this->cachePrefix . $key);
        if ($cached !== false) {
            $cart = $this->deserialize($cached);
            $this->l1Cache[$key] = $cart; // Populate L1
            return $cart;
        }
        
        // L3: Database
        $cart = $this->inner->getById($id);
        
        if ($cart !== null) {
            $this->cacheCart($cart);
        }
        
        return $cart;
    }
    
    public function getByIdentifier(string $identifier, string $instance): ?Cart
    {
        $lookupKey = "{$identifier}:{$instance}";
        
        // Check L1 index
        if (isset($this->l1Cache['idx:' . $lookupKey])) {
            $cartId = $this->l1Cache['idx:' . $lookupKey];
            return $this->getById(CartId::fromString($cartId));
        }
        
        // Check L2 index
        $cartId = $this->redis->get($this->cachePrefix . 'idx:' . $lookupKey);
        if ($cartId !== false) {
            $this->l1Cache['idx:' . $lookupKey] = $cartId;
            return $this->getById(CartId::fromString($cartId));
        }
        
        // Fallback to database
        $cart = $this->inner->getByIdentifier($identifier, $instance);
        
        if ($cart !== null) {
            $this->cacheCart($cart);
            $this->cacheIndex($identifier, $instance, $cart->getId()->toString());
        }
        
        return $cart;
    }
    
    public function save(Cart $cart): void
    {
        // Write-through: Update database first
        $this->inner->save($cart);
        
        // Invalidate and refresh cache
        $this->invalidateCart($cart->getId()->toString());
        $this->cacheCart($cart);
        
        // Publish invalidation event for other instances
        $this->redis->publish('cart:invalidate', json_encode([
            'cart_id' => $cart->getId()->toString(),
            'identifier' => $cart->getIdentifier(),
            'instance' => $cart->getInstance(),
            'version' => $cart->getVersion(),
        ]));
    }
    
    public function delete(CartId $id): void
    {
        $this->inner->delete($id);
        $this->invalidateCart($id->toString());
    }
    
    public function nextIdentity(): CartId
    {
        return $this->inner->nextIdentity();
    }
    
    private function cacheCart(Cart $cart): void
    {
        $key = $cart->getId()->toString();
        $serialized = $this->serialize($cart);
        
        // L1
        $this->l1Cache[$key] = $cart;
        
        // L2
        $this->redis->setex(
            $this->cachePrefix . $key,
            $this->l2TtlSeconds,
            $serialized
        );
    }
    
    private function cacheIndex(string $identifier, string $instance, string $cartId): void
    {
        $lookupKey = "{$identifier}:{$instance}";
        
        $this->l1Cache['idx:' . $lookupKey] = $cartId;
        $this->redis->setex(
            $this->cachePrefix . 'idx:' . $lookupKey,
            $this->l2TtlSeconds,
            $cartId
        );
    }
    
    private function invalidateCart(string $cartId): void
    {
        unset($this->l1Cache[$cartId]);
        $this->redis->del($this->cachePrefix . $cartId);
    }
    
    private function serialize(Cart $cart): string
    {
        return serialize($cart);
    }
    
    private function deserialize(string $data): Cart
    {
        return unserialize($data);
    }
}
```

### Cache Warming Strategy

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Jobs;

use AIArmada\Cart\Infrastructure\Caching\CachedCartRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Warm cache for active carts during low-traffic periods.
 */
final class WarmCartCacheJob implements ShouldQueue
{
    use Queueable;
    
    public function __construct(
        private int $hoursActive = 24,
        private int $batchSize = 100,
    ) {}
    
    public function handle(CachedCartRepository $repository): void
    {
        $activeCarts = DB::table('carts')
            ->where('updated_at', '>=', now()->subHours($this->hoursActive))
            ->select('id')
            ->cursor();
        
        foreach ($activeCarts->chunk($this->batchSize) as $batch) {
            foreach ($batch as $row) {
                $repository->getById(CartId::fromString($row->id));
            }
            
            // Avoid overwhelming Redis
            usleep(10000); // 10ms pause between batches
        }
    }
}
```

---

## 2. Lazy Condition Pipeline Evaluation

### Vision Statement

Transform the condition pipeline from **eager evaluation** to **lazy evaluation** with memoization for better performance.

### Current Implementation (Eager)

```php
// Current: Evaluates ALL phases every time
public function process(ConditionPipelineContext $context): ConditionPipelineResult
{
    foreach ($this->phasesInOrder() as $phase) {
        // Always evaluates even if we only need subtotal
        $finalAmount = $this->resolvePhaseAmount($phaseContext);
    }
    return new ConditionPipelineResult(...);
}
```

### Proposed Implementation (Lazy)

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Pipeline;

use AIArmada\Cart\Conditions\Enums\ConditionPhase;

/**
 * Lazy evaluation pipeline with memoization.
 * Only computes phases that are actually requested.
 */
final class LazyConditionPipeline
{
    /** @var array<string, ConditionPhaseResult> */
    private array $memoizedPhases = [];
    
    /** @var array<string, int> */
    private array $memoizedAmounts = [];
    
    private bool $isStale = true;
    
    public function __construct(
        private ConditionPipelineContext $context,
    ) {}
    
    /**
     * Get subtotal - only evaluates phases up to CART_SUBTOTAL.
     */
    public function getSubtotal(): int
    {
        return $this->evaluateUpToPhase(ConditionPhase::CART_SUBTOTAL);
    }
    
    /**
     * Get total - evaluates all phases, but reuses memoized results.
     */
    public function getTotal(): int
    {
        return $this->evaluateUpToPhase(ConditionPhase::GRAND_TOTAL);
    }
    
    /**
     * Get amount after specific phase.
     */
    public function getAmountAfterPhase(ConditionPhase $phase): int
    {
        return $this->evaluateUpToPhase($phase);
    }
    
    /**
     * Mark pipeline as stale (call when cart changes).
     */
    public function invalidate(): void
    {
        $this->isStale = true;
        $this->memoizedPhases = [];
        $this->memoizedAmounts = [];
    }
    
    /**
     * Lazy evaluation with memoization.
     */
    private function evaluateUpToPhase(ConditionPhase $targetPhase): int
    {
        $targetKey = $targetPhase->value;
        
        // Return memoized result if available and fresh
        if (!$this->isStale && isset($this->memoizedAmounts[$targetKey])) {
            return $this->memoizedAmounts[$targetKey];
        }
        
        // Find the last computed phase we can start from
        $startPhase = $this->findLastComputedPhaseBefore($targetPhase);
        $amount = $startPhase 
            ? $this->memoizedAmounts[$startPhase->value]
            : $this->context->initialAmount();
        
        // Only compute phases we haven't computed yet
        foreach ($this->getPhasesBetween($startPhase, $targetPhase) as $phase) {
            $phaseContext = new ConditionPipelinePhaseContext(
                $phase,
                $amount,
                $this->context->conditions()->byPhase($phase),
                $this->context
            );
            
            $result = $this->resolvePhaseAmount($phaseContext);
            
            $this->memoizedPhases[$phase->value] = new ConditionPhaseResult(
                $phase,
                $amount,
                $result,
                $result - $amount,
                $phaseContext->conditions->count()
            );
            
            $this->memoizedAmounts[$phase->value] = $result;
            $amount = $result;
        }
        
        $this->isStale = false;
        
        return $this->memoizedAmounts[$targetKey];
    }
    
    /**
     * Find the last phase we've already computed before the target.
     */
    private function findLastComputedPhaseBefore(ConditionPhase $target): ?ConditionPhase
    {
        $phases = $this->phasesInOrder();
        $lastComputed = null;
        
        foreach ($phases as $phase) {
            if ($phase === $target) {
                break;
            }
            
            if (isset($this->memoizedAmounts[$phase->value])) {
                $lastComputed = $phase;
            }
        }
        
        return $lastComputed;
    }
    
    /**
     * Get phases between start (exclusive) and end (inclusive).
     * 
     * @return array<ConditionPhase>
     */
    private function getPhasesBetween(?ConditionPhase $start, ConditionPhase $end): array
    {
        $phases = $this->phasesInOrder();
        $result = [];
        $started = ($start === null);
        
        foreach ($phases as $phase) {
            if (!$started && $phase === $start) {
                $started = true;
                continue;
            }
            
            if ($started) {
                $result[] = $phase;
            }
            
            if ($phase === $end) {
                break;
            }
        }
        
        return $result;
    }
    
    /**
     * @return array<ConditionPhase>
     */
    private function phasesInOrder(): array
    {
        $phases = ConditionPhase::cases();
        usort($phases, fn ($a, $b) => $a->order() <=> $b->order());
        return $phases;
    }
    
    private function resolvePhaseAmount(ConditionPipelinePhaseContext $context): int
    {
        if ($context->isEmpty()) {
            return $context->baseAmount;
        }
        
        // Apply conditions for this phase
        $amount = $context->baseAmount;
        
        foreach ($context->conditions as $condition) {
            $amount = $condition->apply($amount);
        }
        
        return $amount;
    }
}
```

### Integration with Cart

```php
<?php

// In Cart class
private ?LazyConditionPipeline $lazyPipeline = null;

public function subtotal(): Money
{
    $amount = $this->getLazyPipeline()->getSubtotal();
    return Money::{$this->currency}($amount);
}

public function total(): Money
{
    $amount = $this->getLazyPipeline()->getTotal();
    return Money::{$this->currency}($amount);
}

private function getLazyPipeline(): LazyConditionPipeline
{
    if ($this->lazyPipeline === null) {
        $context = ConditionPipelineContext::fromCart($this);
        $this->lazyPipeline = new LazyConditionPipeline($context);
    }
    
    return $this->lazyPipeline;
}

// Invalidate on mutations
public function addItem(...): CartItem
{
    // ... add logic
    $this->lazyPipeline?->invalidate();
    return $item;
}
```

### Performance Comparison

| Scenario | Eager (Current) | Lazy (Proposed) | Improvement |
|----------|-----------------|-----------------|-------------|
| Get subtotal only | 10 phases | 4 phases | 60% fewer |
| Get subtotal then total | 20 phases | 10 phases | 50% fewer |
| Display cart (subtotal x5) | 50 phases | 4 phases | 92% fewer |
| Complex checkout | 30 phases | 10 phases | 67% fewer |

---

## 3. Database Query Optimization

### Index Recommendations

```sql
-- Add to existing carts table migration
-- These indexes optimize common query patterns

-- Composite index for identifier + instance lookup (most common)
CREATE INDEX CONCURRENTLY idx_carts_identifier_instance_v2 
    ON carts(identifier, instance) 
    INCLUDE (id, version, updated_at);

-- Partial index for active carts (non-expired)
CREATE INDEX CONCURRENTLY idx_carts_active 
    ON carts(identifier, instance) 
    WHERE expires_at IS NULL OR expires_at > NOW();

-- Index for abandoned cart queries
CREATE INDEX CONCURRENTLY idx_carts_abandoned 
    ON carts(updated_at, instance) 
    WHERE items IS NOT NULL 
    AND items != '[]'::jsonb;

-- Index for owner-scoped queries (multi-tenancy)
CREATE INDEX CONCURRENTLY idx_carts_owner 
    ON carts(owner_type, owner_id, instance) 
    WHERE owner_type IS NOT NULL;

-- Partial index for version tracking (CAS operations)
CREATE INDEX CONCURRENTLY idx_carts_version 
    ON carts(id, version);

-- GIN index for item search within carts (PostgreSQL only)
-- Useful for "find carts containing product X"
CREATE INDEX CONCURRENTLY idx_carts_items_product 
    ON carts USING GIN ((items -> 'associated_model'));
```

### Query Optimization Patterns

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Infrastructure\Persistence;

final class OptimizedCartQueries
{
    public function __construct(
        private \Illuminate\Database\ConnectionInterface $db,
        private string $table = 'carts',
    ) {}
    
    /**
     * Optimized single cart lookup with prepared statement reuse.
     */
    public function findByIdentifierAndInstance(
        string $identifier, 
        string $instance
    ): ?object {
        // Uses covering index, avoids table lookup for existence check
        return $this->db->table($this->table)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();
    }
    
    /**
     * Batch load multiple carts efficiently.
     * Use for "load all user's carts" scenarios.
     */
    public function findByIdentifiers(array $identifiers, string $instance): array
    {
        return $this->db->table($this->table)
            ->whereIn('identifier', $identifiers)
            ->where('instance', $instance)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->get()
            ->keyBy('identifier')
            ->toArray();
    }
    
    /**
     * Optimized abandoned cart query with cursor for memory efficiency.
     */
    public function getAbandonedCarts(
        \DateTimeInterface $olderThan,
        ?int $minValueCents = null,
        int $limit = 1000
    ): \Generator {
        $query = $this->db->table($this->table)
            ->where('updated_at', '<', $olderThan)
            ->whereRaw("items IS NOT NULL AND items != '[]'::jsonb")
            ->orderBy('updated_at', 'asc')
            ->limit($limit);
        
        if ($minValueCents !== null) {
            // Use computed/stored column or subquery for total
            $query->whereRaw('(
                SELECT COALESCE(SUM((item->>\'price\')::int * (item->>\'quantity\')::int), 0)
                FROM jsonb_array_elements(items) AS item
            ) >= ?', [$minValueCents]);
        }
        
        foreach ($query->cursor() as $row) {
            yield $row;
        }
    }
    
    /**
     * Atomic version increment with optimistic locking.
     */
    public function updateWithVersionCheck(
        string $id,
        int $expectedVersion,
        array $data
    ): bool {
        $affected = $this->db->table($this->table)
            ->where('id', $id)
            ->where('version', $expectedVersion)
            ->update(array_merge($data, [
                'version' => $expectedVersion + 1,
                'updated_at' => now(),
            ]));
        
        return $affected > 0;
    }
    
    /**
     * Efficient existence check without loading full cart.
     */
    public function exists(string $identifier, string $instance): bool
    {
        return $this->db->table($this->table)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->exists();
    }
    
    /**
     * Get cart count for identifier (all instances).
     */
    public function countByIdentifier(string $identifier): int
    {
        return $this->db->table($this->table)
            ->where('identifier', $identifier)
            ->count();
    }
}
```

### Connection Pooling Configuration

```php
// config/database.php - Optimized for cart workload
'pgsql' => [
    'driver' => 'pgsql',
    'url' => env('DATABASE_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => 'prefer',
    
    // Connection pooling (via PgBouncer)
    'options' => [
        PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', false),
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
    
    // Prepared statement caching
    'prepared_statement_cache_size' => 256,
],
```

---

## 4. Memory Management

### Object Pool Pattern

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Infrastructure\Memory;

/**
 * Object pool for frequently created/destroyed objects.
 * Reduces GC pressure during high-traffic periods.
 */
final class CartItemPool
{
    /** @var \SplQueue<CartItem> */
    private \SplQueue $available;
    
    private int $created = 0;
    private int $maxSize;
    
    public function __construct(int $maxSize = 1000)
    {
        $this->available = new \SplQueue();
        $this->maxSize = $maxSize;
    }
    
    public function acquire(
        string $id,
        string $name,
        int $price,
        int $quantity,
        array $attributes = []
    ): CartItem {
        if (!$this->available->isEmpty()) {
            $item = $this->available->dequeue();
            return $item->reinitialize($id, $name, $price, $quantity, $attributes);
        }
        
        $this->created++;
        return new CartItem($id, $name, $price, $quantity, $attributes);
    }
    
    public function release(CartItem $item): void
    {
        if ($this->available->count() < $this->maxSize) {
            $item->reset(); // Clear references for GC
            $this->available->enqueue($item);
        }
        // Otherwise let it be garbage collected
    }
    
    public function getStats(): array
    {
        return [
            'created' => $this->created,
            'available' => $this->available->count(),
            'max_size' => $this->maxSize,
        ];
    }
}
```

### Streaming Large Cart Operations

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Services;

/**
 * Stream-based cart operations for large carts.
 * Avoids loading entire cart into memory.
 */
final class StreamingCartService
{
    /**
     * Process cart items in chunks without loading entire cart.
     * 
     * @param callable(CartItem): void $processor
     */
    public function processItemsStreaming(
        string $cartId,
        callable $processor,
        int $chunkSize = 100
    ): void {
        $cart = DB::table('carts')->where('id', $cartId)->first(['items']);
        
        if (!$cart || !$cart->items) {
            return;
        }
        
        // Stream JSON parsing for large item arrays
        $items = json_decode($cart->items, true);
        
        foreach (array_chunk($items, $chunkSize) as $chunk) {
            foreach ($chunk as $itemData) {
                $item = CartItem::fromArray($itemData);
                $processor($item);
            }
            
            // Allow GC between chunks
            gc_collect_cycles();
        }
    }
    
    /**
     * Calculate totals without loading full cart (streaming calculation).
     */
    public function calculateTotalsStreaming(string $cartId): array
    {
        $subtotal = 0;
        $itemCount = 0;
        $totalQuantity = 0;
        
        $this->processItemsStreaming($cartId, function (CartItem $item) use (
            &$subtotal, 
            &$itemCount, 
            &$totalQuantity
        ) {
            $subtotal += $item->price * $item->quantity;
            $itemCount++;
            $totalQuantity += $item->quantity;
        });
        
        return [
            'subtotal_cents' => $subtotal,
            'item_count' => $itemCount,
            'total_quantity' => $totalQuantity,
        ];
    }
}
```

### Memory Usage Monitoring

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Diagnostics;

final class CartMemoryProfiler
{
    private array $snapshots = [];
    
    public function snapshot(string $label): void
    {
        $this->snapshots[$label] = [
            'memory' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'time' => microtime(true),
        ];
    }
    
    public function report(): array
    {
        $report = [];
        $previous = null;
        
        foreach ($this->snapshots as $label => $snapshot) {
            $report[$label] = [
                'memory_mb' => round($snapshot['memory'] / 1024 / 1024, 2),
                'peak_mb' => round($snapshot['peak'] / 1024 / 1024, 2),
                'delta_mb' => $previous 
                    ? round(($snapshot['memory'] - $previous['memory']) / 1024 / 1024, 2)
                    : 0,
            ];
            $previous = $snapshot;
        }
        
        return $report;
    }
}

// Usage in cart operations
$profiler = new CartMemoryProfiler();
$profiler->snapshot('start');

$cart = Cart::get($id);
$profiler->snapshot('after_load');

$cart->add($item);
$profiler->snapshot('after_add');

$total = $cart->total();
$profiler->snapshot('after_total');

logger()->debug('Cart memory profile', $profiler->report());
```

---

## Summary: Performance Optimization Priorities

| Optimization | Complexity | Impact | Risk | Phase |
|--------------|------------|--------|------|-------|
| Lazy Pipeline | Low | High | Low | **Phase 1** |
| Database Indexes | Low | Medium | Low | **Phase 1** |
| Redis L2 Cache | Medium | High | Low | **Phase 1** |
| Query Optimization | Medium | Medium | Low | **Phase 1** |
| In-Memory L1 Cache | Low | Medium | Low | **Phase 2** |
| Object Pooling | Medium | Low | Medium | **Phase 3** |
| Streaming Operations | High | Medium | Medium | **Phase 3** |

---

**Next:** [06-database-evolution.md](06-database-evolution.md) - Schema Analysis, Migration Strategy, Event Store

# Cart Package Vision - Database Evolution

> **Document:** 06-database-evolution.md  
> **Series:** Cart Package Vision  
> **Focus:** Schema Analysis, Required Changes, Migration Strategy

---

## Table of Contents

1. [Current Schema Analysis](#1-current-schema-analysis)
2. [Schema Changes Required](#2-schema-changes-required)
3. [Change Impact Assessment](#3-change-impact-assessment)
4. [Migration Strategy](#4-migration-strategy)
5. [Event Store Schema (New Table)](#5-event-store-schema-new-table)

---

## 1. Current Schema Analysis

### Existing Tables

#### `carts` Table (Primary)

```php
// From: 2024_12_30_000000_create_carts_table.php
Schema::create('carts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('identifier')->index();           // User identifier (session/user_id)
    $table->string('instance')->default('default')->index();  // Cart instance name
    $table->json('items');                           // JSON array of cart items
    $table->json('conditions');                      // JSON array of conditions
    $table->json('metadata');                        // Arbitrary metadata
    $table->unsignedInteger('version')->default(1)->index();  // CAS version
    $table->timestamp('expires_at')->nullable()->index();     // Expiration
    $table->timestamps();
    
    $table->unique(['identifier', 'instance']);
    
    // PostgreSQL-specific GIN indexes for JSONB
    if (DB::getDriverName() === 'pgsql') {
        $table->index([DB::raw('items jsonb_path_ops')], 'carts_items_gin', 'gin');
        $table->index([DB::raw('conditions jsonb_path_ops')], 'carts_conditions_gin', 'gin');
        $table->index([DB::raw('metadata jsonb_path_ops')], 'carts_metadata_gin', 'gin');
    }
});
```

#### `carts` Table (Owner Columns Addition)

```php
// From: 2025_01_15_000000_add_owner_columns_to_carts_table.php
if (config('cart.owner.enabled', false)) {
    Schema::table('carts', function (Blueprint $table) {
        $table->string('owner_type')->nullable()->after('id');
        $table->uuid('owner_id')->nullable()->after('owner_type');
        $table->index(['owner_type', 'owner_id']);
    });
    
    // Drops and recreates unique constraint
    Schema::table('carts', function (Blueprint $table) {
        $table->dropUnique(['identifier', 'instance']);
        $table->unique(['owner_type', 'owner_id', 'identifier', 'instance']);
    });
}
```

#### `conditions` Table (Standalone)

```php
// From: 2025_09_29_184331_create_conditions_table.php
Schema::create('conditions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('type')->index();
    $table->string('value');
    
    // Boolean flags (6 columns)
    $table->boolean('is_charge')->default(false)->index();
    $table->boolean('is_discount')->default(false)->index();
    $table->boolean('is_percentage')->default(false)->index();
    $table->boolean('is_dynamic')->default(false)->index();
    $table->boolean('is_global')->default(false)->index();
    $table->boolean('is_active')->default(true)->index();
    
    $table->json('metadata')->nullable();
    $table->timestamps();
});
```

### Current Column Count

| Table | Columns | Indexes | JSON Columns |
|-------|---------|---------|--------------|
| carts | 10 (12 with owner) | 8 | 3 |
| conditions | 13 | 8 | 1 |

---

## 2. Schema Changes Required

### Priority Matrix

| Vision Feature | Table | Change Type | Complexity | Breaking? |
|----------------|-------|-------------|------------|-----------|
| **AI Intelligence** | carts | Add columns | Low | No |
| **Event Sourcing** | carts | Add columns | Low | No |
| **Collaborative Carts** | carts | Add columns | Medium | No |
| **CQRS** | N/A | Read replica | Config | No |
| **Performance** | carts | Add indexes | Low | No |

---

### 2.1 Changes for AI Intelligence

**Purpose:** Track abandonment signals, enable ML predictions

```php
// NEW COLUMNS for carts table
$table->timestamp('last_activity_at')->nullable()->index();     // Track user engagement
$table->timestamp('checkout_started_at')->nullable();           // Conversion funnel
$table->timestamp('checkout_abandoned_at')->nullable();         // Abandonment tracking
$table->unsignedTinyInteger('recovery_attempts')->default(0);   // Recovery email count
$table->timestamp('recovered_at')->nullable();                  // If cart was recovered
```

**Migration:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('cart.table_prefix') . 'carts', function (Blueprint $table) {
            // AI & Analytics columns
            $table->timestamp('last_activity_at')
                ->nullable()
                ->after('expires_at')
                ->index('idx_carts_last_activity');
            
            $table->timestamp('checkout_started_at')
                ->nullable()
                ->after('last_activity_at');
            
            $table->timestamp('checkout_abandoned_at')
                ->nullable()
                ->after('checkout_started_at');
            
            $table->unsignedTinyInteger('recovery_attempts')
                ->default(0)
                ->after('checkout_abandoned_at');
            
            $table->timestamp('recovered_at')
                ->nullable()
                ->after('recovery_attempts');
            
            // Composite index for abandoned cart queries
            $table->index(
                ['checkout_abandoned_at', 'recovery_attempts'],
                'idx_carts_abandonment_recovery'
            );
        });
    }
    
    public function down(): void
    {
        Schema::table(config('cart.table_prefix') . 'carts', function (Blueprint $table) {
            $table->dropIndex('idx_carts_last_activity');
            $table->dropIndex('idx_carts_abandonment_recovery');
            
            $table->dropColumn([
                'last_activity_at',
                'checkout_started_at',
                'checkout_abandoned_at',
                'recovery_attempts',
                'recovered_at',
            ]);
        });
    }
};
```

---

### 2.2 Changes for Event Sourcing

**Purpose:** Enable event replay, audit trail, time-travel debugging

```php
// NEW COLUMNS for carts table
$table->unsignedBigInteger('event_stream_position')->default(0);  // Last processed event
$table->string('aggregate_version')->default('1.0');               // Aggregate schema version
$table->timestamp('snapshot_at')->nullable();                      // Last snapshot time
```

**Migration:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('cart.table_prefix') . 'carts', function (Blueprint $table) {
            // Event Sourcing columns
            $table->unsignedBigInteger('event_stream_position')
                ->default(0)
                ->after('version')
                ->comment('Position in cart event stream for replay');
            
            $table->string('aggregate_version', 10)
                ->default('1.0')
                ->after('event_stream_position')
                ->comment('Schema version for aggregate migrations');
            
            $table->timestamp('snapshot_at')
                ->nullable()
                ->after('aggregate_version')
                ->comment('When last snapshot was taken');
            
            // Index for event replay queries
            $table->index(
                ['id', 'event_stream_position'],
                'idx_carts_event_stream'
            );
        });
    }
    
    public function down(): void
    {
        Schema::table(config('cart.table_prefix') . 'carts', function (Blueprint $table) {
            $table->dropIndex('idx_carts_event_stream');
            
            $table->dropColumn([
                'event_stream_position',
                'aggregate_version',
                'snapshot_at',
            ]);
        });
    }
};
```

---

### 2.3 Changes for Collaborative Carts

**Purpose:** Enable real-time shared carts with conflict resolution

```php
// NEW COLUMNS for carts table
$table->boolean('is_collaborative')->default(false)->index();  // Enable sharing
$table->uuid('created_by')->nullable();                        // Original creator
$table->json('collaborators')->nullable();                     // Array of {user_id, role, added_at}
$table->json('locks')->nullable();                             // Pessimistic locks on items
$table->timestamp('last_sync_at')->nullable();                 // CRDT sync timestamp
```

**Migration:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('cart.table_prefix') . 'carts', function (Blueprint $table) {
            // Collaborative cart columns
            $table->boolean('is_collaborative')
                ->default(false)
                ->after('metadata')
                ->index('idx_carts_collaborative');
            
            $table->uuid('created_by')
                ->nullable()
                ->after('is_collaborative');
            
            $table->json('collaborators')
                ->nullable()
                ->after('created_by')
                ->comment('JSON: [{user_id, role, permissions, added_at}]');
            
            $table->json('locks')
                ->nullable()
                ->after('collaborators')
                ->comment('JSON: {item_id: {locked_by, locked_at, expires_at}}');
            
            $table->timestamp('last_sync_at')
                ->nullable()
                ->after('locks')
                ->comment('Last CRDT synchronization timestamp');
        });
        
        // PostgreSQL GIN index for collaborators search
        if (config('database.default') === 'pgsql') {
            DB::statement('
                CREATE INDEX CONCURRENTLY idx_carts_collaborators_gin 
                ON ' . config('cart.table_prefix') . 'carts 
                USING GIN (collaborators jsonb_path_ops)
            ');
        }
    }
    
    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_carts_collaborators_gin');
        }
        
        Schema::table(config('cart.table_prefix') . 'carts', function (Blueprint $table) {
            $table->dropIndex('idx_carts_collaborative');
            
            $table->dropColumn([
                'is_collaborative',
                'created_by',
                'collaborators',
                'locks',
                'last_sync_at',
            ]);
        });
    }
};
```

---

### 2.4 Performance Indexes

**Purpose:** Optimize common query patterns

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('cart.table_prefix') . 'carts';
        $driver = config('database.default');
        
        // Covering index for primary lookup (avoids table access)
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_lookup_covering
            ON {$table} (identifier, instance)
            INCLUDE (id, version, updated_at, expires_at)
        ");
        
        // Partial index for active (non-expired) carts
        if ($driver === 'pgsql') {
            DB::statement("
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_active
                ON {$table} (identifier, instance)
                WHERE expires_at IS NULL OR expires_at > NOW()
            ");
        }
        
        // Index for cleanup job (expired carts)
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_expired
            ON {$table} (expires_at)
            WHERE expires_at IS NOT NULL
        ");
        
        // Composite index for abandoned cart analytics
        if ($driver === 'pgsql') {
            DB::statement("
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_analytics
                ON {$table} (updated_at, instance)
                WHERE items IS NOT NULL AND items != '[]'::jsonb
            ");
        }
    }
    
    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_carts_lookup_covering');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_carts_active');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_carts_expired');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_carts_analytics');
    }
};
```

---

## 3. Change Impact Assessment

### Summary of Changes to `carts` Table

| Change | Columns Added | Indexes Added | Data Migration | Downtime |
|--------|---------------|---------------|----------------|----------|
| AI Intelligence | 5 | 2 | None | Zero |
| Event Sourcing | 3 | 1 | None | Zero |
| Collaborative | 5 | 2 | None | Zero |
| Performance | 0 | 4 | None | Zero |
| **Total** | **13** | **9** | **None** | **Zero** |

### New Column Count (After All Changes)

| Current | AI | Events | Collab | Total |
|---------|-----|--------|--------|-------|
| 10 (12 with owner) | +5 | +3 | +5 | 23 (25 with owner) |

### Nullable Strategy

**All new columns are NULLABLE or have defaults:**

```
✅ last_activity_at         - NULL (populated on activity)
✅ checkout_started_at      - NULL (populated at checkout)
✅ checkout_abandoned_at    - NULL (populated on timeout)
✅ recovery_attempts        - DEFAULT 0
✅ recovered_at             - NULL
✅ event_stream_position    - DEFAULT 0
✅ aggregate_version        - DEFAULT '1.0'
✅ snapshot_at              - NULL
✅ is_collaborative         - DEFAULT false
✅ created_by               - NULL
✅ collaborators            - NULL
✅ locks                    - NULL
✅ last_sync_at             - NULL
```

### Backward Compatibility

| Aspect | Status | Reason |
|--------|--------|--------|
| Existing data | ✅ Safe | All columns nullable/defaulted |
| Existing code | ✅ Safe | New columns ignored if not used |
| Old migrations | ✅ Safe | New migrations are additive |
| Rollback | ✅ Safe | Down migrations drop columns |
| Feature flags | ✅ Safe | Features are opt-in via config |

---

## 4. Migration Strategy

### Recommended Approach: Incremental Feature-Based Migrations

```
migrations/
├── 2024_12_30_000000_create_carts_table.php           # Existing
├── 2025_01_15_000000_add_owner_columns_to_carts_table.php  # Existing
├── 2025_09_29_184331_create_conditions_table.php      # Existing
│
├── 2025_XX_01_000000_add_ai_columns_to_carts_table.php      # NEW: AI Intelligence
├── 2025_XX_02_000000_add_event_sourcing_columns_to_carts.php # NEW: Event Sourcing
├── 2025_XX_03_000000_add_collaborative_columns_to_carts.php  # NEW: Collaborative
├── 2025_XX_04_000000_add_performance_indexes_to_carts.php    # NEW: Performance
│
└── 2025_XX_05_000000_create_cart_events_table.php     # NEW: Event Store (new table)
```

### Migration Execution Plan

```bash
# Phase 1: AI Intelligence (No downtime, immediate value)
php artisan migrate --path=database/migrations/2025_XX_01_000000_add_ai_columns_to_carts_table.php

# Phase 2: Performance Indexes (Run during low-traffic)
php artisan migrate --path=database/migrations/2025_XX_04_000000_add_performance_indexes_to_carts.php

# Phase 3: Event Sourcing (When implementing audit trail)
php artisan migrate --path=database/migrations/2025_XX_02_000000_add_event_sourcing_columns_to_carts.php
php artisan migrate --path=database/migrations/2025_XX_05_000000_create_cart_events_table.php

# Phase 4: Collaborative (When implementing shared carts)
php artisan migrate --path=database/migrations/2025_XX_03_000000_add_collaborative_columns_to_carts.php
```

### PostgreSQL CONCURRENTLY Strategy

For production PostgreSQL databases, use `CONCURRENTLY` for index creation:

```php
// In migration
public function up(): void
{
    // Disable transaction for CONCURRENTLY
    DB::statement('SET statement_timeout = 0');
    
    // Create index without locking
    DB::statement('
        CREATE INDEX CONCURRENTLY idx_carts_new_column
        ON carts (new_column)
    ');
}

// In migration class
public bool $withinTransaction = false; // Required for CONCURRENTLY
```

---

## 5. Event Store Schema (New Table)

> This is the only **NEW TABLE** required for the vision features.

### Schema Design

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('cart.table_prefix') . 'cart_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cart_id')->index();           // Aggregate ID
            $table->unsignedBigInteger('position');      // Monotonic sequence
            $table->string('type', 100)->index();        // Event type (CartItemAdded, etc.)
            $table->string('aggregate_version', 10);     // Cart schema version
            $table->json('payload');                     // Event data
            $table->json('metadata')->nullable();        // Correlation ID, causation ID
            $table->uuid('actor_id')->nullable();        // Who performed action
            $table->string('actor_type')->nullable();    // user, system, guest
            $table->string('source')->default('api');    // api, web, mobile, import
            $table->timestamps();
            
            // Unique constraint ensures no duplicate events
            $table->unique(['cart_id', 'position'], 'uk_cart_events_stream');
            
            // Index for replay by type
            $table->index(['cart_id', 'type'], 'idx_cart_events_type');
            
            // Index for time-based queries
            $table->index(['created_at'], 'idx_cart_events_created');
            
            // Index for actor queries (audit trail)
            $table->index(['actor_id', 'actor_type'], 'idx_cart_events_actor');
        });
        
        // PostgreSQL: Partition by month for large event stores
        if (config('database.default') === 'pgsql' && config('cart.events.partitioning', false)) {
            DB::statement('
                ALTER TABLE ' . config('cart.table_prefix') . 'cart_events 
                SET (autovacuum_vacuum_scale_factor = 0.0, autovacuum_vacuum_threshold = 5000)
            ');
        }
    }
    
    public function down(): void
    {
        Schema::dropIfExists(config('cart.table_prefix') . 'cart_events');
    }
};
```

### Event Types

| Event Type | Payload Schema |
|------------|----------------|
| `CartCreated` | `{identifier, instance, owner_id?}` |
| `CartItemAdded` | `{item_id, name, price, quantity, attributes}` |
| `CartItemUpdated` | `{item_id, changes: {quantity?, price?, attributes?}}` |
| `CartItemRemoved` | `{item_id, reason?}` |
| `CartConditionAdded` | `{condition_name, type, value}` |
| `CartConditionRemoved` | `{condition_name}` |
| `CartCleared` | `{items_count, conditions_count}` |
| `CartCheckoutStarted` | `{checkout_id}` |
| `CartCheckoutCompleted` | `{order_id}` |
| `CartCheckoutAbandoned` | `{reason?}` |
| `CartRecovered` | `{recovery_method}` |
| `CartMerged` | `{source_cart_id, items_merged}` |
| `CartCollaboratorAdded` | `{user_id, role}` |
| `CartCollaboratorRemoved` | `{user_id}` |
| `CartSnapshotTaken` | `{snapshot_id}` |

---

## Summary: Database Evolution Plan

### Changes to Existing `carts` Table

| Feature | Type | Columns | Indexes | Breaking | Priority |
|---------|------|---------|---------|----------|----------|
| AI Intelligence | ALTER ADD | 5 | 2 | No | **P0** |
| Performance | ALTER ADD | 0 | 4 | No | **P0** |
| Event Sourcing | ALTER ADD | 3 | 1 | No | **P1** |
| Collaborative | ALTER ADD | 5 | 2 | No | **P2** |

### New Tables

| Table | Purpose | Priority |
|-------|---------|----------|
| `cart_events` | Event Store for audit/replay | **P1** |

### No Changes Required

| Existing Table | Reason |
|----------------|--------|
| `conditions` | Already supports all vision features |

---

**Next:** [07-security-framework.md](07-security-framework.md) - Zero-Trust Security, Fraud Detection


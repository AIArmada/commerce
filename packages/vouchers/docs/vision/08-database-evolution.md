# Database Evolution

> **Document:** 08-database-evolution.md  
> **Status:** Vision  
> **Priority:** Foundation

---

## Current Schema

### Existing Tables

| Table | Purpose | Status |
|-------|---------|--------|
| `vouchers` | Voucher definitions | ✅ Complete |
| `voucher_usage` | Usage tracking | ✅ Complete |
| `voucher_wallets` | User saved vouchers | ✅ Complete |
| `voucher_assignments` | Voucher-user assignments | ✅ Complete |
| `voucher_transactions` | Transaction log | ✅ Complete |

---

## Evolution Blueprint

### Phase 0: Current State (Baseline)

```sql
-- vouchers table (current)
CREATE TABLE vouchers (
    id UUID PRIMARY KEY,
    code VARCHAR(50) UNIQUE,
    name VARCHAR(255),
    description TEXT,
    type VARCHAR(50),              -- percentage, fixed, free_shipping
    value BIGINT,                  -- in cents or basis points
    currency VARCHAR(3),
    min_cart_value BIGINT,
    max_discount BIGINT,
    usage_limit INT,
    usage_limit_per_user INT,
    applied_count INT DEFAULT 0,
    redeemed_count INT DEFAULT 0,
    status VARCHAR(50),
    starts_at TIMESTAMP,
    expires_at TIMESTAMP,
    allows_manual_redemption BOOLEAN,
    target_definition JSONB,
    owner_type VARCHAR(255),
    owner_id UUID,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

### Phase 1: Stacking & Campaigns

**New Columns for `vouchers`:**

```php
Schema::table('vouchers', function (Blueprint $table) {
    // Stacking configuration
    $table->jsonb('stacking_rules')->nullable();
    $table->jsonb('exclusion_groups')->nullable();
    $table->integer('stacking_priority')->default(100);
    
    // Campaign linkage
    $table->foreignUuid('campaign_id')->nullable();
    $table->foreignUuid('campaign_variant_id')->nullable();
    
    // Enhanced targeting
    $table->jsonb('targeting')->nullable();
    
    // Indexes
    $table->index(['campaign_id']);
    $table->index([DB::raw("(stacking_rules->>'mode')")], 'idx_vouchers_stacking_mode');
});
```

**New Tables:**

```php
// Campaign management tables
Schema::create('voucher_campaigns', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->string('type')->default('promotional');
    $table->string('objective')->default('revenue_increase');
    $table->bigInteger('budget_cents')->nullable();
    $table->bigInteger('spent_cents')->default(0);
    $table->integer('max_redemptions')->nullable();
    $table->integer('current_redemptions')->default(0);
    $table->timestamp('starts_at');
    $table->timestamp('ends_at');
    $table->string('timezone')->default('UTC');
    $table->boolean('ab_testing_enabled')->default(false);
    $table->string('ab_winner_variant')->nullable();
    $table->timestamp('ab_winner_declared_at')->nullable();
    $table->string('status')->default('draft');
    $table->nullableUuidMorphs('owner');
    $table->jsonb('metrics')->nullable();
    $table->jsonb('automation_rules')->nullable();
    $table->timestamps();
    
    $table->index(['status', 'starts_at', 'ends_at']);
    $table->index(['owner_type', 'owner_id', 'status']);
});

Schema::create('voucher_campaign_variants', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('campaign_id');
    $table->string('name');
    $table->char('variant_code', 1);
    $table->decimal('traffic_percentage', 5, 2)->default(100);
    $table->foreignUuid('voucher_id')->nullable();
    $table->integer('impressions')->default(0);
    $table->integer('applications')->default(0);
    $table->integer('conversions')->default(0);
    $table->bigInteger('revenue_cents')->default(0);
    $table->boolean('is_control')->default(false);
    $table->timestamps();
    
    $table->unique(['campaign_id', 'variant_code']);
});

Schema::create('voucher_campaign_events', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('campaign_id');
    $table->foreignUuid('variant_id')->nullable();
    $table->string('event_type');
    $table->string('voucher_code')->nullable();
    $table->nullableUuidMorphs('user');
    $table->nullableUuidMorphs('cart');
    $table->nullableUuidMorphs('order');
    $table->string('channel')->nullable();
    $table->string('source')->nullable();
    $table->string('medium')->nullable();
    $table->bigInteger('value_cents')->nullable();
    $table->jsonb('metadata')->nullable();
    $table->timestamp('occurred_at');
    
    $table->index(['campaign_id', 'event_type', 'occurred_at']);
    $table->index(['variant_id', 'event_type']);
});
```

---

### Phase 2: Advanced Voucher Types

**New Columns for `vouchers`:**

```php
Schema::table('vouchers', function (Blueprint $table) {
    // Compound voucher configuration
    $table->jsonb('value_config')->nullable();
    
    // Cashback specific
    $table->string('credit_destination')->nullable();
    $table->integer('credit_delay_hours')->default(0);
});
```

**Migration for existing vouchers:**

```php
// Migrate simple value to value_config for consistency
DB::table('vouchers')
    ->whereNull('value_config')
    ->update([
        'value_config' => DB::raw("jsonb_build_object('simple', true, 'value', value)")
    ]);
```

---

### Phase 3: Gift Cards

**New Tables:**

```php
Schema::create('gift_cards', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('code', 32)->unique();
    $table->string('pin', 8)->nullable();
    $table->string('type')->default('standard');
    $table->string('currency', 3)->default('MYR');
    $table->bigInteger('initial_balance');
    $table->bigInteger('current_balance');
    $table->string('status')->default('inactive');
    $table->timestamp('activated_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('last_used_at')->nullable();
    $table->nullableUuidMorphs('purchaser');
    $table->nullableUuidMorphs('recipient');
    $table->nullableUuidMorphs('owner');
    $table->jsonb('metadata')->nullable();
    $table->timestamps();
    
    $table->index(['status', 'expires_at']);
    $table->index(['recipient_type', 'recipient_id']);
    $table->index(['code', 'status']);
});

Schema::create('gift_card_transactions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('gift_card_id');
    $table->string('type');
    $table->bigInteger('amount');
    $table->bigInteger('balance_before');
    $table->bigInteger('balance_after');
    $table->nullableUuidMorphs('reference');
    $table->string('description')->nullable();
    $table->nullableUuidMorphs('actor');
    $table->jsonb('metadata')->nullable();
    $table->timestamps();
    
    $table->index(['gift_card_id', 'created_at']);
    $table->index(['reference_type', 'reference_id']);
});
```

---

### Phase 4: AI & Analytics

**New Columns for `vouchers`:**

```php
Schema::table('vouchers', function (Blueprint $table) {
    // AI optimization data
    $table->jsonb('ai_metrics')->nullable();
    $table->float('predicted_conversion_rate')->nullable();
    $table->float('predicted_roi')->nullable();
    $table->timestamp('metrics_updated_at')->nullable();
});
```

**New Tables:**

```php
Schema::create('voucher_fraud_signals', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('voucher_id');
    $table->string('signal_type');
    $table->float('severity_score');
    $table->jsonb('details');
    $table->nullableUuidMorphs('user');
    $table->nullableUuidMorphs('cart');
    $table->string('ip_address')->nullable();
    $table->string('user_agent')->nullable();
    $table->boolean('was_blocked')->default(false);
    $table->timestamps();
    
    $table->index(['voucher_id', 'created_at']);
    $table->index(['signal_type', 'severity_score']);
});

Schema::create('voucher_ml_training_data', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('voucher_id')->nullable();
    $table->foreignUuid('cart_id');
    $table->foreignUuid('user_id')->nullable();
    
    // Features (denormalized for ML)
    $table->jsonb('cart_features');
    $table->jsonb('user_features');
    $table->jsonb('voucher_features');
    $table->jsonb('session_features');
    
    // Target variables
    $table->boolean('converted');
    $table->bigInteger('order_value_cents')->nullable();
    $table->integer('time_to_convert_minutes')->nullable();
    
    $table->timestamp('created_at');
    
    $table->index(['created_at']);
    $table->index(['converted']);
});
```

---

## Index Strategy

### Performance Indexes

```php
// Frequent query patterns
Schema::table('vouchers', function (Blueprint $table) {
    // Active voucher lookup
    $table->index(['status', 'starts_at', 'expires_at'], 'idx_vouchers_active');
    
    // Owner-scoped queries
    $table->index(['owner_type', 'owner_id', 'status'], 'idx_vouchers_owner_active');
    
    // Campaign analytics
    $table->index(['campaign_id', 'redeemed_count'], 'idx_vouchers_campaign_performance');
});

// PostgreSQL partial indexes
if (config('database.default') === 'pgsql') {
    DB::statement('
        CREATE INDEX CONCURRENTLY idx_vouchers_active_partial 
        ON vouchers (code, type, value) 
        WHERE status = \'active\' 
        AND (expires_at IS NULL OR expires_at > NOW())
    ');
}
```

### Analytics Indexes

```php
Schema::table('voucher_campaign_events', function (Blueprint $table) {
    // Time-series queries
    $table->index([
        DB::raw('DATE_TRUNC(\'day\', occurred_at)'),
        'campaign_id',
        'event_type'
    ], 'idx_campaign_events_timeseries');
    
    // Funnel analysis
    $table->index(['campaign_id', 'user_id', 'event_type', 'occurred_at'], 'idx_campaign_events_funnel');
});
```

---

## Migration Order

```
migrations/
├── 2025_12_03_000001_add_stacking_columns_to_vouchers.php
├── 2025_12_03_000002_add_campaign_columns_to_vouchers.php
├── 2025_12_03_000003_create_voucher_campaigns_table.php
├── 2025_12_03_000004_create_voucher_campaign_variants_table.php
├── 2025_12_03_000005_create_voucher_campaign_events_table.php
├── 2025_12_03_000006_add_value_config_to_vouchers.php
├── 2025_12_03_000007_create_gift_cards_table.php
├── 2025_12_03_000008_create_gift_card_transactions_table.php
├── 2025_12_03_000009_add_ai_columns_to_vouchers.php
├── 2025_12_03_000010_create_voucher_fraud_signals_table.php
├── 2025_12_03_000011_create_voucher_ml_training_data_table.php
└── 2025_12_03_000012_add_performance_indexes.php
```

---

## Breaking Changes

| Phase | Breaking Change | Migration Path |
|-------|-----------------|----------------|
| 1 | None | Additive columns only |
| 2 | None | `value_config` is nullable, `value` still works |
| 3 | None | Gift cards are new tables |
| 4 | None | AI columns are nullable |

**All migrations are non-breaking and additive.**

---

## Navigation

**Previous:** [07-ai-optimization.md](07-ai-optimization.md)  
**Next:** [09-filament-enhancements.md](09-filament-enhancements.md)

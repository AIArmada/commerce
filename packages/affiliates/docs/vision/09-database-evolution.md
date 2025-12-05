# Database Evolution Strategy

> **Document:** 09 of 11  
> **Package:** `aiarmada/affiliates`  
> **Status:** 🟢 65% Implemented  
> **Last Updated:** December 5, 2025

---

## Overview

Define the **database evolution strategy** for the affiliate packages, including schema enhancements, migration patterns, and data modeling for scale.

---

## Current State ✅

### Implemented Tables (Phase 1 Complete)

| Table | Status | Notes |
|-------|--------|-------|
| `affiliates` | ✅ Done | Core affiliate data, UUID PK |
| `affiliate_attributions` | ✅ Done | Attribution windows, touchpoint refs |
| `affiliate_conversions` | ✅ Done | Order-to-commission mapping |
| `affiliate_payouts` | ✅ Done | Payout records |
| `affiliate_payout_events` | ✅ Done | Payout status history |
| `affiliate_touchpoints` | ✅ Done | Click/view tracking |

### Implemented Schema Features

1. **UUID Primary Keys**
   - All tables use `uuid('id')->primary()`
   - Consistent with commerce package guidelines

2. **Foreign Keys (No DB Constraints)**
   - `foreignUuid()` without `->constrained()`
   - Cascade deletes in model `booted()` methods

3. **JSON Columns**
   - Proper JSON column support
   - Configurable via `json_column_type`

4. **Minor Unit Currency**
   - All monetary values in minor units (cents)
   - `bigInteger` for amount columns

5. **Timestamps**
   - All tables have `created_at`, `updated_at`
   - Key events use custom timestamp columns

### Pending Tables (Phases 2-8)

| Table | Phase | Status |
|-------|-------|--------|
| `affiliate_programs` | 2 | ⬜ Planned |
| `affiliate_program_memberships` | 2 | ⬜ Planned |
| `affiliate_tiers` | 3 | ⬜ Planned |
| `affiliate_balances` | 3 | ⬜ Planned |
| `affiliate_commission_rules` | 4 | ⬜ Planned |
| `affiliate_promotional_commissions` | 4 | ⬜ Planned |
| `affiliate_payout_methods` | 5 | ⬜ Planned |
| `affiliate_payout_batches` | 5 | ⬜ Planned |
| `affiliate_payout_holds` | 5 | ⬜ Planned |
| `affiliate_fraud_signals` | 6 | ⬜ Planned |
| `affiliate_links` | 7 | ⬜ Planned |
| `affiliate_assets` | 7 | ⬜ Planned |
| `affiliate_daily_stats` | 8 | ⬜ Planned |
| `affiliate_monthly_stats` | 8 | ⬜ Planned |

---

## Schema Evolution Phases

### Phase 1: Core Foundation (✅ Complete)

```php
// affiliates table - IMPLEMENTED
Schema::create('affiliates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->nullable();
    $table->foreignUuid('parent_id')->nullable();
    $table->string('code')->unique();
    $table->string('status');
    $table->string('name');
    $table->string('email');
    $table->string('commission_type')->nullable();
    $table->decimal('commission_rate', 8, 4)->nullable();
    $table->json('metadata')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

// affiliate_touchpoints table - IMPLEMENTED
Schema::create('affiliate_touchpoints', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->string('visitor_id');
    $table->string('type'); // click, view, impression
    $table->string('source')->nullable();
    $table->string('landing_url')->nullable();
    $table->string('referrer_url')->nullable();
    $table->string('ip_address')->nullable();
    $table->string('user_agent')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamp('occurred_at');
    $table->timestamps();
});

// affiliate_conversions table - IMPLEMENTED
Schema::create('affiliate_conversions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->foreignUuid('attribution_id')->nullable();
    $table->string('order_id');
    $table->bigInteger('order_amount_minor');
    $table->bigInteger('commission_amount_minor');
    $table->string('currency', 3)->default('USD');
    $table->string('status');
    $table->json('metadata')->nullable();
    $table->timestamp('occurred_at');
    $table->timestamps();
});

// affiliate_attributions table - IMPLEMENTED
Schema::create('affiliate_attributions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->foreignUuid('touchpoint_id')->nullable();
    $table->string('visitor_id');
    $table->string('model'); // first_click, last_click, linear
    $table->decimal('weight', 5, 4)->default(1.0);
    $table->timestamp('attributed_at');
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
});

// affiliate_payouts table - IMPLEMENTED
Schema::create('affiliate_payouts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->string('reference')->unique();
    $table->bigInteger('amount_minor');
    $table->string('currency', 3)->default('USD');
    $table->string('status');
    $table->string('method')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();
});

// affiliate_payout_events table - IMPLEMENTED
Schema::create('affiliate_payout_events', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('payout_id');
    $table->string('status');
    $table->text('notes')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
});
```

---

### Phase 2: Program Management (⬜ Planned)

```php
// affiliate_programs table
Schema::create('affiliate_programs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->string('commission_type');
    $table->decimal('commission_rate', 8, 4);
    $table->integer('cookie_duration_days')->default(30);
    $table->json('terms')->nullable();
    $table->json('settings')->nullable();
    $table->boolean('is_active')->default(true);
    $table->boolean('requires_approval')->default(true);
    $table->timestamps();
});

// affiliate_program_memberships table
Schema::create('affiliate_program_memberships', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->foreignUuid('program_id');
    $table->string('status');
    $table->decimal('custom_rate', 8, 4)->nullable();
    $table->timestamp('applied_at')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->timestamps();
    
    $table->unique(['affiliate_id', 'program_id']);
});
```

---

### Phase 3: Tiered Commissions (⬜ Planned)

```php
// affiliate_tiers table
Schema::create('affiliate_tiers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->bigInteger('threshold_minor');
    $table->decimal('commission_rate', 5, 2);
    $table->json('benefits')->nullable();
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});

// affiliate_balances table
Schema::create('affiliate_balances', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id')->unique();
    $table->string('currency', 3);
    $table->bigInteger('holding_minor')->default(0);
    $table->bigInteger('available_minor')->default(0);
    $table->bigInteger('lifetime_earnings_minor')->default(0);
    $table->bigInteger('minimum_payout_minor');
    $table->timestamps();
});
```

---

### Phase 4: Commission Rules (⬜ Planned)

```php
// affiliate_commission_rules table
Schema::create('affiliate_commission_rules', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('type');
    $table->integer('priority')->default(0);
    $table->json('conditions');
    $table->string('commission_type');
    $table->decimal('commission_value', 10, 4);
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->index(['type', 'is_active', 'priority']);
});

// affiliate_promotional_commissions table
Schema::create('affiliate_promotional_commissions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('bonus_type');
    $table->decimal('bonus_value', 10, 4);
    $table->json('conditions')->nullable();
    $table->timestamp('starts_at');
    $table->timestamp('ends_at');
    $table->integer('max_uses')->nullable();
    $table->integer('current_uses')->default(0);
    $table->json('affiliate_ids')->nullable();
    $table->timestamps();
});
```

---

### Phase 5: Payout Enhancement (⬜ Planned)

```php
// affiliate_payout_methods table
Schema::create('affiliate_payout_methods', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->string('type');
    $table->json('details');
    $table->boolean('is_verified')->default(false);
    $table->boolean('is_default')->default(false);
    $table->timestamp('verified_at')->nullable();
    $table->timestamps();
});

// affiliate_payout_batches table
Schema::create('affiliate_payout_batches', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('reference')->unique();
    $table->string('status');
    $table->integer('total_payouts')->default(0);
    $table->bigInteger('total_amount_minor')->default(0);
    $table->integer('successful_count')->default(0);
    $table->integer('failed_count')->default(0);
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->json('summary')->nullable();
    $table->timestamps();
});

// affiliate_payout_holds table
Schema::create('affiliate_payout_holds', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->string('reason');
    $table->text('notes')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->foreignUuid('placed_by')->nullable();
    $table->timestamp('released_at')->nullable();
    $table->timestamps();
});
```

---

### Phase 6: Fraud Detection (⬜ Planned)

```php
// affiliate_fraud_signals table
Schema::create('affiliate_fraud_signals', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->foreignUuid('touchpoint_id')->nullable();
    $table->foreignUuid('conversion_id')->nullable();
    $table->string('signal_type');
    $table->integer('severity'); // 1-10
    $table->string('status');
    $table->json('evidence');
    $table->text('notes')->nullable();
    $table->timestamp('detected_at');
    $table->timestamp('reviewed_at')->nullable();
    $table->foreignUuid('reviewed_by')->nullable();
    $table->timestamps();
    
    $table->index(['affiliate_id', 'status']);
    $table->index(['signal_type', 'detected_at']);
});
```

---

### Phase 7: Marketing Assets (⬜ Planned)

```php
// affiliate_links table
Schema::create('affiliate_links', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->string('name');
    $table->string('destination_url');
    $table->string('short_code')->unique();
    $table->json('utm_params')->nullable();
    $table->bigInteger('click_count')->default(0);
    $table->bigInteger('conversion_count')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// affiliate_assets table
Schema::create('affiliate_assets', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('type'); // banner, text, email, video
    $table->text('content');
    $table->json('dimensions')->nullable();
    $table->string('file_path')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

---

### Phase 8: Analytics Optimization (⬜ Planned)

```php
// affiliate_daily_stats table
Schema::create('affiliate_daily_stats', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->date('date');
    $table->integer('clicks')->default(0);
    $table->integer('impressions')->default(0);
    $table->integer('conversions')->default(0);
    $table->bigInteger('revenue_minor')->default(0);
    $table->bigInteger('commissions_minor')->default(0);
    $table->decimal('conversion_rate', 8, 4)->default(0);
    $table->timestamps();
    
    $table->unique(['affiliate_id', 'date']);
    $table->index(['date']);
});

// affiliate_monthly_stats table  
Schema::create('affiliate_monthly_stats', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->unsignedSmallInteger('year');
    $table->unsignedTinyInteger('month');
    $table->integer('clicks')->default(0);
    $table->integer('impressions')->default(0);
    $table->integer('conversions')->default(0);
    $table->bigInteger('revenue_minor')->default(0);
    $table->bigInteger('commissions_minor')->default(0);
    $table->decimal('conversion_rate', 8, 4)->default(0);
    $table->timestamps();
    
    $table->unique(['affiliate_id', 'year', 'month']);
});
```

---

## Indexing Strategy

### Current Indexes (Implemented)

```php
// affiliates
$table->unique('code');
$table->index('user_id');
$table->index('parent_id');
$table->index('status');

// affiliate_touchpoints
$table->index('affiliate_id');
$table->index('visitor_id');
$table->index('occurred_at');

// affiliate_conversions
$table->index('affiliate_id');
$table->index('order_id');
$table->index('status');

// affiliate_payouts
$table->unique('reference');
$table->index('affiliate_id');
$table->index('status');
```

### Recommended Additions

```php
// Composite indexes for common queries
$table->index(['affiliate_id', 'status', 'occurred_at']);
$table->index(['visitor_id', 'occurred_at']);
$table->index(['status', 'processed_at']);
```

---

## Migration Guidelines

### Naming Convention

```
YYYY_MM_DD_HHMMSS_create_affiliate_<table>_table.php
YYYY_MM_DD_HHMMSS_add_<column>_to_affiliate_<table>_table.php
```

### Best Practices

1. **No DB constraints** - Use `foreignUuid()` without `->constrained()`
2. **UUID primary keys** - Always `uuid('id')->primary()`
3. **Minor units** - Store currency as `bigInteger` cents
4. **JSON columns** - Use config for column type
5. **Soft deletes** - Add only where business requires
6. **Timestamps** - Always include `$table->timestamps()`

---

## Implementation Progress

| Phase | Tables | Status | Effort |
|-------|--------|--------|--------|
| 1 | Core (6 tables) | ✅ Done | 3 days |
| 2 | Programs (2 tables) | ⬜ Todo | 2 days |
| 3 | Tiers (2 tables) | ⬜ Todo | 1 day |
| 4 | Rules (2 tables) | ⬜ Todo | 1.5 days |
| 5 | Payouts (3 tables) | ⬜ Todo | 2 days |
| 6 | Fraud (1 table) | ⬜ Todo | 1 day |
| 7 | Assets (2 tables) | ⬜ Todo | 1 day |
| 8 | Analytics (2 tables) | ⬜ Todo | 1 day |

**Remaining Effort:** ~1.5 weeks

---

## Navigation

**Previous:** [08-dynamic-commissions.md](08-dynamic-commissions.md)  
**Next:** [10-filament-enhancements.md](10-filament-enhancements.md)

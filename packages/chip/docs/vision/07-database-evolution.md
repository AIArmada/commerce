# Database Evolution

> **Document:** 07 of 10  
> **Package:** `aiarmada/chip`  
> **Status:** Vision

---

## Overview

Evolve the Chip database schema to support **subscriptions, billing templates, disputes, analytics**, and **enhanced webhook processing** while maintaining backward compatibility.

---

## Current Schema Analysis

### Existing Tables

| Table | Purpose | Status |
|-------|---------|--------|
| `chip_purchases` | Payment transactions | ✅ Stable |
| `chip_refunds` | Refund records | ✅ Stable |
| `chip_recurring_tokens` | Saved payment methods | ✅ Stable |
| `chip_payouts` | Merchant payouts | ✅ Stable |
| `chip_payout_destinations` | Payout bank accounts | ✅ Stable |
| `chip_webhook_logs` | Webhook history | ⚠️ Enhance |

### Current chip_purchases Structure

```php
// Existing columns
$table->uuid('id')->primary();
$table->foreignUuid('recurring_token_id')->nullable();
$table->string('chip_id')->unique();
$table->string('status');
$table->string('payment_method')->nullable();
$table->string('customer_email');
$table->string('customer_name')->nullable();
$table->bigInteger('total_minor');
$table->string('currency', 3);
$table->text('description')->nullable();
$table->json('metadata')->nullable();
$table->timestamp('completed_at')->nullable();
$table->timestamps();
```

---

## Schema Evolution Plan

### Phase 1: Subscription Infrastructure

#### New Table: chip_plans

```php
Schema::create('chip_plans', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->bigInteger('price_minor');
    $table->string('currency', 3)->default('MYR');
    $table->string('interval'); // daily, weekly, monthly, yearly
    $table->unsignedInteger('interval_count')->default(1);
    $table->unsignedInteger('trial_days')->default(0);
    $table->json('features')->nullable();
    $table->json('metadata')->nullable();
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
    $table->softDeletes();
    
    $table->index('is_active');
    $table->index('slug');
});
```

#### New Table: chip_subscriptions

```php
Schema::create('chip_subscriptions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('plan_id');
    $table->foreignUuid('recurring_token_id')->nullable();
    $table->string('chip_subscription_id')->nullable()->unique();
    $table->string('subscriber_type');
    $table->uuid('subscriber_id');
    $table->string('status'); // active, paused, canceled, past_due, expired
    $table->unsignedInteger('quantity')->default(1);
    $table->bigInteger('unit_price_minor')->nullable(); // Override plan price
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('current_period_start')->nullable();
    $table->timestamp('current_period_end')->nullable();
    $table->timestamp('canceled_at')->nullable();
    $table->timestamp('ended_at')->nullable();
    $table->string('cancel_reason')->nullable();
    $table->unsignedInteger('payment_failures')->default(0);
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->index(['subscriber_type', 'subscriber_id']);
    $table->index('status');
    $table->index('current_period_end');
    $table->index('trial_ends_at');
});
```

#### New Table: chip_subscription_items

```php
Schema::create('chip_subscription_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('subscription_id');
    $table->foreignUuid('plan_id');
    $table->string('name');
    $table->unsignedInteger('quantity')->default(1);
    $table->bigInteger('unit_price_minor');
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->index('subscription_id');
});
```

---

### Phase 2: Billing Templates

#### New Table: chip_billing_templates

```php
Schema::create('chip_billing_templates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('code')->unique();
    $table->text('description')->nullable();
    $table->bigInteger('default_amount_minor')->nullable();
    $table->string('currency', 3)->default('MYR');
    $table->json('custom_fields')->nullable();
    $table->json('branding')->nullable();
    $table->string('redirect_url')->nullable();
    $table->string('webhook_url')->nullable();
    $table->string('success_message')->nullable();
    $table->boolean('is_active')->default(true);
    $table->unsignedBigInteger('usage_count')->default(0);
    $table->bigInteger('total_collected_minor')->default(0);
    $table->timestamps();
    $table->softDeletes();
    
    $table->index('code');
    $table->index('is_active');
});
```

#### New Table: chip_billing_instances

```php
Schema::create('chip_billing_instances', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('template_id');
    $table->foreignUuid('purchase_id')->nullable();
    $table->json('field_values')->nullable();
    $table->bigInteger('amount_minor');
    $table->string('currency', 3);
    $table->string('customer_email')->nullable();
    $table->string('customer_name')->nullable();
    $table->string('status'); // pending, completed, expired, canceled
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
    
    $table->index('template_id');
    $table->index('status');
});
```

---

### Phase 3: Dispute Management

#### New Table: chip_disputes

```php
Schema::create('chip_disputes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('purchase_id');
    $table->string('chip_dispute_id')->nullable()->unique();
    $table->string('reason'); // fraudulent, duplicate, product_not_received, etc.
    $table->string('status'); // open, under_review, won, lost, closed
    $table->bigInteger('amount_minor');
    $table->string('currency', 3);
    $table->text('customer_statement')->nullable();
    $table->text('merchant_response')->nullable();
    $table->timestamp('evidence_due_at')->nullable();
    $table->timestamp('resolved_at')->nullable();
    $table->string('resolution')->nullable(); // won, lost, accepted
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->index('purchase_id');
    $table->index('status');
    $table->index('evidence_due_at');
});
```

#### New Table: chip_dispute_evidence

```php
Schema::create('chip_dispute_evidence', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('dispute_id');
    $table->string('type'); // receipt, shipping_proof, communication, refund_policy, etc.
    $table->string('file_path')->nullable();
    $table->text('content')->nullable();
    $table->string('submitted_by')->nullable();
    $table->timestamp('submitted_at')->nullable();
    $table->timestamps();
    
    $table->index('dispute_id');
});
```

---

### Phase 4: Analytics & Metrics

#### New Table: chip_daily_metrics

```php
Schema::create('chip_daily_metrics', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->date('date');
    $table->string('payment_method')->nullable();
    $table->unsignedInteger('total_attempts')->default(0);
    $table->unsignedInteger('successful_count')->default(0);
    $table->unsignedInteger('failed_count')->default(0);
    $table->unsignedInteger('refunded_count')->default(0);
    $table->bigInteger('revenue_minor')->default(0);
    $table->bigInteger('refunds_minor')->default(0);
    $table->bigInteger('fees_minor')->default(0);
    $table->decimal('success_rate', 5, 2)->default(0);
    $table->decimal('avg_transaction_minor', 12, 2)->default(0);
    $table->decimal('avg_processing_seconds', 8, 2)->default(0);
    $table->json('failure_breakdown')->nullable();
    $table->timestamps();
    
    $table->unique(['date', 'payment_method']);
    $table->index('date');
});
```

#### New Table: chip_subscription_metrics

```php
Schema::create('chip_subscription_metrics', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->date('date');
    $table->foreignUuid('plan_id')->nullable();
    $table->unsignedInteger('active_count')->default(0);
    $table->unsignedInteger('new_count')->default(0);
    $table->unsignedInteger('churned_count')->default(0);
    $table->unsignedInteger('trial_count')->default(0);
    $table->bigInteger('mrr_minor')->default(0);
    $table->bigInteger('arr_minor')->default(0);
    $table->decimal('churn_rate', 5, 2)->default(0);
    $table->timestamps();
    
    $table->unique(['date', 'plan_id']);
    $table->index('date');
});
```

---

### Phase 5: Enhanced Purchases

#### Modify chip_purchases

```php
Schema::table('chip_purchases', function (Blueprint $table) {
    // Add subscription support
    $table->foreignUuid('subscription_id')->nullable()->after('recurring_token_id');
    $table->foreignUuid('billing_instance_id')->nullable()->after('subscription_id');
    
    // Add failure tracking
    $table->string('failure_reason')->nullable()->after('status');
    $table->unsignedInteger('retry_count')->default(0)->after('failure_reason');
    
    // Add processing metrics
    $table->decimal('processing_time_seconds', 8, 3)->nullable()->after('completed_at');
    
    // Add refund tracking
    $table->bigInteger('refund_amount_minor')->default(0)->after('total_minor');
    
    // Add indexes
    $table->index('subscription_id');
    $table->index('failure_reason');
    $table->index(['status', 'created_at']);
});
```

---

### Phase 6: Enhanced Webhook Logs

#### Modify chip_webhook_logs

```php
Schema::table('chip_webhook_logs', function (Blueprint $table) {
    // Add processing metadata
    $table->unsignedInteger('retry_count')->default(0)->after('status');
    $table->timestamp('processed_at')->nullable()->after('retry_count');
    $table->timestamp('last_retry_at')->nullable()->after('processed_at');
    $table->text('last_error')->nullable()->after('last_retry_at');
    $table->string('idempotency_key')->unique()->nullable()->after('last_error');
    $table->decimal('processing_time_ms', 10, 3)->nullable()->after('idempotency_key');
    $table->json('metadata')->nullable()->after('processing_time_ms');
    $table->string('ip_address')->nullable()->after('metadata');
    
    // Add indexes
    $table->index(['status', 'retry_count']);
    $table->index('processed_at');
});
```

---

## Indexing Strategy

### Performance Indexes

```php
// High-frequency query indexes
$table->index(['customer_email', 'status']); // Customer lookup
$table->index(['created_at', 'status']); // Date range queries
$table->index(['payment_method', 'status']); // Method analysis
$table->index(['subscription_id', 'status']); // Subscription payments

// Analytics indexes
$table->index('completed_at'); // Revenue queries
$table->index(['status', 'completed_at']); // Success rate

// Subscription indexes
$table->index(['current_period_end', 'status']); // Renewal queries
$table->index(['trial_ends_at', 'status']); // Trial management
```

### Composite Indexes

```php
// Subscription renewal check
$table->index(['status', 'current_period_end', 'payment_failures']);

// Failed payment retry
$table->index(['status', 'payment_failures', 'last_retry_at']);

// Dispute deadline
$table->index(['status', 'evidence_due_at']);
```

---

## Migration Strategy

### Rollout Order

```
Phase 1: Subscriptions (2 weeks)
├── chip_plans
├── chip_subscriptions
└── chip_subscription_items

Phase 2: Billing Templates (1 week)
├── chip_billing_templates
└── chip_billing_instances

Phase 3: Disputes (1 week)
├── chip_disputes
└── chip_dispute_evidence

Phase 4: Analytics (1 week)
├── chip_daily_metrics
└── chip_subscription_metrics

Phase 5: Schema Enhancements (1 week)
├── chip_purchases alterations
└── chip_webhook_logs alterations
```

### Zero-Downtime Migration

```php
// Add columns as nullable first
$table->foreignUuid('subscription_id')->nullable();

// Backfill data
ChipPurchase::query()
    ->whereNotNull('metadata->subscription_id')
    ->lazyById(1000)
    ->each(function ($purchase) {
        $purchase->update([
            'subscription_id' => $purchase->metadata['subscription_id'],
        ]);
    });

// Add constraints after backfill
Schema::table('chip_purchases', function (Blueprint $table) {
    $table->index('subscription_id');
});
```

---

## Data Integrity

### Application-Level Cascades

```php
// ChipSubscription model
protected static function booted(): void
{
    static::deleting(function (ChipSubscription $subscription): void {
        $subscription->items()->delete();
        $subscription->purchases()->update(['subscription_id' => null]);
    });
}

// ChipDispute model
protected static function booted(): void
{
    static::deleting(function (ChipDispute $dispute): void {
        $dispute->evidence()->delete();
    });
}

// ChipBillingTemplate model
protected static function booted(): void
{
    static::deleting(function (ChipBillingTemplate $template): void {
        $template->instances()->delete();
    });
}
```

---

## Schema Summary

### New Tables

| Table | Columns | Indexes | Purpose |
|-------|---------|---------|---------|
| `chip_plans` | 14 | 3 | Subscription plans |
| `chip_subscriptions` | 17 | 4 | Active subscriptions |
| `chip_subscription_items` | 8 | 1 | Multi-item subscriptions |
| `chip_billing_templates` | 15 | 3 | Payment templates |
| `chip_billing_instances` | 11 | 2 | Template usage |
| `chip_disputes` | 14 | 3 | Dispute tracking |
| `chip_dispute_evidence` | 8 | 1 | Evidence storage |
| `chip_daily_metrics` | 14 | 2 | Daily aggregates |
| `chip_subscription_metrics` | 11 | 2 | Subscription KPIs |

### Modified Tables

| Table | New Columns | New Indexes |
|-------|-------------|-------------|
| `chip_purchases` | 5 | 4 |
| `chip_webhook_logs` | 7 | 2 |

---

## Navigation

**Previous:** [06-enhanced-webhooks.md](06-enhanced-webhooks.md)  
**Next:** [08-filament-enhancements.md](08-filament-enhancements.md)

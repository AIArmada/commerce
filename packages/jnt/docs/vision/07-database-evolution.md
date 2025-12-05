# Database Evolution

> **Document:** 7 of 9  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision

---

## Overview

Evolve the JNT-specific database schema into a **multi-carrier shipping platform** with unified tables, rate management, carrier analytics, and returns support.

---

## Current Schema (JNT-Specific)

```
jnt_orders
jnt_order_items
jnt_order_parcels
jnt_tracking_events
jnt_webhook_logs
```

---

## Evolved Schema

```
┌─────────────────────────────────────────────────────────────────┐
│                     CORE SHIPMENT TABLES                         │
├─────────────────────────────────────────────────────────────────┤
│  shipments             - Main shipment records                   │
│  shipment_items        - Line items per shipment                │
│  shipment_packages     - Package dimensions                      │
│  tracking_events       - Unified tracking history               │
│  webhook_logs          - Multi-carrier webhook logs             │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     CARRIER MANAGEMENT                           │
├─────────────────────────────────────────────────────────────────┤
│  carriers              - Carrier registry                        │
│  carrier_credentials   - API credentials per tenant             │
│  carrier_rate_cards    - Rate tables                             │
│  carrier_service_types - Available services                      │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     RULES & AUTOMATION                           │
├─────────────────────────────────────────────────────────────────┤
│  shipping_rules        - Carrier selection rules                │
│  shipping_zones        - Zone definitions                        │
│  zone_postcodes        - Postcode mappings                       │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     RETURNS MANAGEMENT                           │
├─────────────────────────────────────────────────────────────────┤
│  return_requests       - RMA records                             │
│  return_request_items  - Items per return                        │
│  return_tracking       - Return shipment tracking               │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     ANALYTICS & METRICS                          │
├─────────────────────────────────────────────────────────────────┤
│  carrier_metrics       - Performance statistics                  │
│  transit_history       - Historical transit times               │
│  delivery_estimates    - Estimate accuracy tracking             │
└─────────────────────────────────────────────────────────────────┘
```

---

## New Tables

### 1. shipments

```php
Schema::create('shipments', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    // Carrier info
    $table->string('carrier_id');
    $table->string('service_type');
    $table->string('tracking_number')->nullable()->unique();
    $table->string('carrier_shipment_id')->nullable();
    
    // References
    $table->foreignUuid('order_id')->nullable();
    $table->string('reference')->nullable();
    
    // Owner (multi-tenant)
    $table->nullableUuidMorphs('owner');
    
    // Status
    $table->string('status')->default('pending');
    $table->string('tracking_status')->nullable();
    $table->timestamp('shipped_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    
    // Addresses (JSON)
    $table->json('sender_address');
    $table->json('recipient_address');
    
    // Package summary
    $table->integer('total_weight_grams');
    $table->integer('package_count')->default(1);
    
    // Financials
    $table->unsignedBigInteger('shipping_cost_minor')->nullable();
    $table->unsignedBigInteger('cod_amount_minor')->nullable();
    $table->unsignedBigInteger('insurance_value_minor')->nullable();
    $table->string('currency')->default('MYR');
    
    // Delivery
    $table->date('estimated_delivery_date')->nullable();
    $table->timestamp('original_estimated_at')->nullable();
    $table->string('signature_name')->nullable();
    $table->string('signature_image_url')->nullable();
    
    // Labels
    $table->string('label_url')->nullable();
    $table->string('label_format')->nullable();
    
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['carrier_id', 'status']);
    $table->index(['order_id']);
    $table->index(['tracking_status', 'carrier_id']);
    $table->index(['created_at']);
});
```

### 2. carriers

```php
Schema::create('carriers', function (Blueprint $table) {
    $table->string('id')->primary(); // e.g., 'jnt', 'poslaju'
    $table->string('name');
    $table->string('driver'); // Class identifier
    $table->boolean('is_active')->default(true);
    $table->integer('priority')->default(0);
    
    // Capabilities (cached)
    $table->json('capabilities');
    
    // Configuration
    $table->string('api_base_url')->nullable();
    $table->string('tracking_url_template')->nullable();
    $table->json('settings')->nullable();
    
    $table->timestamps();
});
```

### 3. carrier_credentials

```php
Schema::create('carrier_credentials', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('carrier_id');
    $table->nullableUuidMorphs('owner'); // Multi-tenant
    
    $table->boolean('is_sandbox')->default(false);
    $table->text('credentials'); // Encrypted JSON
    $table->boolean('is_verified')->default(false);
    $table->timestamp('verified_at')->nullable();
    
    $table->timestamps();

    $table->unique(['carrier_id', 'owner_type', 'owner_id', 'is_sandbox']);
});
```

### 4. carrier_rate_cards

```php
Schema::create('carrier_rate_cards', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('carrier_id');
    $table->nullableUuidMorphs('owner');
    
    $table->string('name');
    $table->date('effective_from');
    $table->date('effective_until')->nullable();
    $table->boolean('is_active')->default(true);
    
    $table->json('rates'); // Service → Zone → Weight → Price
    $table->json('surcharges'); // Fuel, remote, etc.
    
    $table->timestamps();

    $table->index(['carrier_id', 'is_active', 'effective_from']);
});
```

### 5. shipping_rules

```php
Schema::create('shipping_rules', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->nullableUuidMorphs('owner');
    
    $table->string('name');
    $table->string('type'); // force, restrict, prefer, avoid
    $table->integer('priority')->default(0);
    $table->string('carrier_id');
    
    $table->json('conditions'); // Array of conditions
    
    $table->boolean('is_active')->default(true);
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    
    $table->timestamps();

    $table->index(['is_active', 'priority']);
});
```

### 6. shipping_zones

```php
Schema::create('shipping_zones', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('carrier_id');
    
    $table->string('code'); // e.g., 'same_state', 'west_malaysia'
    $table->string('name');
    $table->string('origin_pattern'); // Regex or postcode list
    $table->string('destination_pattern');
    
    $table->integer('transit_days_min');
    $table->integer('transit_days_max');
    
    $table->boolean('is_remote')->default(false);
    $table->boolean('is_serviceable')->default(true);
    
    $table->timestamps();

    $table->unique(['carrier_id', 'code']);
});
```

### 7. return_requests

```php
Schema::create('return_requests', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('order_id');
    $table->foreignUuid('customer_id');
    
    $table->string('rma_number')->unique();
    $table->string('status');
    $table->string('reason');
    
    $table->text('customer_notes')->nullable();
    $table->text('internal_notes')->nullable();
    
    $table->string('resolution_type')->nullable();
    $table->unsignedBigInteger('refund_amount_minor')->nullable();
    
    $table->string('return_shipping_paid_by');
    $table->string('return_tracking_number')->nullable();
    $table->string('return_carrier_id')->nullable();
    
    $table->timestamp('received_at')->nullable();
    $table->timestamp('inspected_at')->nullable();
    $table->timestamp('resolved_at')->nullable();
    
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index(['order_id']);
    $table->index(['status']);
    $table->index(['rma_number']);
});
```

### 8. return_request_items

```php
Schema::create('return_request_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('return_request_id');
    $table->foreignUuid('order_item_id');
    
    $table->integer('quantity');
    $table->string('reason')->nullable();
    $table->string('condition_received')->nullable();
    $table->text('inspection_notes')->nullable();
    $table->string('resolution')->nullable();
    $table->unsignedBigInteger('refund_amount_minor')->nullable();
    
    $table->timestamps();

    $table->index(['return_request_id']);
});
```

### 9. carrier_metrics

```php
Schema::create('carrier_metrics', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('carrier_id');
    $table->string('zone_code')->nullable();
    $table->date('period_start');
    $table->date('period_end');
    
    // Volume
    $table->integer('total_shipments')->default(0);
    $table->integer('delivered_shipments')->default(0);
    $table->integer('failed_shipments')->default(0);
    
    // Performance
    $table->decimal('delivery_success_rate', 5, 4)->nullable();
    $table->decimal('on_time_rate', 5, 4)->nullable();
    $table->decimal('problem_rate', 5, 4)->nullable();
    
    // Transit times
    $table->decimal('avg_transit_days', 5, 2)->nullable();
    $table->integer('min_transit_days')->nullable();
    $table->integer('max_transit_days')->nullable();
    
    // Costs
    $table->unsignedBigInteger('total_shipping_cost_minor')->default(0);
    $table->unsignedBigInteger('avg_shipping_cost_minor')->nullable();
    
    $table->timestamps();

    $table->unique(['carrier_id', 'zone_code', 'period_start']);
});
```

### 10. tracking_events (Unified)

```php
Schema::create('tracking_events', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('shipment_id');
    $table->string('carrier_id');
    $table->string('tracking_number');
    
    // Normalized status
    $table->string('status');
    
    // Carrier-specific
    $table->string('carrier_status_code')->nullable();
    $table->string('carrier_status_description')->nullable();
    
    // Location
    $table->string('location_city')->nullable();
    $table->string('location_state')->nullable();
    $table->string('location_country')->nullable();
    $table->string('location_postcode')->nullable();
    $table->decimal('latitude', 10, 8)->nullable();
    $table->decimal('longitude', 11, 8)->nullable();
    
    // Timing
    $table->timestamp('occurred_at');
    $table->timestamp('estimated_delivery_at')->nullable();
    
    // Proof of delivery
    $table->string('signature_name')->nullable();
    $table->string('signature_image_url')->nullable();
    $table->json('photo_urls')->nullable();
    
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index(['shipment_id', 'occurred_at']);
    $table->index(['tracking_number']);
    $table->index(['status']);
});
```

---

## Migration Strategy

### Phase 1: Add New Tables
```
2024_01_01_create_carriers_table.php
2024_01_02_create_carrier_credentials_table.php
2024_01_03_create_carrier_rate_cards_table.php
2024_01_04_create_shipping_rules_table.php
2024_01_05_create_shipping_zones_table.php
2024_01_06_create_shipments_table.php
2024_01_07_create_tracking_events_table.php
2024_01_08_create_carrier_metrics_table.php
```

### Phase 2: Add Return Tables
```
2024_02_01_create_return_requests_table.php
2024_02_02_create_return_request_items_table.php
```

### Phase 3: Data Migration
```
2024_03_01_migrate_jnt_orders_to_shipments.php
2024_03_02_migrate_jnt_tracking_events.php
2024_03_03_seed_jnt_carrier_data.php
```

### Phase 4: Cleanup (Optional)
```
2024_04_01_drop_jnt_tables.php
```

---

## Indexing Strategy

| Table | Index | Purpose |
|-------|-------|---------|
| shipments | carrier_id, status | Filter by carrier/status |
| shipments | tracking_number | Lookup by tracking |
| shipments | order_id | Order linkage |
| tracking_events | shipment_id, occurred_at | Event timeline |
| carrier_metrics | carrier_id, zone_code, period_start | Analytics queries |
| return_requests | order_id | Return lookup |
| shipping_rules | is_active, priority | Rule evaluation |

---

## Navigation

**Previous:** [06-tracking-notifications.md](06-tracking-notifications.md)  
**Next:** [08-filament-enhancements.md](08-filament-enhancements.md)

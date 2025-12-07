# Shipping Vision: Database Schema

> **Document:** 09 of 11  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision Document  
> **Last Updated:** December 7, 2025

---

## Overview

Complete database schema design for the shipping package, including all models, relationships, and migration strategy.

---

## Entity Relationship Diagram

```
┌─────────────────┐       ┌──────────────────┐
│    Shipment     │──────<│  ShipmentItem    │
├─────────────────┤       ├──────────────────┤
│ id              │       │ id               │
│ reference       │       │ shipment_id (FK) │
│ carrier_code    │       │ sku, name        │
│ tracking_number │       │ quantity, weight │
│ status          │       └──────────────────┘
│ origin_address  │
│ dest_address    │       ┌──────────────────┐
│ shipped_at      │──────<│  ShipmentEvent   │
│ delivered_at    │       ├──────────────────┤
└─────────────────┘       │ id               │
        │                 │ shipment_id (FK) │
        │                 │ normalized_status│
        │                 │ description      │
        ▼                 │ occurred_at      │
┌─────────────────┐       └──────────────────┘
│  ShipmentLabel  │
├─────────────────┤       ┌──────────────────┐
│ id              │       │  ShippingZone    │
│ shipment_id(FK) │       ├──────────────────┤
│ format, url     │       │ id               │
└─────────────────┘       │ name, code       │
                          │ type, countries  │
┌─────────────────┐       │ postcode_ranges  │
│ ReturnAuth      │       └────────┬─────────┘
├─────────────────┤                │
│ id              │       ┌────────▼─────────┐
│ rma_number      │       │  ShippingRate    │
│ order_id        │       ├──────────────────┤
│ status, reason  │       │ id               │
│ approved_at     │       │ zone_id (FK)     │
└─────────────────┘       │ carrier_code     │
                          │ calculation_type │
                          │ base_rate        │
                          └──────────────────┘
```

---

## Migration: shipments

```php
Schema::create('shipments', function (Blueprint $table) {
    $table->id();
    $table->ulid('ulid')->unique();
    
    // Owner (multi-tenant)
    $table->morphs('owner');
    
    // Reference & Carrier
    $table->string('reference')->index();
    $table->string('carrier_code', 50)->index();
    $table->string('service_code', 50)->nullable();
    $table->string('tracking_number')->nullable()->index();
    $table->string('carrier_reference')->nullable();
    
    // Status
    $table->string('status', 50)->default('draft')->index();
    
    // Addresses (JSON)
    $table->json('origin_address');
    $table->json('destination_address');
    
    // Package Info
    $table->unsignedInteger('package_count')->default(1);
    $table->unsignedInteger('total_weight')->default(0); // grams
    $table->unsignedInteger('declared_value')->default(0); // cents
    $table->string('currency', 3)->default('MYR');
    
    // Costs
    $table->unsignedInteger('shipping_cost')->default(0);
    $table->unsignedInteger('insurance_cost')->default(0);
    
    // Labels
    $table->string('label_url')->nullable();
    $table->string('label_format', 10)->nullable();
    
    // Timestamps
    $table->timestamp('shipped_at')->nullable();
    $table->timestamp('estimated_delivery_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamp('last_tracking_sync')->nullable();
    
    // Metadata
    $table->json('metadata')->nullable();
    
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['carrier_code', 'status']);
});
```

---

## Migration: shipment_items

```php
Schema::create('shipment_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
    
    // Polymorphic link to original item
    $table->nullableMorphs('shippable_item');
    
    $table->string('sku')->nullable();
    $table->string('name');
    $table->unsignedInteger('quantity')->default(1);
    $table->unsignedInteger('weight')->default(0); // grams
    $table->unsignedInteger('declared_value')->default(0);
    
    $table->json('metadata')->nullable();
    $table->timestamps();
});
```

---

## Migration: shipment_events

```php
Schema::create('shipment_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
    
    $table->string('carrier_event_code', 50)->nullable();
    $table->string('normalized_status', 50)->index();
    $table->text('description')->nullable();
    
    // Location
    $table->string('location')->nullable();
    $table->string('city')->nullable();
    $table->string('state')->nullable();
    $table->string('country', 3)->nullable();
    $table->string('postal_code', 20)->nullable();
    
    $table->timestamp('occurred_at')->index();
    $table->json('raw_data')->nullable();
    
    $table->timestamps();
    
    $table->unique(['shipment_id', 'carrier_event_code', 'occurred_at'], 'unique_event');
});
```

---

## Migration: shipping_zones

```php
Schema::create('shipping_zones', function (Blueprint $table) {
    $table->id();
    $table->morphs('owner');
    
    $table->string('name');
    $table->string('code', 50)->unique();
    $table->string('type', 20); // country, state, postcode, radius
    
    $table->json('countries')->nullable();
    $table->json('states')->nullable();
    $table->json('postcode_ranges')->nullable();
    $table->point('center_coords')->nullable();
    $table->unsignedInteger('radius_km')->nullable();
    
    $table->unsignedInteger('priority')->default(0);
    $table->boolean('is_default')->default(false);
    $table->boolean('active')->default(true);
    
    $table->timestamps();
});
```

---

## Migration: shipping_rates

```php
Schema::create('shipping_rates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('zone_id')->constrained('shipping_zones')->cascadeOnDelete();
    
    $table->string('carrier_code', 50)->nullable();
    $table->string('method_code', 50);
    $table->string('name');
    
    $table->string('calculation_type', 20); // flat, per_kg, per_item
    $table->unsignedInteger('base_rate')->default(0);
    $table->unsignedInteger('per_unit_rate')->default(0);
    $table->unsignedInteger('min_charge')->nullable();
    $table->unsignedInteger('max_charge')->nullable();
    $table->unsignedInteger('free_shipping_threshold')->nullable();
    
    $table->unsignedTinyInteger('estimated_days_min')->nullable();
    $table->unsignedTinyInteger('estimated_days_max')->nullable();
    
    $table->json('conditions')->nullable();
    $table->boolean('active')->default(true);
    
    $table->timestamps();
    
    $table->index(['zone_id', 'carrier_code', 'active']);
});
```

---

## Migration: return_authorizations

```php
Schema::create('return_authorizations', function (Blueprint $table) {
    $table->id();
    $table->morphs('owner');
    
    $table->string('rma_number')->unique();
    $table->foreignId('original_shipment_id')
        ->nullable()
        ->constrained('shipments');
    
    $table->string('order_reference')->nullable();
    $table->foreignId('customer_id')->nullable();
    
    $table->string('status', 50)->default('pending');
    $table->string('type', 50); // refund, exchange, store_credit
    $table->string('reason', 100);
    $table->text('reason_details')->nullable();
    
    $table->foreignId('approved_by')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

---

## Indexes Strategy

```php
// Composite indexes for common queries
$table->index(['owner_id', 'owner_type', 'status'], 'shipments_owner_status');
$table->index(['carrier_code', 'status', 'created_at'], 'shipments_carrier_status');
$table->index(['tracking_number', 'carrier_code'], 'shipments_tracking');
```

---

## Navigation

**Previous:** [08-cart-integration.md](08-cart-integration.md)  
**Next:** [10-filament-enhancements.md](10-filament-enhancements.md)

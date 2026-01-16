---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 11+
- `aiarmada/commerce-support` package

## Install via Composer

```bash
composer require aiarmada/pricing
```

The package auto-registers its service provider via Laravel's package discovery.

## Publish Configuration

```bash
php artisan vendor:publish --tag=pricing-config
```

This publishes the configuration file to `config/pricing.php`.

## Run Migrations

```bash
php artisan migrate
```

This creates the following tables:
- `price_lists` - Stores price list definitions
- `prices` - Stores individual prices
- `price_tiers` - Stores tiered pricing rules

## Publish Settings Migrations (Optional)

If you want to manage pricing settings via Spatie Laravel Settings:

```bash
php artisan vendor:publish --tag=pricing-settings
```

Then run the settings migration:

```bash
php artisan migrate
```

## Database Tables

### price_lists

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `owner_type` | string (nullable) | Owner model type for multitenancy |
| `owner_id` | uuid (nullable) | Owner model ID |
| `name` | string | Price list name |
| `slug` | string | Unique slug identifier |
| `description` | text (nullable) | Optional description |
| `currency` | string(3) | ISO 4217 currency code |
| `priority` | integer | Higher value = higher priority |
| `is_default` | boolean | Default price list flag |
| `is_active` | boolean | Active status |
| `customer_id` | uuid (nullable) | Specific customer assignment |
| `segment_id` | uuid (nullable) | Customer segment assignment |
| `starts_at` | timestamp (nullable) | Activation start date |
| `ends_at` | timestamp (nullable) | Activation end date |

### prices

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `owner_type` | string (nullable) | Owner model type |
| `owner_id` | uuid (nullable) | Owner model ID |
| `price_list_id` | uuid | Foreign key to price_lists |
| `priceable_type` | string | Polymorphic type (Product, Variant) |
| `priceable_id` | uuid | Polymorphic ID |
| `amount` | bigint | Price in minor units (cents) |
| `compare_amount` | bigint (nullable) | Original/compare-at price |
| `currency` | string(3) | Currency code |
| `min_quantity` | integer | Minimum quantity for this price |
| `starts_at` | timestamp (nullable) | Price start date |
| `ends_at` | timestamp (nullable) | Price end date |

### price_tiers

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `owner_type` | string (nullable) | Owner model type |
| `owner_id` | uuid (nullable) | Owner model ID |
| `price_list_id` | uuid (nullable) | Optional price list assignment |
| `tierable_type` | string | Polymorphic type |
| `tierable_id` | uuid | Polymorphic ID |
| `min_quantity` | integer | Tier minimum quantity |
| `max_quantity` | integer (nullable) | Tier maximum quantity (null = unlimited) |
| `amount` | bigint | Price for this tier |
| `discount_type` | string (nullable) | 'percentage' or 'fixed' |
| `discount_value` | bigint (nullable) | Discount value |
| `currency` | string(3) | Currency code |

# Package Configuration Guide

## Table Name Configuration

The package uses `stock_transactions` as the default table name. You can customize this in the configuration file.

### Publishing Configuration

```bash
php artisan vendor:publish --tag=stock-config
```

This creates `config/stock.php` in your application.

### Customizing Table Name

If you want to use a different table name (e.g., to match an existing table):

```php
// config/stock.php

return [
    // Use existing table name
    'table_name' => env('STOCK_TABLE_NAME', 'inventory_transactions'),
    
    // Or keep default
    'table_name' => env('STOCK_TABLE_NAME', 'stock_transactions'),
];
```

Then set in your `.env`:

```env
STOCK_TABLE_NAME=inventory_transactions
```

### Using Existing Table Structure

If you want to use the package with your existing `stock_transactions` table, you need to ensure the columns match:

**Required columns:**
- `id` (UUID, primary key)
- `stockable_type` (string) - for polymorphic relationship
- `stockable_id` (UUID) - for polymorphic relationship  
- `user_id` (UUID, nullable)
- `quantity` (integer)
- `type` (enum: 'in', 'out')
- `reason` (string, nullable)
- `note` (text, nullable)
- `transaction_date` (timestamp)
- `created_at` (timestamp)
- `updated_at` (timestamp)

**Your existing table has:**
- `product_id` (UUID) - instead of polymorphic columns
- `order_item_id` (UUID, nullable) - additional column

### Option 1: Create New Table (Recommended)

Keep your existing table and create a new one for the package:

```bash
php artisan migrate
```

This creates `stock_transactions` table with polymorphic columns.

**Benefits:**
- No risk to existing data
- Can migrate gradually
- Clean separation

### Option 2: Modify Existing Table

Add polymorphic columns to your existing table:

```php
// In a new migration
Schema::table('stock_transactions', function (Blueprint $table) {
    $table->string('stockable_type')->nullable()->after('id');
    $table->uuid('stockable_id')->nullable()->after('stockable_type');
    
    $table->index(['stockable_type', 'stockable_id']);
});

// Populate new columns from existing data
DB::statement("
    UPDATE stock_transactions 
    SET stockable_type = 'App\\\\Models\\\\Product',
        stockable_id = product_id
    WHERE product_id IS NOT NULL
");
```

Then configure the package:

```php
// config/stock.php
return [
    'table_name' => 'stock_transactions', // Use existing table
];
```

## Low Stock Threshold

Configure the default threshold for low stock alerts:

```php
// config/stock.php
return [
    'low_stock_threshold' => env('STOCK_LOW_THRESHOLD', 10),
];
```

Then in `.env`:

```env
STOCK_LOW_THRESHOLD=20
```

**Usage:**

```php
// Uses config threshold (10 or 20 from env)
$isLow = $product->isLowStock();

// Override with custom threshold
$isLow = $product->isLowStock(50);
```

## Transaction Reasons

Customize available transaction reasons:

```php
// config/stock.php
return [
    'transaction_reasons' => [
        'restock' => 'Restock',
        'sale' => 'Sale',
        'return' => 'Return',
        'adjustment' => 'Adjustment',
        'damaged' => 'Damaged',
        'initial' => 'Initial Stock',
        // Add custom reasons
        'transfer' => 'Transfer',
        'promotion' => 'Promotional Gift',
    ],
];
```

**Usage:**

```php
$product->addStock(100, 'transfer', 'Transfer from warehouse B');
$product->removeStock(5, 'promotion', 'Free gift with purchase');
```

## Transaction Types

The package defines two types:

```php
// config/stock.php
return [
    'transaction_types' => [
        'in' => 'Stock In',
        'out' => 'Stock Out',
    ],
];
```

These are fixed and shouldn't be changed, but the labels can be customized for display purposes.

## Soft Deletes

Enable soft deletes for stock transactions:

```php
// config/stock.php
return [
    'use_soft_deletes' => env('STOCK_USE_SOFT_DELETES', false),
];
```

**Note:** Currently not implemented in the package. This is a placeholder for future enhancement.

## Environment Variables Reference

All available environment variables:

```env
# Table name
STOCK_TABLE_NAME=stock_transactions

# Low stock threshold
STOCK_LOW_THRESHOLD=10

# Soft deletes (future use)
STOCK_USE_SOFT_DELETES=false
```

## Complete Configuration Example

```php
<?php

// config/stock.php

return [
    'table_name' => env('STOCK_TABLE_NAME', 'stock_transactions'),
    
    'low_stock_threshold' => env('STOCK_LOW_THRESHOLD', 10),
    
    'transaction_types' => [
        'in' => 'Stock In',
        'out' => 'Stock Out',
    ],
    
    'transaction_reasons' => [
        'restock' => 'Restock from Supplier',
        'sale' => 'Customer Sale',
        'return' => 'Customer Return',
        'adjustment' => 'Stock Adjustment',
        'damaged' => 'Damaged/Lost',
        'initial' => 'Initial Stock Count',
        'transfer' => 'Warehouse Transfer',
        'sample' => 'Sample/Demo',
        'promotion' => 'Promotional Item',
    ],
    
    'use_soft_deletes' => env('STOCK_USE_SOFT_DELETES', false),
];
```

## Publishing Migrations

If you want to customize the migration:

```bash
php artisan vendor:publish --tag=stock-migrations
```

This copies the migration to your `database/migrations` folder where you can modify it before running.

## Publishing Everything

Publish all package files at once:

```bash
php artisan vendor:publish --provider="AIArmada\Stock\StockServiceProvider"
```

This publishes:
- Configuration file
- Migrations

## Cache Clearing

After changing configuration, clear the cache:

```bash
php artisan config:clear
```

## Multi-Tenancy Considerations

If using multi-tenancy, you might want different table names per tenant:

```php
// In your tenant configuration
config(['stock.table_name' => 'tenant_' . $tenantId . '_stock_transactions']);
```

Or use a global table with tenant_id column (requires custom migration).

## Summary

The package is highly configurable:
- ✅ Custom table names
- ✅ Configurable thresholds
- ✅ Custom transaction reasons
- ✅ Environment variable support
- ✅ Easy publishing of files
- ✅ Compatible with existing tables (with modifications)

Start with defaults and customize as needed! 🎉

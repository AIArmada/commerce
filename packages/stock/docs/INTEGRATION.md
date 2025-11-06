# Integration Example: Adding Stock Management to Product Model

This document shows how to integrate the stock management package with your existing Product model.

## Current Product Model

Your current `app/Models/Product.php` already uses UUIDs:

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

final class Product extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia;
    
    // ... existing code
}
```

## Adding Stock Management

Simply add the `HasStock` trait:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use AIArmada\Stock\Traits\HasStock;  // Add this import

final class Product extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia;
    use HasStock;  // Add this trait

    // ... rest of your existing code remains unchanged
}
```

## That's It! 🎉

Your Product model now has full stock management capabilities:

```php
$product = Product::find($id);

// Add stock
$product->addStock(100, 'restock', 'Supplier delivery');

// Remove stock (for sales)
$product->removeStock(1, 'sale', 'Customer order #123');

// Check current stock
$currentStock = $product->getCurrentStock(); // Returns integer

// Check if has enough stock
if ($product->hasStock(5)) {
    // Can fulfill order
}

// Check if stock is low
if ($product->isLowStock()) {
    // Alert admin to restock
}

// Get stock transaction history
$history = $product->getStockHistory(100); // Last 100 transactions
```

## Migrating from Existing StockService

If you have existing stock management code in `app/Services/StockService.php`, you have two options:

### Option 1: Keep Both (Recommended)

Keep your existing `StockService` for app-specific logic and use the package for the core functionality:

```php
// In your app/Services/StockService.php
use AIArmada\Stock\Facades\Stock;

class StockService
{
    // Your existing methods can use the package internally
    public function recordSale(Product $product, OrderItem $orderItem, ?string $note = null)
    {
        return Stock::removeStock(
            $product,
            $orderItem->quantity,
            'sale',
            $note ?? "Sale from Order #{$orderItem->order_id}"
        );
    }
}
```

### Option 2: Replace Gradually

Gradually replace your existing methods to use the trait:

```php
// Old way
$stockService->addStock($product, 100, 'restock');

// New way (using trait)
$product->addStock(100, 'restock');

// Or (using facade)
Stock::addStock($product, 100, 'restock');
```

## Database Migration

The package migration will create a new `stock_transactions` table that's separate from your existing structure:

**Existing table**: `stock_transactions` (with `product_id`, `order_item_id`)
**New table**: `stock_transactions` (with `stockable_type`, `stockable_id`)

The new table uses polymorphic relationships, so it can work with Products, Variants, or any other model.

### Migration Strategy

1. **Run the package migration**:
   ```bash
   php artisan migrate
   ```

2. **Optionally migrate existing data**:
   Create a migration to copy data from old to new format:
   
   ```php
   use App\Models\StockTransaction as OldStockTransaction;
   use AIArmada\Stock\Models\StockTransaction as NewStockTransaction;
   
   OldStockTransaction::chunk(100, function ($transactions) {
       foreach ($transactions as $old) {
           NewStockTransaction::create([
               'stockable_type' => Product::class,
               'stockable_id' => $old->product_id,
               'user_id' => $old->user_id,
               'quantity' => $old->quantity,
               'type' => $old->type,
               'reason' => $old->reason,
               'note' => $old->note,
               'transaction_date' => $old->transaction_date,
           ]);
       }
   });
   ```

3. **Update your application code gradually**

4. **Once tested, optionally remove the old implementation**

## Comparison with Existing Implementation

### Existing Code
```php
// In app/Models/StockTransaction.php
public function product(): BelongsTo
{
    return $this->belongsTo(Product::class);
}
```

### Package Code
```php
// In package - polymorphic relationship
public function stockable(): MorphTo
{
    return $this->morphTo();
}
```

**Advantage**: The package's polymorphic approach means you can add stock to ANY model, not just Products:

```php
// Works with Products
$product->addStock(100);

// Also works with ProductVariants
$variant->addStock(50);

// Or any other model that uses HasStock trait
$kit->addStock(25);
```

## Configuration

Configure stock management in `config/stock.php`:

```php
return [
    'table_name' => 'stock_transactions',
    'low_stock_threshold' => 10,
    // ... more options
];
```

## Benefits of Using the Package

1. **Polymorphic**: Works with any model, not just Products
2. **Tested**: 16 comprehensive tests included
3. **Type-safe**: PHPStan Level 6 compliant
4. **Reusable**: Use across multiple projects
5. **Maintained**: Separate package means easier updates
6. **Configurable**: Easy to customize via config file

## Example: Integration with Existing StockManagementCommand

Update your `app/Console/Commands/StockManagementCommand.php`:

```php
// Old way
use App\Services\StockService;
$stockService->addStock($product, 100, 'restock', 'Initial stock');

// New way
$product->addStock(100, 'restock', 'Initial stock');

// Or keep using the service pattern
use AIArmada\Stock\Facades\Stock;
Stock::addStock($product, 100, 'restock', 'Initial stock');
```

The command will work the same way, but now it uses the package implementation!

## Summary

1. Add `use HasStock;` trait to your Product model
2. Run migrations: `php artisan migrate`
3. Start using stock methods on products: `$product->addStock(100)`
4. Optionally migrate existing data
5. Enjoy clean, tested, type-safe stock management! 🎉

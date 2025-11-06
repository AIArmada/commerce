# Advanced Usage Examples

This guide shows advanced use cases and patterns for the stock management package.

## Multiple Models with Stock

The package supports polymorphic relationships, so any model can have stock:

```php
// Product model
class Product extends Model
{
    use HasUuids, HasStock;
}

// Product Variant model
class ProductVariant extends Model
{
    use HasUuids, HasStock;
}

// Equipment/Asset model
class Equipment extends Model
{
    use HasUuids, HasStock;
}
```

Now you can manage stock for all of them:

```php
$product->addStock(100);
$variant->addStock(50);
$equipment->addStock(5);
```

## Scoped Queries

Get all transactions for a specific stockable:

```php
use AIArmada\Stock\Models\StockTransaction;

// All transactions for a product
$transactions = StockTransaction::query()
    ->where('stockable_type', Product::class)
    ->where('stockable_id', $product->id)
    ->get();

// Only inbound transactions
$inbound = StockTransaction::query()
    ->where('stockable_type', Product::class)
    ->where('stockable_id', $product->id)
    ->where('type', 'in')
    ->get();

// Only sales
$sales = StockTransaction::query()
    ->where('stockable_type', Product::class)
    ->where('stockable_id', $product->id)
    ->where('reason', 'sale')
    ->get();
```

## Eager Loading

Optimize queries by eager loading relationships:

```php
// Load products with their stock transactions
$products = Product::with('stockTransactions')->get();

foreach ($products as $product) {
    $currentStock = $product->getCurrentStock(); // No extra query
}

// Load with user who made the transaction
$products = Product::with('stockTransactions.user')->get();

foreach ($products as $product) {
    foreach ($product->stockTransactions as $transaction) {
        echo $transaction->user->name; // No N+1 query
    }
}
```

## Bulk Stock Operations

Process multiple products at once:

```php
use AIArmada\Stock\Facades\Stock;

$productsToRestock = [
    ['product' => $product1, 'quantity' => 100],
    ['product' => $product2, 'quantity' => 150],
    ['product' => $product3, 'quantity' => 75],
];

DB::transaction(function () use ($productsToRestock) {
    foreach ($productsToRestock as $item) {
        Stock::addStock(
            $item['product'],
            $item['quantity'],
            'restock',
            'Bulk restock operation'
        );
    }
});
```

## Stock Alerts

Create a command to check low stock:

```php
// app/Console/Commands/CheckLowStock.php

use App\Models\Product;
use Illuminate\Console\Command;

class CheckLowStock extends Command
{
    protected $signature = 'stock:check-low';
    
    protected $description = 'Check for products with low stock';
    
    public function handle(): int
    {
        $lowStockProducts = Product::all()->filter(function ($product) {
            return $product->isLowStock();
        });
        
        if ($lowStockProducts->isEmpty()) {
            $this->info('✅ All products have sufficient stock!');
            return 0;
        }
        
        $this->warn("⚠️  {$lowStockProducts->count()} products have low stock:");
        
        foreach ($lowStockProducts as $product) {
            $currentStock = $product->getCurrentStock();
            $this->line("  - {$product->name}: {$currentStock} units");
        }
        
        return 1;
    }
}
```

## Stock Reports

Generate stock reports:

```php
use App\Models\Product;
use AIArmada\Stock\Models\StockTransaction;

class StockReportService
{
    public function generateReport(Carbon $startDate, Carbon $endDate): array
    {
        $report = [];
        
        $products = Product::all();
        
        foreach ($products as $product) {
            $transactions = $product->stockTransactions()
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->get();
            
            $totalIn = $transactions->where('type', 'in')->sum('quantity');
            $totalOut = $transactions->where('type', 'out')->sum('quantity');
            
            $report[] = [
                'product' => $product->name,
                'current_stock' => $product->getCurrentStock(),
                'received' => $totalIn,
                'sold' => $totalOut,
                'net_change' => $totalIn - $totalOut,
            ];
        }
        
        return $report;
    }
}
```

## Events Integration

Create observers for stock transactions:

```php
// app/Observers/StockTransactionObserver.php

use AIArmada\Stock\Models\StockTransaction;

class StockTransactionObserver
{
    public function created(StockTransaction $transaction): void
    {
        // Send notification if stock is low
        if ($transaction->type === 'out') {
            $stockable = $transaction->stockable;
            
            if ($stockable->isLowStock()) {
                // Notify admin
                Notification::send(
                    User::admins()->get(),
                    new LowStockNotification($stockable)
                );
            }
        }
        
        // Log large stock movements
        if ($transaction->quantity > 100) {
            Log::info('Large stock movement', [
                'type' => $transaction->type,
                'quantity' => $transaction->quantity,
                'stockable' => $transaction->stockable_type,
                'stockable_id' => $transaction->stockable_id,
            ]);
        }
    }
}

// In AppServiceProvider
StockTransaction::observe(StockTransactionObserver::class);
```

## Stock Transfer Between Warehouses

If you have multiple warehouses:

```php
class WarehouseTransferService
{
    public function transfer(
        Product $product,
        int $quantity,
        string $fromWarehouse,
        string $toWarehouse
    ): void {
        DB::transaction(function () use ($product, $quantity, $fromWarehouse, $toWarehouse) {
            // Remove from source warehouse
            $product->removeStock(
                $quantity,
                'transfer',
                "Transfer to {$toWarehouse}"
            );
            
            // Add to destination warehouse
            // (This would need warehouse-specific products or a different approach)
            $product->addStock(
                $quantity,
                'transfer',
                "Transfer from {$fromWarehouse}"
            );
        });
    }
}
```

## Reservation System

Implement stock reservations (e.g., for pending orders):

```php
class StockReservationService
{
    public function reserve(Product $product, int $quantity, string $orderId): bool
    {
        if (!$product->hasStock($quantity)) {
            return false;
        }
        
        // Create a reservation transaction with a special reason
        $product->removeStock(
            $quantity,
            'reserved',
            "Reserved for order #{$orderId}"
        );
        
        return true;
    }
    
    public function confirmReservation(Product $product, string $orderId): void
    {
        // Update the reservation to a sale
        $transaction = $product->stockTransactions()
            ->where('reason', 'reserved')
            ->where('note', 'like', "%order #{$orderId}%")
            ->first();
        
        if ($transaction) {
            $transaction->update([
                'reason' => 'sale',
                'note' => "Sale - Order #{$orderId}",
            ]);
        }
    }
    
    public function releaseReservation(Product $product, int $quantity, string $orderId): void
    {
        // Release reserved stock
        $product->addStock(
            $quantity,
            'release',
            "Released reservation for order #{$orderId}"
        );
    }
}
```

## Integration with Order Processing

Automatically manage stock when orders are placed:

```php
// app/Listeners/DeductStockOnOrderConfirmed.php

use App\Events\OrderConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeductStockOnOrderConfirmed implements ShouldQueue
{
    public function handle(OrderConfirmed $event): void
    {
        $order = $event->order;
        
        foreach ($order->items as $item) {
            $product = $item->product;
            
            if (!$product->hasStock($item->quantity)) {
                throw new InsufficientStockException(
                    "Insufficient stock for {$product->name}"
                );
            }
            
            $product->removeStock(
                $item->quantity,
                'sale',
                "Order #{$order->id}"
            );
        }
    }
}
```

## Stock Valuation Report

Calculate total inventory value:

```php
class InventoryValuationService
{
    public function getTotalValue(): int
    {
        $products = Product::all();
        $totalValue = 0;
        
        foreach ($products as $product) {
            $currentStock = $product->getCurrentStock();
            $productValue = $currentStock * $product->price;
            $totalValue += $productValue;
        }
        
        return $totalValue;
    }
    
    public function getProductValuation(Product $product): array
    {
        $currentStock = $product->getCurrentStock();
        $totalValue = $currentStock * $product->price;
        
        return [
            'product' => $product->name,
            'quantity' => $currentStock,
            'unit_price' => $product->price,
            'total_value' => $totalValue,
        ];
    }
}
```

## Custom Stock Rules

Implement business-specific stock rules:

```php
class StockRuleService
{
    public function canRemoveStock(Product $product, int $quantity): bool
    {
        // Rule 1: Must have sufficient stock
        if (!$product->hasStock($quantity)) {
            return false;
        }
        
        // Rule 2: Don't allow stock to go below safety stock level
        $safetyStock = $product->safety_stock ?? 0;
        $afterRemoval = $product->getCurrentStock() - $quantity;
        
        if ($afterRemoval < $safetyStock) {
            return false;
        }
        
        // Rule 3: Check if product is discontinued
        if ($product->is_discontinued) {
            return false;
        }
        
        return true;
    }
}
```

## REST API Endpoints

Create API endpoints for stock management:

```php
// routes/api.php

Route::middleware('auth:sanctum')->group(function () {
    // Get current stock
    Route::get('/products/{product}/stock', function (Product $product) {
        return response()->json([
            'current_stock' => $product->getCurrentStock(),
            'is_low_stock' => $product->isLowStock(),
        ]);
    });
    
    // Add stock
    Route::post('/products/{product}/stock/add', function (Request $request, Product $product) {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string',
            'note' => 'nullable|string',
        ]);
        
        $transaction = $product->addStock(
            $request->quantity,
            $request->reason,
            $request->note,
            auth()->id()
        );
        
        return response()->json([
            'message' => 'Stock added successfully',
            'transaction' => $transaction,
            'current_stock' => $product->getCurrentStock(),
        ]);
    });
    
    // Get stock history
    Route::get('/products/{product}/stock/history', function (Product $product) {
        return response()->json([
            'history' => $product->getStockHistory(100),
        ]);
    });
});
```

## Testing Stock Operations

Example tests for your application:

```php
// tests/Feature/StockManagementTest.php

use App\Models\Product;

it('deducts stock when order is confirmed', function () {
    $product = Product::factory()->create();
    $product->addStock(100);
    
    $order = Order::factory()->create();
    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
    ]);
    
    event(new OrderConfirmed($order));
    
    expect($product->getCurrentStock())->toBe(90);
});

it('prevents overselling', function () {
    $product = Product::factory()->create();
    $product->addStock(5);
    
    $order = Order::factory()->create();
    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
    ]);
    
    expect(fn () => event(new OrderConfirmed($order)))
        ->toThrow(InsufficientStockException::class);
});
```

## Summary

The stock package is flexible enough to handle:
- ✅ Multiple stockable models
- ✅ Bulk operations
- ✅ Stock reservations
- ✅ Automated stock management
- ✅ Reporting and analytics
- ✅ API integration
- ✅ Event-driven workflows
- ✅ Custom business rules

Use these patterns as starting points for your specific needs! 🚀

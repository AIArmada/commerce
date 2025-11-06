# Stock Management Package for Laravel

A Laravel package for managing stock/inventory with UUID support, built with PHPStan level 6 compliance.

## Features

- ✅ UUID-based stock transactions
- ✅ Polymorphic relationships - any model can have stock
- ✅ `HasStock` trait for easy integration
- ✅ Stock history tracking
- ✅ Low stock detection
- ✅ Multiple transaction types (in/out)
- ✅ Customizable transaction reasons
- ✅ PHPStan level 6 compliant
- ✅ Comprehensive test coverage
- ✅ Built with Spatie Package Tools

## Installation

Install the package via Composer:

```bash
composer require aiarmada/stock
```

Publish and run the migrations:

```bash
php artisan migrate
```

Optionally publish the configuration file:

```bash
php artisan vendor:publish --tag=stock-config
```

## Usage

### Making a Model Stackable

Add the `HasStock` trait to any model:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use AIArmada\Stock\Traits\HasStock;

class Product extends Model
{
    use HasUuids, HasStock;

    // ...
}
```

### Adding Stock

```php
// Using the trait
$product = Product::find($id);
$product->addStock(100, 'restock', 'Supplier delivery');

// Using the facade
use AIArmada\Stock\Facades\Stock;

Stock::addStock($product, 100, 'restock', 'Supplier delivery');

// Using the service
use AIArmada\Stock\Services\StockService;

$stockService = app(StockService::class);
$stockService->addStock($product, 100, 'restock', 'Supplier delivery');
```

### Removing Stock

```php
// Using the trait
$product->removeStock(20, 'sale', 'Customer order #123');

// Using the facade
Stock::removeStock($product, 20, 'sale', 'Customer order #123');
```

### Getting Current Stock

```php
// Using the trait
$currentStock = $product->getCurrentStock(); // Returns integer

// Using the facade
$currentStock = Stock::getCurrentStock($product);
```

### Checking Stock Availability

```php
// Check if has stock
if ($product->hasStock(50)) {
    // Product has at least 50 units
}

// Check if stock is low
if ($product->isLowStock()) {
    // Stock is below threshold (default: 10)
}

// Custom threshold
if ($product->isLowStock(20)) {
    // Stock is below 20 units
}
```

### Adjusting Stock

Useful for inventory corrections:

```php
$currentStock = $product->getCurrentStock(); // 95
$actualStock = 100; // Counted during stocktake

Stock::adjustStock($product, $currentStock, $actualStock);
// Creates a transaction of type 'in' with quantity 5
```

### Stock History

```php
// Using the trait
$history = $product->getStockHistory(); // Last 50 transactions
$history = $product->getStockHistory(100); // Last 100 transactions

// Using the service
$history = Stock::getStockHistory($product, 50);
```

### Accessing Stock Transactions

```php
// Get all stock transactions
$transactions = $product->stockTransactions;

// Get latest transaction
$latest = $product->stockTransactions()->latest()->first();

// Filter by type
$inbound = $product->stockTransactions()->where('type', 'in')->get();
$outbound = $product->stockTransactions()->where('type', 'out')->get();
```

## Configuration

The configuration file is located at `config/stock.php`:

```php
return [
    // Database table name
    'table_name' => env('STOCK_TABLE_NAME', 'stock_transactions'),

    // Low stock threshold
    'low_stock_threshold' => env('STOCK_LOW_THRESHOLD', 10),

    // Transaction types
    'transaction_types' => [
        'in' => 'Stock In',
        'out' => 'Stock Out',
    ],

    // Transaction reasons
    'transaction_reasons' => [
        'restock' => 'Restock',
        'sale' => 'Sale',
        'return' => 'Return',
        'adjustment' => 'Adjustment',
        'damaged' => 'Damaged',
        'initial' => 'Initial Stock',
    ],

    // Use soft deletes
    'use_soft_deletes' => env('STOCK_USE_SOFT_DELETES', false),
];
```

## Database Schema

The package creates a `stock_transactions` table with the following structure:

- `id` (UUID) - Primary key
- `stockable_type` (string) - Polymorphic model type
- `stockable_id` (UUID) - Polymorphic model ID
- `user_id` (UUID, nullable) - User who performed the transaction
- `quantity` (integer) - Quantity of stock
- `type` (enum: 'in', 'out') - Transaction type
- `reason` (string, nullable) - Reason for transaction
- `note` (text, nullable) - Additional notes
- `transaction_date` (timestamp) - When the transaction occurred
- `created_at` (timestamp)
- `updated_at` (timestamp)

## Testing

The package comes with comprehensive tests:

```bash
cd packages/commerce/packages/stock
composer install
composer test
```

## Code Quality

### Run PHPStan (Level 6)

```bash
composer analyse
```

### Format Code

```bash
composer format
```

## Development Tools

This package includes:

- **Rector** for Laravel-specific code refactoring
- **Larastan** (PHPStan for Laravel) at level 6
- **Laravel Pint** for code formatting
- **Pest PHP** for testing
- **Spatie Package Tools** for service provider

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [AIArmada](https://github.com/AIArmada)

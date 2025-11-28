# Laravel Stock

A comprehensive inventory and stock management package for Laravel with UUID support, reservation system, and seamless cart integration.

## Features

- **Stock Transactions** - Track all stock movements with full audit trail
- **Polymorphic Design** - Add stock tracking to any Eloquent model
- **Reservation System** - Prevent overselling during checkout
- **Cart Integration** - Automatic integration with `aiarmada/cart`
- **Payment Integration** - Auto-deduct stock on successful payment
- **Event-Driven** - Dispatch events for low stock, out of stock, and more
- **Configurable** - Control thresholds, TTLs, and event dispatching
- **UUID Support** - First-class UUID support for all models

## Installation

```bash
composer require aiarmada/stock
```

The package auto-discovers and registers itself. Run migrations:

```bash
php artisan migrate
```

Optionally publish the configuration:

```bash
php artisan vendor:publish --tag=stock-config
```

## Quick Start

### 1. Add Trait to Your Model

```php
use AIArmada\Stock\Contracts\StockableInterface;
use AIArmada\Stock\Traits\HasStock;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements StockableInterface
{
    use HasUuids, HasStock;
}
```

### 2. Manage Stock

```php
$product = Product::find($id);

// Add stock
$product->addStock(100, 'restock', 'Supplier delivery');

// Remove stock
$product->removeStock(5, 'sale', 'Order #123');

// Check stock levels
$current = $product->getCurrentStock();      // 95
$available = $product->getAvailableStock();  // Accounts for reservations

// Stock checks
$product->hasStock(10);           // true if >= 10 units
$product->hasAvailableStock(10);  // true if >= 10 available (minus reservations)
$product->isLowStock();           // true if below threshold (default: 10)
```

### 3. Use Facades

```php
use AIArmada\Stock\Facades\Stock;
use AIArmada\Stock\Facades\StockReservations;

// Stock management
Stock::addStock($product, 100, 'restock');
Stock::removeStock($product, 5, 'sale');
Stock::getCurrentStock($product);
Stock::adjustStock($product, 95, 100); // Correction: 95 → 100

// Reservations
StockReservations::reserve($product, 5, 'cart-123', 30);
StockReservations::release($product, 'cart-123');
StockReservations::getAvailableStock($product);
StockReservations::commitReservations('cart-123', 'order-456');
```

## Stock Reservations

Reservations temporarily hold stock during checkout to prevent overselling:

```php
// Reserve stock (expires in 30 minutes)
$reservation = $product->reserveStock(5, 'cart-123', 30);

// Check available stock (current - reserved)
$available = $product->getAvailableStock();

// Release reservation (cart abandoned)
$product->releaseReservedStock('cart-123');

// Commit reservation (payment successful)
StockReservations::commitReservations('cart-123', 'order-456');
```

## Cart Integration

When installed with `aiarmada/cart`, the package automatically:

1. Extends `CartManager` with stock-aware methods
2. Releases reservations when carts are cleared
3. Deducts stock on payment success

```php
use AIArmada\Cart\Facades\Cart;

// Reserve stock for checkout
$results = Cart::reserveAllStock(30);

// Validate stock availability
$validation = Cart::validateStock();
if (!$validation['available']) {
    // Handle insufficient stock
}

// Commit after payment
Cart::commitStock('order-123');

// Release on abandon
Cart::releaseAllStock();
```

## Events

The package dispatches events you can listen to:

| Event | Description |
|-------|-------------|
| `StockReserved` | Stock reserved for a cart |
| `StockReleased` | Reservation released |
| `StockDeducted` | Stock removed (sale complete) |
| `LowStockDetected` | Stock fell below threshold |
| `OutOfStock` | Stock reached zero |

```php
use AIArmada\Stock\Events\LowStockDetected;

class SendLowStockAlert
{
    public function handle(LowStockDetected $event): void
    {
        // $event->stockable - the product
        // $event->currentStock - current level
        // $event->threshold - configured threshold
    }
}
```

## Commands

```bash
# Clean up expired reservations
php artisan stock:cleanup-reservations
```

Schedule in `app/Console/Kernel.php`:

```php
$schedule->command('stock:cleanup-reservations')->everyFiveMinutes();
```

## Configuration

Key configuration options in `config/stock.php`:

```php
return [
    'low_stock_threshold' => 10,
    
    'cart' => [
        'enabled' => true,
        'reservation_ttl' => 30,
    ],
    
    'payment' => [
        'auto_deduct' => true,
    ],
    
    'events' => [
        'low_stock' => true,
        'out_of_stock' => true,
    ],
    
    'cleanup' => [
        'keep_expired_for_minutes' => 0,
    ],
];
```

## Documentation

- [Configuration Guide](docs/configuration.md)
- [Cart Integration](docs/cart-integration.md)
- [Events & Listeners](docs/events.md)
- [API Reference](docs/api-reference.md)

## Testing

```bash
./vendor/bin/pest tests/src/Stock
```

## License

MIT License. See [LICENSE](LICENSE) for details.

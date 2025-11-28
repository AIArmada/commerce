# Events & Listeners

The stock package uses an event-driven architecture for notifications and integrations.

## Available Events

### StockReserved

Dispatched when stock is reserved for a cart.

```php
use AIArmada\Stock\Events\StockReserved;

class HandleStockReserved
{
    public function handle(StockReserved $event): void
    {
        $product = $event->stockable;
        $quantity = $event->quantity;
        $cartId = $event->cartId;
        $reservation = $event->reservation;
        
        // Log or notify
    }
}
```

### StockReleased

Dispatched when a reservation is released.

```php
use AIArmada\Stock\Events\StockReleased;

class HandleStockReleased
{
    public function handle(StockReleased $event): void
    {
        $product = $event->stockable;
        $quantity = $event->quantity;
        $cartId = $event->cartId;
    }
}
```

### StockDeducted

Dispatched when stock is deducted (sale complete).

```php
use AIArmada\Stock\Events\StockDeducted;

class HandleStockDeducted
{
    public function handle(StockDeducted $event): void
    {
        $product = $event->stockable;
        $quantity = $event->quantity;
        $reason = $event->reason;
        $orderId = $event->orderId;
        $transaction = $event->transaction;
    }
}
```

### LowStockDetected

Dispatched when stock falls below the configured threshold.

```php
use AIArmada\Stock\Events\LowStockDetected;

class SendLowStockAlert
{
    public function handle(LowStockDetected $event): void
    {
        $product = $event->stockable;
        $currentStock = $event->currentStock;
        $threshold = $event->threshold;
        
        // Send notification to admin
        Notification::send(
            User::admins()->get(),
            new LowStockNotification($product, $currentStock)
        );
    }
}
```

### OutOfStock

Dispatched when stock reaches zero.

```php
use AIArmada\Stock\Events\OutOfStock;

class HandleOutOfStock
{
    public function handle(OutOfStock $event): void
    {
        $product = $event->stockable;
        
        // Disable product
        $product->update(['is_active' => false]);
        
        // Notify admin
        Notification::send(
            User::admins()->get(),
            new OutOfStockNotification($product)
        );
    }
}
```

## Registering Listeners

### In EventServiceProvider

```php
// app/Providers/EventServiceProvider.php

use AIArmada\Stock\Events\LowStockDetected;
use AIArmada\Stock\Events\OutOfStock;
use AIArmada\Stock\Events\StockDeducted;
use App\Listeners\SendLowStockAlert;
use App\Listeners\HandleOutOfStock;
use App\Listeners\LogStockDeduction;

protected $listen = [
    LowStockDetected::class => [
        SendLowStockAlert::class,
    ],
    OutOfStock::class => [
        HandleOutOfStock::class,
    ],
    StockDeducted::class => [
        LogStockDeduction::class,
    ],
];
```

### Using Closures

```php
// app/Providers/AppServiceProvider.php

use AIArmada\Stock\Events\LowStockDetected;
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    Event::listen(LowStockDetected::class, function ($event) {
        Log::warning('Low stock alert', [
            'product' => $event->stockable->name,
            'current' => $event->currentStock,
            'threshold' => $event->threshold,
        ]);
    });
}
```

## Built-in Listeners

### ReleaseStockOnCartClear

Automatically releases stock reservations when cart events occur:

- `AIArmada\Cart\Events\CartCleared`
- `AIArmada\Cart\Events\CartDestroyed`

Registered automatically when cart package is installed.

### DeductStockOnPaymentSuccess

Commits reservations when payment succeeds:

- `AIArmada\CashierChip\Events\PaymentSucceeded`
- `AIArmada\Cashier\Events\PaymentSucceeded`
- Custom events from config

Registered automatically when payment packages are installed.

## Configuring Event Dispatching

Disable specific events via config:

```php
// config/stock.php
'events' => [
    'low_stock' => true,      // Dispatch LowStockDetected
    'out_of_stock' => true,   // Dispatch OutOfStock
    'reserved' => true,       // Dispatch StockReserved
    'released' => true,       // Dispatch StockReleased
    'deducted' => true,       // Dispatch StockDeducted
],
```

Or via environment:

```env
STOCK_EVENT_LOW_STOCK=true
STOCK_EVENT_OUT_OF_STOCK=true
STOCK_EVENT_RESERVED=false    # Disable reservation events
STOCK_EVENT_RELEASED=false
STOCK_EVENT_DEDUCTED=true
```

## Custom Payment Events

Listen to custom payment events for stock deduction:

```php
// config/stock.php
'payment' => [
    'auto_deduct' => true,
    'events' => [
        App\Events\OrderPaid::class,
        App\Events\PaymentCompleted::class,
    ],
],
```

Your event should include cart/order information:

```php
class OrderPaid
{
    public function __construct(
        public Order $order,
        public string $cart_id,    // For reservation lookup
        public ?string $order_id,  // For transaction note
    ) {}
}
```

## Queueing Listeners

The built-in listeners are synchronous. To queue them, extend and implement `ShouldQueue`:

```php
namespace App\Listeners;

use AIArmada\Stock\Listeners\DeductStockOnPaymentSuccess as BaseListener;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class QueuedStockDeduction extends BaseListener implements ShouldQueue
{
    use InteractsWithQueue;
    
    public int $tries = 3;
    public int $backoff = 5;
}
```

Then register your queued listener instead:

```php
// EventServiceProvider
use AIArmada\CashierChip\Events\PaymentSucceeded;
use App\Listeners\QueuedStockDeduction;

protected $listen = [
    PaymentSucceeded::class => [
        QueuedStockDeduction::class,
    ],
];
```

## Testing Events

```php
use AIArmada\Stock\Events\LowStockDetected;
use Illuminate\Support\Facades\Event;

test('dispatches low stock event when threshold reached', function () {
    Event::fake([LowStockDetected::class]);
    
    $product = Product::create(['name' => 'Test']);
    Stock::addStock($product, 5);
    
    // Deduct stock to trigger low stock
    StockReservations::deductStock($product, 3, 'sale');
    
    Event::assertDispatched(LowStockDetected::class, function ($event) use ($product) {
        return $event->stockable->is($product)
            && $event->currentStock === 2;
    });
});
```

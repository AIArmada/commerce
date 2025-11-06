# 🔔 Events System

> **Hook into cart lifecycle events for logging, analytics, and business logic integration.**

The cart package dispatches Laravel events for every significant cart action, allowing you to respond to cart changes in real-time.

## 📋 Table of Contents

- [Event Catalog](#event-catalog)
- [Enabling Events](#enabling-events)
- [Listening to Events](#listening-to-events)
- [Event Payloads](#event-payloads)
- [Common Patterns](#common-patterns)
- [Testing Events](#testing-events)
- [Troubleshooting](#troubleshooting)

---

## Event Catalog

### Cart Item Events

| Event | Fired When | Payload |
|-------|-----------|---------|
| `CartItemAdded` | Item added to cart | `$cartItem`, `$instance` |
| `CartItemUpdated` | Item quantity/price/attributes changed | `$cartItem`, `$instance` |
| `CartItemRemoved` | Item removed from cart | `$itemId`, `$instance` |
| `CartCleared` | All items removed from cart | `$instance` |

### Metadata Events

| Event | Fired When | Payload |
|-------|-----------|---------|
| `MetadataSet` | Metadata key set or updated | `$key`, `$value`, `$instance` |
| `MetadataRemoved` | Metadata key removed | `$key`, `$instance` |

### All Events Namespace

```php
AIArmada\Cart\Events\{EventName}
```

---

## Enabling Events

Events are enabled by default. To disable globally:

```php
// config/cart.php
return [
    'events' => false, // Disable all cart events
];
```

To disable events temporarily:

```php
// Disable events for specific operations
Cart::withoutEvents(function () {
    Cart::add('sku-1', 'Product', 1000, 10);
    Cart::add('sku-2', 'Product', 2000, 5);
    // No events fired for these operations
});

// Events resume after closure
Cart::add('sku-3', 'Product', 3000, 1); // Event fired
```

---

## Listening to Events

### Method 1: Event Listeners (Recommended)

Create dedicated listener classes for clean, testable code.

```php
// app/Listeners/Cart/LogCartActivity.php
namespace App\Listeners\Cart;

use AIArmada\Cart\Events\CartItemAdded;
use Illuminate\Support\Facades\Log;

class LogCartActivity
{
    public function handle(CartItemAdded $event): void
    {
        Log::info('Cart item added', [
            'item_id' => $event->cartItem->id,
            'name' => $event->cartItem->name,
            'quantity' => $event->cartItem->quantity,
            'price' => $event->cartItem->getPrice()->format(),
            'instance' => $event->instance,
        ]);
    }
}
```

Register in `app/Providers/AppServiceProvider.php` or `EventServiceProvider.php`:

```php
use Illuminate\Support\Facades\Event;
use AIArmada\Cart\Events\CartItemAdded;
use App\Listeners\Cart\LogCartActivity;

public function boot(): void
{
    Event::listen(
        CartItemAdded::class,
        LogCartActivity::class
    );
}
```

### Method 2: Closure Listeners (Quick Prototyping)

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Event;
use AIArmada\Cart\Events\CartItemAdded;

public function boot(): void
{
    Event::listen(CartItemAdded::class, function (CartItemAdded $event) {
        logger('Item added to cart', [
            'id' => $event->cartItem->id,
            'instance' => $event->instance,
        ]);
    });
}
```

---

## Event Payloads

### CartItemAdded

```php
namespace AIArmada\Cart\Events;

class CartItemAdded
{
    public function __construct(
        public CartItem $cartItem,  // The added item
        public string $instance      // Cart instance ('default', 'wishlist', etc.)
    ) {}
}
```

**Access item data:**

```php
$event->cartItem->id;           // Item ID
$event->cartItem->name;         // Item name
$event->cartItem->quantity;     // Quantity
$event->cartItem->getPrice();   // Money object
$event->cartItem->attributes;   // Collection of attributes
$event->instance;               // Cart instance
```

### CartItemUpdated

```php
class CartItemUpdated
{
    public function __construct(
        public CartItem $cartItem,  // Updated item (new values)
        public string $instance
    ) {}
}
```

### CartItemRemoved

```php
class CartItemRemoved
{
    public function __construct(
        public string $itemId,      // ID of removed item
        public string $instance
    ) {}
}
```

### CartCleared

```php
class CartCleared
{
    public function __construct(
        public string $instance     // Which cart was cleared
    ) {}
}
```

### MetadataSet

```php
class MetadataSet
{
    public function __construct(
        public string $key,         // Metadata key
        public mixed $value,        // Metadata value
        public string $instance
    ) {}
}
```

### MetadataRemoved

```php
class MetadataRemoved
{
    public function __construct(
        public string $key,         // Removed metadata key
        public string $instance
    ) {}
}
```

---

## Common Patterns

### 1. Analytics Tracking

```php
namespace App\Listeners\Cart;

use AIArmada\Cart\Events\CartItemAdded;

class TrackAnalytics
{
    public function handle(CartItemAdded $event): void
    {
        // Track with Google Analytics, Mixpanel, etc.
        analytics()->track('Product Added to Cart', [
            'product_id' => $event->cartItem->id,
            'product_name' => $event->cartItem->name,
            'price' => $event->cartItem->getPrice()->getAmount() / 100,
            'currency' => $event->cartItem->getPrice()->getCurrency()->getCurrency(),
            'quantity' => $event->cartItem->quantity,
        ]);
    }
}
```

### 2. Inventory Updates

```php
namespace App\Listeners\Cart;

use AIArmada\Cart\Events\CartItemAdded;
use App\Models\Product;

class ReserveInventory
{
    public function handle(CartItemAdded $event): void
    {
        $product = Product::find($event->cartItem->id);
        
        if ($product && $product->track_inventory) {
            $product->decrement('reserved_stock', $event->cartItem->quantity);
        }
    }
}
```

### 3. User Notifications

```php
namespace App\Listeners\Cart;

use AIArmada\Cart\Events\CartItemAdded;
use App\Notifications\ItemAddedNotification;
use Illuminate\Support\Facades\Auth;

class NotifyUser
{
    public function handle(CartItemAdded $event): void
    {
        if ($user = Auth::user()) {
            $user->notify(new ItemAddedNotification($event->cartItem));
        }
    }
}
```

### 4. Abandoned Cart Detection

```php
namespace App\Listeners\Cart;

use AIArmada\Cart\Events\CartItemAdded;
use App\Models\AbandonedCart;
use Illuminate\Support\Facades\Auth;

class TrackAbandonedCart
{
    public function handle(CartItemAdded $event): void
    {
        if ($user = Auth::user()) {
            AbandonedCart::updateOrCreate(
                ['user_id' => $user->id, 'instance' => $event->instance],
                [
                    'cart_data' => Cart::content(),
                    'total' => Cart::total()->getAmount(),
                    'last_activity' => now(),
                ]
            );
        }
    }
}
```

### 5. Price Alerts

```php
namespace App\Listeners\Cart;

use AIArmada\Cart\Events\CartItemUpdated;
use App\Models\PriceAlert;

class CheckPriceAlerts
{
    public function handle(CartItemUpdated $event): void
    {
        $oldPrice = $event->cartItem->getPrice()->getAmount();
        
        // Check if price dropped
        $alert = PriceAlert::where('product_id', $event->cartItem->id)
            ->where('threshold_price', '>=', $oldPrice)
            ->first();
        
        if ($alert) {
            $alert->user->notify(new PriceDropNotification($event->cartItem));
        }
    }
}
```

---

## Testing Events

### Test Event Dispatching

```php
use AIArmada\Cart\Events\CartItemAdded;
use Illuminate\Support\Facades\Event;

it('dispatches event when item added', function () {
    Event::fake([CartItemAdded::class]);
    
    Cart::add('sku-1', 'Product', 1000, 1);
    
    Event::assertDispatched(CartItemAdded::class, function ($event) {
        return $event->cartItem->id === 'sku-1'
            && $event->cartItem->quantity === 1
            && $event->instance === 'default';
    });
});

it('does not dispatch events when disabled', function () {
    Event::fake();
    
    Cart::withoutEvents(fn () => Cart::add('sku-1', 'Product', 1000, 1));
    
    Event::assertNotDispatched(CartItemAdded::class);
});
```

### Test Listeners

```php
use App\Listeners\Cart\LogCartActivity;
use AIArmada\Cart\Events\CartItemAdded;
use Illuminate\Support\Facades\Log;

it('logs cart activity', function () {
    Log::shouldReceive('info')
        ->once()
        ->with('Cart item added', \Mockery::type('array'));
    
    $event = new CartItemAdded(
        cartItem: new CartItem([
            'id' => 'sku-1',
            'name' => 'Product',
            'price' => 1000,
            'quantity' => 1
        ]),
        instance: 'default'
    );
    
    $listener = new LogCartActivity();
    $listener->handle($event);
});
```

### Test Event Flow

```php
use AIArmada\Cart\Events\{CartItemAdded, CartItemUpdated, CartItemRemoved, CartCleared};

it('tracks complete cart workflow', function () {
    Event::fake();
    
    // Add item
    Cart::add('sku-1', 'Product', 1000, 1);
    Event::assertDispatched(CartItemAdded::class);
    
    // Update item
    Cart::update('sku-1', ['quantity' => 2]);
    Event::assertDispatched(CartItemUpdated::class);
    
    // Remove item
    Cart::remove('sku-1');
    Event::assertDispatched(CartItemRemoved::class);
    
    // Clear cart
    Cart::clear();
    Event::assertDispatched(CartCleared::class);
    
    // Assert event counts
    Event::assertDispatchedTimes(CartItemAdded::class, 1);
    Event::assertDispatchedTimes(CartItemUpdated::class, 1);
    Event::assertDispatchedTimes(CartItemRemoved::class, 1);
    Event::assertDispatchedTimes(CartCleared::class, 1);
});
```

---

## Troubleshooting

### Issue: Events Not Firing

**Symptoms:**
- Listeners not called
- Expected side effects not occurring

**Solutions:**

1. **Check event configuration:**
```php
// config/cart.php
'events' => true, // Must be enabled
```

2. **Verify listener registration:**
```php
// app/Providers/AppServiceProvider.php
Event::listen(CartItemAdded::class, YourListener::class);
```

3. **Check listener method signature:**
```php
// Must accept the correct event type
public function handle(CartItemAdded $event): void
{
    // ...
}
```

4. **Clear cached events:**
```bash
php artisan event:clear
php artisan config:clear
```

### Issue: Events Firing Multiple Times

**Symptoms:**
- Listener executed 2+ times per action
- Duplicate logs/notifications

**Solutions:**

1. **Check for duplicate registrations:**
```php
// Only register once in EventServiceProvider or AppServiceProvider
Event::listen(CartItemAdded::class, YourListener::class);
```

2. **Avoid registering in multiple service providers**

3. **Check for nested operations:**
```php
// ❌ BAD: Creates infinite loop
public function handle(CartItemAdded $event): void
{
    Cart::add('bonus-item', 'Bonus', 0, 1); // Triggers another CartItemAdded!
}

// ✅ GOOD: Use withoutEvents
public function handle(CartItemAdded $event): void
{
    Cart::withoutEvents(fn () => Cart::add('bonus-item', 'Bonus', 0, 1));
}
```

### Issue: Listener Errors Not Visible

**Symptoms:**
- Listener throws exception but cart operation succeeds
- Errors hidden

**Solution:**

Laravel events are synchronous by default, so exceptions should propagate. If not seeing errors:

1. **Check Laravel logs:**
```bash
tail -f storage/logs/laravel.log
```

2. **Add try-catch in listener for debugging:**
```php
public function handle(CartItemAdded $event): void
{
    try {
        // Your code
    } catch (\Exception $e) {
        Log::error('Listener failed', ['error' => $e->getMessage()]);
        throw $e;
    }
}
```

---

## Best Practices

1. **Keep listeners focused** - One responsibility per listener
2. **Use descriptive listener names** - `TrackAnalytics`, not `Handle`
3. **Test listeners independently** - Unit test listener logic
4. **Use `withoutEvents()` for bulk operations** - Avoid event storms
5. **Document side effects** - Make it clear what each listener does
6. **Handle failures gracefully** - Don't let listener failures break cart operations

---

## Additional Resources

- [Configuration](configuration.md) – Event configuration options
- [Cart Operations](cart-operations.md) – Actions that trigger events
- [Examples](examples.md) – Real-world event listener examples

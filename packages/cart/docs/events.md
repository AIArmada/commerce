# Events

Subscribe to cart lifecycle events for logging, analytics, notifications, and side effects.

## Available Events

| Event | Fired When |
|-------|------------|
| `ItemAdded` | Item added to cart |
| `ItemUpdated` | Item quantity or metadata updated |
| `ItemRemoved` | Item removed from cart |
| `CartCleared` | All items removed |
| `CartStored` | Cart persisted to storage |
| `CartRestored` | Cart loaded from storage |
| `ConditionAdded` | Pricing condition applied |
| `ConditionRemoved` | Condition removed |

## Registering Listeners

### EventServiceProvider

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \AIArmada\Cart\Events\ItemAdded::class => [
        \App\Listeners\LogItemAddedToCart::class,
        \App\Listeners\TrackAddToCart::class,
    ],
    \AIArmada\Cart\Events\CartCleared::class => [
        \App\Listeners\NotifyCartAbandonment::class,
    ],
];
```

### Closure Listeners

```php
use Illuminate\Support\Facades\Event;
use AIArmada\Cart\Events\ItemAdded;

Event::listen(ItemAdded::class, function (ItemAdded $event) {
    Log::info('Item added', [
        'sku' => $event->item->getIdentifier(),
        'quantity' => $event->item->getQuantity(),
    ]);
});
```

## Event Properties

### ItemAdded

```php
use AIArmada\Cart\Events\ItemAdded;

class TrackAddToCart
{
    public function handle(ItemAdded $event): void
    {
        $item = $event->item;
        $cart = $event->cart;
        
        Analytics::track('add_to_cart', [
            'sku' => $item->getIdentifier(),
            'name' => $item->getName(),
            'price' => $item->getPrice()->getAmount(),
            'quantity' => $item->getQuantity(),
            'cart_total' => $cart->total()->getAmount(),
        ]);
    }
}
```

### ItemUpdated

```php
use AIArmada\Cart\Events\ItemUpdated;

class LogQuantityChange
{
    public function handle(ItemUpdated $event): void
    {
        Log::info('Quantity changed', [
            'sku' => $event->item->getIdentifier(),
            'previous' => $event->previousQuantity,
            'current' => $event->item->getQuantity(),
        ]);
    }
}
```

### ItemRemoved

```php
use AIArmada\Cart\Events\ItemRemoved;

class TrackRemoval
{
    public function handle(ItemRemoved $event): void
    {
        Analytics::track('remove_from_cart', [
            'sku' => $event->item->getIdentifier(),
        ]);
    }
}
```

### CartCleared

```php
use AIArmada\Cart\Events\CartCleared;

class HandleCartClear
{
    public function handle(CartCleared $event): void
    {
        // Cart was cleared
        Log::info('Cart cleared', [
            'item_count' => $event->previousItemCount,
        ]);
    }
}
```

### CartStored / CartRestored

```php
use AIArmada\Cart\Events\CartStored;
use AIArmada\Cart\Events\CartRestored;

// Stored
Event::listen(CartStored::class, function (CartStored $event) {
    Cache::forget("cart-summary:{$event->identifier}");
});

// Restored
Event::listen(CartRestored::class, function (CartRestored $event) {
    // Rehydrate item metadata from database
    foreach ($event->cart->getContent() as $item) {
        $product = Product::find($item->getIdentifier());
        $item->updateMetadata(['stock' => $product->stock]);
    }
});
```

### ConditionAdded / ConditionRemoved

```php
use AIArmada\Cart\Events\ConditionAdded;

Event::listen(ConditionAdded::class, function (ConditionAdded $event) {
    Log::info('Condition applied', [
        'name' => $event->condition->getName(),
        'type' => $event->condition->getType(),
        'value' => $event->condition->getValue(),
    ]);
});
```

## Queued Listeners

For non-critical operations:

```php
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncInventory implements ShouldQueue
{
    public $queue = 'inventory';
    
    public function handle(ItemAdded $event): void
    {
        InventoryService::reserve(
            $event->item->getIdentifier(),
            $event->item->getQuantity()
        );
    }
}
```

## Practical Examples

### Analytics Integration

```php
class CartAnalyticsListener
{
    public function handle(ItemAdded|ItemRemoved|CartCleared $event): void
    {
        $action = match (get_class($event)) {
            ItemAdded::class => 'add_to_cart',
            ItemRemoved::class => 'remove_from_cart',
            CartCleared::class => 'clear_cart',
        };
        
        Analytics::track($action, $this->buildPayload($event));
    }
}
```

### Stock Reservation

```php
class ReserveStockOnAdd
{
    public function handle(ItemAdded $event): void
    {
        Stock::reserve(
            $event->item->getIdentifier(),
            $event->item->getQuantity(),
            $event->cart->getIdentifier()
        );
    }
}

class ReleaseStockOnRemove
{
    public function handle(ItemRemoved $event): void
    {
        Stock::release(
            $event->item->getIdentifier(),
            $event->item->getQuantity()
        );
    }
}
```

### Notification Triggers

```php
class NotifyWishlistMatch
{
    public function handle(ItemAdded $event): void
    {
        $users = Wishlist::usersWanting($event->item->getIdentifier())
            ->where('user_id', '!=', auth()->id())
            ->get();
        
        Notification::send($users, new WishlistItemInCart($event->item));
    }
}
```

## Disabling Events

For batch operations:

```php
Cart::withoutEvents(function () {
    foreach ($bulkItems as $item) {
        Cart::add($item['sku'], $item['name'], $item['price'], $item['qty']);
    }
});

// Fire single event after
event(new BulkItemsAdded($bulkItems));
```

## Testing Events

```php
use Illuminate\Support\Facades\Event;
use AIArmada\Cart\Events\ItemAdded;

it('fires ItemAdded event', function () {
    Event::fake([ItemAdded::class]);
    
    Cart::add('sku-123', 'Product', Money::MYR(5000), 1);
    
    Event::assertDispatched(ItemAdded::class, function ($event) {
        return $event->item->getIdentifier() === 'sku-123';
    });
});
```

## Next Steps

- [API Reference](api-reference.md) – Complete method list
- [Troubleshooting](troubleshooting.md) – Common issues

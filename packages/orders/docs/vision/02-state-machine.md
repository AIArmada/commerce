# Order State Machine Architecture

> **Document:** 02 of 08  
> **Package:** `aiarmada/orders`  
> **Status:** Vision  
> **Implementation:** `spatie/laravel-model-states` v2

---

## Overview

The Orders package uses **Spatie Laravel Model States** to implement a robust, type-safe state machine for order lifecycle management. Each state is represented as a PHP class with its own behavior, and transitions between states are explicitly defined with optional side-effect handling.

---

## Why Spatie Model States?

| Feature | Benefit |
|---------|---------|
| **Type-Safe States** | Each state is a class, not a string constant |
| **Explicit Transitions** | Only allowed transitions compile; invalid ones fail |
| **Transition Classes** | Business logic (emails, inventory) in dedicated classes |
| **Query Scopes** | `Order::whereState('status', Shipped::class)` |
| **Validation Rules** | Form validation for state fields |
| **Dependency Injection** | Inject services into transition handlers |

---

## State Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           ORDER STATE MACHINE                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│                              ┌─────────────┐                                 │
│                              │   CREATED   │                                 │
│                              │  (Initial)  │                                 │
│                              └──────┬──────┘                                 │
│                                     │                                        │
│                                     ▼                                        │
│                           ┌─────────────────┐                                │
│         ┌─────────────────│PENDING_PAYMENT  │─────────────────┐              │
│         │                 └────────┬────────┘                 │              │
│         │                          │                          │              │
│         ▼                          ▼                          ▼              │
│  ┌────────────┐           ┌────────────────┐           ┌───────────┐        │
│  │  CANCELED  │           │   PROCESSING   │           │  FAILED   │        │
│  └────────────┘           └───────┬────────┘           └───────────┘        │
│                                   │                                          │
│                    ┌──────────────┼──────────────┐                          │
│                    │              │              │                          │
│                    ▼              ▼              ▼                          │
│           ┌────────────┐  ┌────────────┐  ┌────────────┐                    │
│           │  ON_HOLD   │  │  SHIPPED   │  │   FRAUD    │                    │
│           └─────┬──────┘  └──────┬─────┘  └────────────┘                    │
│                 │                │                                           │
│                 │                ├─────────────────┐                        │
│                 │                │                 │                        │
│                 ▼                ▼                 ▼                        │
│           ┌────────────┐  ┌────────────┐   ┌────────────┐                   │
│           │ PROCESSING │  │ DELIVERED  │   │  RETURNED  │                   │
│           │  (Resume)  │  └──────┬─────┘   └────────────┘                   │
│           └────────────┘         │                                          │
│                                  ▼                                          │
│                           ┌────────────┐                                    │
│                           │ COMPLETED  │                                    │
│                           │  (Final)   │                                    │
│                           └────────────┘                                    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## State Classes

### Abstract Base State

```php
namespace AIArmada\Orders\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class OrderStatus extends State
{
    /**
     * Filament badge color
     */
    abstract public function color(): string;

    /**
     * Heroicon name
     */
    abstract public function icon(): string;

    /**
     * Human-readable label
     */
    abstract public function label(): string;

    /**
     * Can the customer cancel in this state?
     */
    public function canCancel(): bool
    {
        return false;
    }

    /**
     * Can the order be edited in this state?
     */
    public function canEdit(): bool
    {
        return false;
    }

    /**
     * State machine configuration
     */
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingPayment::class)
            // Payment flow
            ->allowTransition(PendingPayment::class, Processing::class, PaymentConfirmedTransition::class)
            ->allowTransition(PendingPayment::class, Canceled::class, OrderCanceledTransition::class)
            ->allowTransition(PendingPayment::class, Failed::class)
            // Processing flow
            ->allowTransition(Processing::class, Shipped::class, ShipmentCreatedTransition::class)
            ->allowTransition(Processing::class, OnHold::class)
            ->allowTransition(Processing::class, Fraud::class)
            ->allowTransition(Processing::class, Canceled::class, OrderCanceledTransition::class)
            // Hold flow
            ->allowTransition(OnHold::class, Processing::class)
            ->allowTransition(OnHold::class, Canceled::class, OrderCanceledTransition::class)
            // Shipping flow
            ->allowTransition(Shipped::class, Delivered::class, DeliveryConfirmedTransition::class)
            ->allowTransition(Shipped::class, Returned::class, ReturnInitiatedTransition::class)
            // Delivery flow
            ->allowTransition(Delivered::class, Completed::class)
            ->allowTransition(Delivered::class, Returned::class, ReturnInitiatedTransition::class)
            // Refund flow
            ->allowTransition(Returned::class, Refunded::class, RefundProcessedTransition::class);
    }
}
```

---

## Concrete State Implementations

### PendingPayment State

```php
namespace AIArmada\Orders\States;

class PendingPayment extends OrderStatus
{
    public function color(): string
    {
        return 'warning';
    }

    public function icon(): string
    {
        return 'heroicon-o-clock';
    }

    public function label(): string
    {
        return __('orders::states.pending_payment');
    }

    public function canCancel(): bool
    {
        return true; // Customer can cancel before paying
    }

    public function canEdit(): bool
    {
        return true; // Can modify order before payment
    }
}
```

### Processing State

```php
namespace AIArmada\Orders\States;

class Processing extends OrderStatus
{
    public function color(): string
    {
        return 'info';
    }

    public function icon(): string
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public function label(): string
    {
        return __('orders::states.processing');
    }
}
```

### Shipped State

```php
namespace AIArmada\Orders\States;

class Shipped extends OrderStatus
{
    public function color(): string
    {
        return 'primary';
    }

    public function icon(): string
    {
        return 'heroicon-o-truck';
    }

    public function label(): string
    {
        return __('orders::states.shipped');
    }
}
```

### Completed State (Final)

```php
namespace AIArmada\Orders\States;

class Completed extends OrderStatus
{
    public static bool $isFinal = true;

    public function color(): string
    {
        return 'success';
    }

    public function icon(): string
    {
        return 'heroicon-o-check-circle';
    }

    public function label(): string
    {
        return __('orders::states.completed');
    }
}
```

---

## Transition Classes

### PaymentConfirmedTransition

Triggered when payment is confirmed. Handles:
- Inventory deduction
- Customer notification
- Commission attribution (affiliates)

```php
namespace AIArmada\Orders\Transitions;

use Spatie\ModelStates\Transition;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\Events\OrderPaid;

class PaymentConfirmedTransition extends Transition
{
    public function __construct(
        private Order $order,
        private string $transactionId,
        private string $gateway,
    ) {}

    public function handle(): Order
    {
        // Record payment
        $this->order->payments()->create([
            'transaction_id' => $this->transactionId,
            'gateway' => $this->gateway,
            'amount' => $this->order->grand_total,
            'status' => 'completed',
        ]);

        // Deduct inventory (if inventory package present)
        if (class_exists(\AIArmada\Inventory\InventoryService::class)) {
            app(\AIArmada\Inventory\InventoryService::class)
                ->deductForOrder($this->order);
        }

        // Update state
        $this->order->status = new Processing($this->order);
        $this->order->paid_at = now();
        $this->order->save();

        // Dispatch event
        event(new OrderPaid($this->order));

        return $this->order;
    }
}
```

### ShipmentCreatedTransition

Triggered when order is shipped. Handles:
- Shipment record creation
- Tracking number association
- Customer notification

```php
namespace AIArmada\Orders\Transitions;

use Spatie\ModelStates\Transition;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Shipped;
use AIArmada\Orders\Events\OrderShipped;
use AIArmada\Orders\Notifications\OrderShippedNotification;

class ShipmentCreatedTransition extends Transition
{
    public function __construct(
        private Order $order,
        private string $carrier,
        private string $trackingNumber,
        private array $items = [],
    ) {}

    public function handle(): Order
    {
        // Create shipment record
        $shipment = $this->order->shipments()->create([
            'carrier' => $this->carrier,
            'tracking_number' => $this->trackingNumber,
            'shipped_at' => now(),
        ]);

        // Attach items to shipment
        foreach ($this->items as $item) {
            $shipment->items()->attach($item['order_item_id'], [
                'quantity' => $item['quantity'],
            ]);
        }

        // Update state
        $this->order->status = new Shipped($this->order);
        $this->order->shipped_at = now();
        $this->order->save();

        // Notify customer
        $this->order->customer->notify(
            new OrderShippedNotification($this->order, $shipment)
        );

        // Dispatch event
        event(new OrderShipped($this->order, $shipment));

        return $this->order;
    }
}
```

### OrderCanceledTransition

Triggered when order is canceled. Handles:
- Inventory restoration
- Payment refund (if paid)
- Customer notification

```php
namespace AIArmada\Orders\Transitions;

use Spatie\ModelStates\Transition;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\Events\OrderCanceled;

class OrderCanceledTransition extends Transition
{
    public function __construct(
        private Order $order,
        private string $reason,
        private ?string $canceledBy = null,
    ) {}

    public function handle(): Order
    {
        // Restore inventory if already deducted
        if ($this->order->paid_at && class_exists(\AIArmada\Inventory\InventoryService::class)) {
            app(\AIArmada\Inventory\InventoryService::class)
                ->restoreForOrder($this->order);
        }

        // Refund if paid
        if ($this->order->paid_at) {
            // Trigger refund via Cashier
            $this->order->refund();
        }

        // Update state
        $this->order->status = new Canceled($this->order);
        $this->order->canceled_at = now();
        $this->order->cancellation_reason = $this->reason;
        $this->order->canceled_by = $this->canceledBy;
        $this->order->save();

        // Dispatch event
        event(new OrderCanceled($this->order));

        return $this->order;
    }
}
```

---

## Usage Examples

### Transitioning an Order

```php
// Simple transition
$order->status->transitionTo(Processing::class);

// Transition with custom class and data
$order->status->transitionTo(Shipped::class, [
    'carrier' => 'jnt',
    'trackingNumber' => 'JNT123456789',
    'items' => $order->items->map(fn($item) => [
        'order_item_id' => $item->id,
        'quantity' => $item->quantity,
    ])->toArray(),
]);
```

### Checking Allowed Transitions

```php
// Get all possible next states
$order->status->transitionableStates();
// → [Processing::class, Canceled::class, Failed::class]

// Check if specific transition is allowed
$order->status->canTransitionTo(Shipped::class);
// → false (must be Processing first)
```

### Querying by State

```php
// Orders pending payment
Order::whereState('status', PendingPayment::class)->get();

// Orders in multiple states
Order::whereState('status', [Processing::class, Shipped::class])->get();

// Orders NOT in completed state
Order::whereNotState('status', Completed::class)->get();
```

### In Filament Actions

```php
Tables\Actions\Action::make('ship')
    ->label('Ship Order')
    ->icon('heroicon-o-truck')
    ->visible(fn (Order $order) => $order->status->canTransitionTo(Shipped::class))
    ->form([
        TextInput::make('tracking_number')->required(),
        Select::make('carrier')->options([...]),
    ])
    ->action(function (Order $order, array $data) {
        $order->status->transitionTo(Shipped::class, $data);
    });
```

---

## State Summary Table

| State | Color | Icon | Can Cancel | Can Edit | Transitions To |
|-------|-------|------|------------|----------|----------------|
| PendingPayment | warning | clock | ✅ | ✅ | Processing, Canceled, Failed |
| Processing | info | cog | ❌ | ❌ | Shipped, OnHold, Fraud, Canceled |
| OnHold | gray | pause | ❌ | ❌ | Processing, Canceled |
| Fraud | danger | exclamation | ❌ | ❌ | — |
| Shipped | primary | truck | ❌ | ❌ | Delivered, Returned |
| Delivered | success | check | ❌ | ❌ | Completed, Returned |
| Returned | warning | arrow-uturn-left | ❌ | ❌ | Refunded |
| Refunded | gray | banknotes | ❌ | ❌ | — |
| Completed | success | check-circle | ❌ | ❌ | — (Final) |
| Canceled | gray | x-circle | ❌ | ❌ | — (Final) |
| Failed | danger | x-mark | ❌ | ❌ | — (Final) |

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-order-structure.md](03-order-structure.md)

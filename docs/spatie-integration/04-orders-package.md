# Orders Package: Spatie Integration Blueprint

> **Package:** `aiarmada/orders`  
> **Status:** Planned (Vision Complete)  
> **Role:** Core Layer - Order Management

---

## ⚠️ Critical Update: Compliance-First Auditing

Based on [fact-based GitHub source code research](00a-audit-vs-activitylog.md), the orders package uses **owen-it/laravel-auditing** instead of spatie/laravel-activitylog for:

| Feature | Benefit for Orders |
|---------|-------------------|
| State Restoration | `transitionTo($audit, $old)` for dispute rollback |
| IP/UA Tracking | Built-in fraud detection |
| Separate old/new values | Compliance-ready audit queries |
| Pivot Auditing | `auditAttach()` for order items |

---

## 📋 Current Vision State

From [orders vision docs](../../packages/orders/docs/vision/):

- Order lifecycle management
- State machine for order status
- Payment integration
- Fulfillment flow
- Immutable order records

**Already Planned:** `spatie/laravel-model-states` for state machine

---

## 🎯 Recommended Spatie Integrations

### Priority 1: laravel-model-states (CRITICAL - Already Planned)

**Impact:** Type-safe, transition-validated order state machine

```json
{
    "require": {
        "spatie/laravel-model-states": "^2.8"
    }
}
```

#### Why Critical

- Orders have complex state transitions
- Invalid transitions must be prevented
- State changes trigger side effects
- Audit trail for state changes
- Query orders by state

#### Complete Integration Blueprint

```php
// orders/src/States/OrderStatus.php

namespace AIArmada\Orders\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class OrderStatus extends State
{
    abstract public function color(): string;
    abstract public function icon(): string;
    abstract public function label(): string;

    public function canCancel(): bool
    {
        return false;
    }

    public function canRefund(): bool
    {
        return false;
    }

    public function canModify(): bool
    {
        return false;
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Created::class)
            // Initial → Payment
            ->allowTransition(Created::class, PendingPayment::class)
            ->allowTransition(PendingPayment::class, Processing::class, PaymentConfirmed::class)
            ->allowTransition(PendingPayment::class, Canceled::class, OrderCanceled::class)
            ->allowTransition(PendingPayment::class, PaymentFailed::class)
            // Processing → Fulfillment
            ->allowTransition(Processing::class, OnHold::class)
            ->allowTransition(Processing::class, Shipped::class, ShipmentCreated::class)
            ->allowTransition(Processing::class, Canceled::class, OrderCanceled::class)
            // Hold management
            ->allowTransition(OnHold::class, Processing::class)
            ->allowTransition(OnHold::class, Canceled::class, OrderCanceled::class)
            // Shipping → Delivery
            ->allowTransition(Shipped::class, Delivered::class, DeliveryConfirmed::class)
            ->allowTransition(Shipped::class, Returned::class)
            // Completion
            ->allowTransition(Delivered::class, Completed::class)
            ->allowTransition(Delivered::class, Returned::class)
            // Returns
            ->allowTransition(Returned::class, Refunded::class, RefundProcessed::class);
    }
}
```

#### State Implementations

```php
// orders/src/States/PendingPayment.php

namespace AIArmada\Orders\States;

class PendingPayment extends OrderStatus
{
    public static string $name = 'pending_payment';

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

    public function canModify(): bool
    {
        return true; // Order can be edited
    }
}
```

```php
// orders/src/States/Processing.php

namespace AIArmada\Orders\States;

class Processing extends OrderStatus
{
    public static string $name = 'processing';

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

```php
// orders/src/States/Completed.php

namespace AIArmada\Orders\States;

class Completed extends OrderStatus
{
    public static string $name = 'completed';
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

#### Transition Classes with Side Effects

```php
// orders/src/Transitions/PaymentConfirmed.php

namespace AIArmada\Orders\Transitions;

use Spatie\ModelStates\Transition;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\Events\OrderPaid;

class PaymentConfirmed extends Transition
{
    public function __construct(
        private Order $order,
        private string $transactionId,
        private string $gateway,
        private array $metadata = [],
    ) {}

    public function handle(): Order
    {
        // Record payment
        $this->order->payments()->create([
            'transaction_id' => $this->transactionId,
            'gateway' => $this->gateway,
            'amount' => $this->order->grand_total,
            'currency' => $this->order->currency,
            'status' => 'completed',
            'metadata' => $this->metadata,
        ]);

        // Deduct inventory (if package present)
        if (class_exists(\AIArmada\Inventory\InventoryDeduction::class)) {
            \AIArmada\Inventory\InventoryDeduction::run($this->order);
        }

        // Attribute affiliate commission (if package present)
        if (class_exists(\AIArmada\Affiliates\AttributeCommission::class)) {
            \AIArmada\Affiliates\AttributeCommission::run($this->order);
        }

        // Update order
        $this->order->status = new Processing($this->order);
        $this->order->paid_at = now();
        $this->order->save();

        // Dispatch event
        event(new OrderPaid($this->order));

        // Log activity
        activity('orders')
            ->performedOn($this->order)
            ->withProperties([
                'transition' => 'payment_confirmed',
                'transaction_id' => $this->transactionId,
                'gateway' => $this->gateway,
            ])
            ->log('Payment confirmed');

        return $this->order;
    }
}
```

```php
// orders/src/Transitions/ShipmentCreated.php

namespace AIArmada\Orders\Transitions;

use Spatie\ModelStates\Transition;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Shipped;
use AIArmada\Orders\Events\OrderShipped;

class ShipmentCreated extends Transition
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
            'status' => 'in_transit',
        ]);

        // Attach shipped items
        foreach ($this->items as $item) {
            $shipment->items()->attach($item['order_item_id'], [
                'quantity' => $item['quantity'],
            ]);
        }

        // Update order state
        $this->order->status = new Shipped($this->order);
        $this->order->shipped_at = now();
        $this->order->save();

        // Notify customer
        $this->order->customer->notify(
            new \AIArmada\Orders\Notifications\OrderShipped($this->order, $shipment)
        );

        // Dispatch event
        event(new OrderShipped($this->order, $shipment));

        return $this->order;
    }
}
```

#### Order Model Integration

```php
// orders/src/Models/Order.php

namespace AIArmada\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\ModelStates\HasStates;
use AIArmada\Orders\States\OrderStatus;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Order extends Model implements AuditableContract
{
    use HasUuids;
    use HasStates;
    use HasCommerceAudit;

    /**
     * Attributes to include in compliance audit.
     */
    protected $auditInclude = [
        'status',
        'grand_total',
        'customer_id',
        'paid_at',
        'shipped_at',
        'canceled_at',
        'cancellation_reason',
    ];

    /**
     * Keep extensive history for orders (compliance).
     */
    protected $auditThreshold = 500;

    protected $casts = [
        'status' => OrderStatus::class,
        'grand_total' => \Akaunting\Money\Casts\MoneyCast::class,
        'subtotal' => \Akaunting\Money\Casts\MoneyCast::class,
        'tax_total' => \Akaunting\Money\Casts\MoneyCast::class,
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * Generate tags for audit categorization.
     */
    public function generateTags(): array
    {
        return [
            'order',
            'commerce',
            $this->status?->value ?? 'unknown',
        ];
    }

    /**
     * Rollback order to state before a dispute date.
     * Uses owen-it/laravel-auditing transitionTo().
     */
    public function rollbackToBeforeDispute(\Carbon\Carbon $disputeDate): bool
    {
        $audit = $this->audits()
            ->where('event', 'updated')
            ->where('created_at', '<', $disputeDate)
            ->latest()
            ->first();

        if ($audit) {
            $this->transitionTo($audit, old: true);
            return $this->save();
        }

        return false;
    }

    /**
     * Get suspicious activity by IP address.
     */
    public function getSuspiciousAudits(): \Illuminate\Support\Collection
    {
        return $this->audits()
            ->whereNotNull('ip_address')
            ->selectRaw('ip_address, COUNT(*) as attempts')
            ->groupBy('ip_address')
            ->having('attempts', '>', 5)
            ->get();
    }

    // Transition helpers
    public function confirmPayment(string $transactionId, string $gateway, array $metadata = []): self
    {
        return $this->status->transitionTo(
            \AIArmada\Orders\States\Processing::class,
            transactionId: $transactionId,
            gateway: $gateway,
            metadata: $metadata,
        );
    }

    public function ship(string $carrier, string $trackingNumber, array $items = []): self
    {
        return $this->status->transitionTo(
            \AIArmada\Orders\States\Shipped::class,
            carrier: $carrier,
            trackingNumber: $trackingNumber,
            items: $items ?: $this->items->map(fn($i) => [
                'order_item_id' => $i->id,
                'quantity' => $i->quantity,
            ])->toArray(),
        );
    }

    public function cancel(string $reason, ?string $canceledBy = null): self
    {
        return $this->status->transitionTo(
            \AIArmada\Orders\States\Canceled::class,
            reason: $reason,
            canceledBy: $canceledBy,
        );
    }
}
```

#### Querying by State

```php
// Find all orders pending payment
Order::whereState('status', PendingPayment::class)->get();

// Find orders in multiple states
Order::whereState('status', [Processing::class, Shipped::class])->get();

// Find orders NOT completed
Order::whereNotState('status', Completed::class)->get();

// With Eloquent scopes
Order::processing()->get();
Order::shipped()->get();
Order::needsAttention()->get();
```

#### Value Delivered

- ✅ Type-safe state transitions
- ✅ Invalid transitions prevented at compile time
- ✅ Transition side effects encapsulated
- ✅ State-based querying
- ✅ Filament-ready state badges
- ✅ Activity log integration

---

### Priority 2: owen-it/laravel-auditing (Inherited from commerce-support)

Orders especially benefit from compliance-grade audit trails with state restoration.

```php
// orders/src/Models/Order.php

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Order extends Model implements AuditableContract
{
    use HasCommerceAudit;

    protected $auditInclude = [
        'status',
        'grand_total',
        'customer_id',
        'paid_at',
        'shipped_at',
        'canceled_at',
        'cancellation_reason',
    ];

    protected $auditThreshold = 500;  // Keep extensive history for compliance
}
```

#### Fraud Detection Query (Built-in IP/UA)

```php
// Find orders from suspicious IPs
use OwenIt\Auditing\Models\Audit;

$suspiciousOrders = Audit::query()
    ->where('auditable_type', Order::class)
    ->where('ip_address', $flaggedIp)
    ->orWhere('user_agent', 'LIKE', '%bot%')
    ->with('auditable')
    ->get()
    ->pluck('auditable')
    ->unique('id');
```

#### Dispute Resolution with State Restoration

```php
// Restore order to pre-dispute state (UNIQUE to owen-it/auditing)
$order = Order::find($orderId);
$audit = $order->audits()
    ->where('created_at', '<', $disputeDate)
    ->where('event', 'updated')
    ->latest()
    ->first();

// Restore the order to its previous state
$order->transitionTo($audit, old: true);
$order->save();

activity('orders')
    ->performedOn($order)
    ->withProperties(['restored_from_audit' => $audit->id])
    ->log('Order restored for dispute resolution');
```

#### Audit Timeline in Filament

```php
// filament-orders/src/Resources/OrderResource/Widgets/OrderAuditTimeline.php

use OwenIt\Auditing\Models\Audit;

class OrderAuditTimeline extends Widget
{
    public Order $record;

    protected function getViewData(): array
    {
        return [
            'audits' => $this->record->audits()
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn(Audit $audit) => [
                    'event' => $audit->event,
                    'user' => $audit->user?->name ?? 'System',
                    'old_values' => $audit->old_values,
                    'new_values' => $audit->new_values,
                    'ip_address' => $audit->ip_address,
                    'user_agent' => $audit->user_agent,
                    'url' => $audit->url,
                    'created_at' => $audit->created_at,
                    'icon' => $this->getAuditIcon($audit),
                    'color' => $this->getAuditColor($audit),
                ]),
        ];
    }

    private function getAuditIcon(Audit $audit): string
    {
        return match($audit->event) {
            'created' => 'heroicon-o-plus',
            'updated' => 'heroicon-o-pencil',
            'deleted' => 'heroicon-o-trash',
            default => 'heroicon-o-clock',
        };
    }

    private function getAuditColor(Audit $audit): string
    {
        return match($audit->event) {
            'created' => 'success',
            'updated' => 'info',
            'deleted' => 'danger',
            default => 'gray',
        };
    }
}
```

#### Value Delivered

- ✅ Compliance-ready audit trail (PCI-DSS, SOC2)
- ✅ Built-in IP/UA/URL tracking for fraud
- ✅ State restoration for dispute resolution
- ✅ Separate old/new columns for easy queries
- ✅ Pivot auditing for order items
- ✅ Queue support for high-volume orders
```

---

### Priority 3: laravel-pdf (For Order Documents)

Already in docs package, but orders should leverage:

```php
// orders/src/Actions/GenerateInvoice.php

namespace AIArmada\Orders\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use AIArmada\Orders\Models\Order;
use Spatie\LaravelPdf\Facades\Pdf;

class GenerateInvoice
{
    use AsAction;

    public function handle(Order $order): string
    {
        $pdf = Pdf::view('orders::pdf.invoice', [
            'order' => $order,
            'items' => $order->items,
            'customer' => $order->customer,
            'billing' => $order->billingAddress,
            'shipping' => $order->shippingAddress,
        ])
            ->format('a4')
            ->margins(10, 10, 10, 10)
            ->name("invoice-{$order->number}.pdf");

        // Store to media library
        $order->addMediaFromStream($pdf->toString())
            ->usingName("Invoice #{$order->number}")
            ->usingFileName("invoice-{$order->number}.pdf")
            ->toMediaCollection('invoices');

        return $pdf->toString();
    }
}
```

---

## 📊 State Machine Complete Reference

### State Diagram

```
                              ┌─────────────┐
                              │   CREATED   │
                              └──────┬──────┘
                                     │
                                     ▼
                           ┌─────────────────┐
         ┌─────────────────│PENDING_PAYMENT  │─────────────────┐
         │                 └────────┬────────┘                 │
         │                          │                          │
         ▼                          ▼                          ▼
  ┌────────────┐           ┌────────────────┐           ┌───────────┐
  │  CANCELED  │           │   PROCESSING   │           │  FAILED   │
  └────────────┘           └───────┬────────┘           └───────────┘
         ▲                         │
         │          ┌──────────────┼──────────────┐
         │          │              │              │
         │          ▼              ▼              ▼
         │   ┌────────────┐  ┌────────────┐  ┌────────────┐
         └───│  ON_HOLD   │  │  SHIPPED   │  │   FRAUD    │
             └─────┬──────┘  └──────┬─────┘  └────────────┘
                   │                │
                   │                ├─────────────────┐
                   ▼                ▼                 ▼
             ┌────────────┐  ┌────────────┐   ┌────────────┐
             │ PROCESSING │  │ DELIVERED  │   │  RETURNED  │
             └────────────┘  └──────┬─────┘   └──────┬─────┘
                                    │                │
                                    ▼                ▼
                             ┌────────────┐   ┌────────────┐
                             │ COMPLETED  │   │  REFUNDED  │
                             └────────────┘   └────────────┘
```

### State Properties

| State | Color | Icon | Can Cancel | Can Refund | Final |
|-------|-------|------|------------|------------|-------|
| Created | gray | plus | ✅ | ❌ | ❌ |
| PendingPayment | warning | clock | ✅ | ❌ | ❌ |
| PaymentFailed | danger | x-mark | ✅ | ❌ | ✅ |
| Processing | info | cog | ✅ | ❌ | ❌ |
| OnHold | gray | pause | ✅ | ❌ | ❌ |
| Fraud | danger | exclamation | ❌ | ❌ | ✅ |
| Shipped | primary | truck | ❌ | ❌ | ❌ |
| Delivered | success | check | ❌ | ✅ | ❌ |
| Returned | warning | arrow-uturn-left | ❌ | ✅ | ❌ |
| Refunded | gray | banknotes | ❌ | ❌ | ✅ |
| Completed | success | check-circle | ❌ | ✅ | ✅ |
| Canceled | gray | x-circle | ❌ | ❌ | ✅ |

---

## 📦 Full composer.json Blueprint

```json
{
    "name": "aiarmada/orders",
    "description": "Order management for AIArmada Commerce.",
    "require": {
        "php": "^8.4",
        "aiarmada/commerce-support": "^1.0",
        "spatie/laravel-model-states": "^2.8"
    },
    "suggest": {
        "aiarmada/inventory": "For automatic inventory deduction on payment",
        "aiarmada/affiliates": "For affiliate commission attribution",
        "aiarmada/cashier": "For payment processing",
        "aiarmada/shipping": "For fulfillment integration",
        "aiarmada/docs": "For invoice/receipt generation"
    }
}
```

---

## ✅ Implementation Checklist

### Phase 1: State Machine Core

- [ ] Install spatie/laravel-model-states
- [ ] Create OrderStatus abstract class
- [ ] Create all state classes
- [ ] Configure allowed transitions
- [ ] Add HasStates to Order model

### Phase 2: Transition Classes

- [ ] Create PaymentConfirmed transition
- [ ] Create ShipmentCreated transition
- [ ] Create DeliveryConfirmed transition
- [ ] Create OrderCanceled transition
- [ ] Create RefundProcessed transition

### Phase 3: Compliance Audit Integration (owen-it/auditing)

- [ ] Add HasCommerceAudit to Order
- [ ] Configure auditInclude attributes
- [ ] Set auditThreshold for retention
- [ ] Implement rollbackToBeforeDispute()
- [ ] Add fraud detection queries
- [ ] Create Filament audit timeline widget

### Phase 4: Filament UI

- [ ] State badge column
- [ ] Transition action buttons
- [ ] Audit timeline widget (replaces activity timeline)
- [ ] State filter
- [ ] IP/UA display in audit details

---

## 🔗 Related Documents

- [00-overview.md](00-overview.md) - Master overview
- [00a-audit-vs-activitylog.md](00a-audit-vs-activitylog.md) - **Package comparison research**
- [01-commerce-support.md](01-commerce-support.md) - Hybrid audit foundation
- [08-payment-packages.md](08-payment-packages.md) - Payment transitions
- [09-shipping-packages.md](09-shipping-packages.md) - Shipping transitions

---

*This blueprint was created by the Visionary Chief Architect.*

# Cross-Package Integration

> **Document:** 06 of 08  
> **Package:** `aiarmada/orders`  
> **Status:** Vision

---

## Overview

Orders serve as the central transaction hub, integrating with multiple packages through events and service contracts.

---

## Integration Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    ORDERS INTEGRATION ARCHITECTURE                           │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│                              ┌─────────────┐                                 │
│                              │   ORDERS    │                                 │
│                              │  (Central)  │                                 │
│                              └──────┬──────┘                                 │
│                                     │                                        │
│     ┌───────────────────────────────┼───────────────────────────────┐       │
│     │              │                │                │              │       │
│     ▼              ▼                ▼                ▼              ▼       │
│ ┌────────┐   ┌──────────┐   ┌────────────┐   ┌──────────┐   ┌──────────┐   │
│ │ CART   │   │ CASHIER  │   │ INVENTORY  │   │ SHIPPING │   │CUSTOMERS │   │
│ │Checkout│   │ Payment  │   │  Deduct    │   │ Fulfill  │   │  CRM     │   │
│ └────────┘   └──────────┘   └────────────┘   └──────────┘   └──────────┘   │
│                                                                              │
│ ┌────────┐   ┌──────────┐   ┌────────────┐   ┌──────────┐                   │
│ │VOUCHERS│   │AFFILIATES│   │    TAX     │   │ PRICING  │                   │
│ │Discount│   │Commission│   │  Snapshot  │   │ Snapshot │                   │
│ └────────┘   └──────────┘   └────────────┘   └──────────┘                   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Events Dispatched

| Event | When | Payload |
|-------|------|---------|
| `OrderCreated` | Order created from cart | Order, Customer |
| `OrderPaid` | Payment confirmed | Order, Payment |
| `OrderShipped` | All items shipped | Order, Shipments |
| `OrderDelivered` | Delivery confirmed | Order |
| `OrderCompleted` | Order finalized | Order |
| `OrderCanceled` | Order canceled | Order, Reason |
| `OrderRefunded` | Refund processed | Order, Refund |

---

## Events Listened

| Event | From Package | Action |
|-------|--------------|--------|
| `CartCheckedOut` | cart | Create Order from Cart |
| `PaymentCompleted` | cashier | Record payment, transition to Processing |
| `PaymentFailed` | cashier | Transition to Failed |
| `ShipmentDelivered` | shipping | Check if all delivered, transition to Delivered |
| `RefundProcessed` | cashier | Update refund records |

---

## Event Listeners

### CartCheckedOut Listener

```php
namespace AIArmada\Orders\Listeners;

class CreateOrderFromCart
{
    public function __construct(
        private OrderFactory $orderFactory
    ) {}

    public function handle(CartCheckedOut $event): void
    {
        $this->orderFactory->createFromCart(
            cart: $event->cart,
            billingAddress: $event->billingAddress,
            shippingAddress: $event->shippingAddress,
            customer: $event->customer,
        );
    }
}
```

### PaymentCompleted Listener

```php
namespace AIArmada\Orders\Listeners;

class RecordPaymentOnOrder
{
    public function __construct(
        private PaymentRecorder $paymentRecorder
    ) {}

    public function handle(PaymentCompleted $event): void
    {
        $order = Order::where('id', $event->metadata['order_id'])->first();

        if (!$order) {
            return;
        }

        $this->paymentRecorder->recordPayment(
            order: $order,
            gateway: $event->gateway,
            transactionId: $event->transactionId,
            amount: $event->amount,
            status: 'completed',
            metadata: $event->metadata,
        );
    }
}
```

---

## Service Contracts

### Orderable Interface

For models that can have orders (Customer):

```php
namespace AIArmada\CommerceSupport\Contracts;

interface Orderable
{
    public function orders(): HasMany;
    public function getOrderCount(): int;
    public function getTotalSpent(): Money;
    public function getLastOrder(): ?Order;
}
```

### Fulfillable Interface

For order items that can be fulfilled:

```php
namespace AIArmada\CommerceSupport\Contracts;

interface Fulfillable
{
    public function getWeight(): float;
    public function getDimensions(): array;
    public function requiresShipping(): bool;
    public function isDigital(): bool;
}
```

---

## Auto-Discovery Pattern

```php
namespace AIArmada\Orders;

class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Auto-discover integrations
        $this->registerIntegrations();
    }

    protected function registerIntegrations(): void
    {
        // Cart integration
        if (class_exists(\AIArmada\Cart\CartServiceProvider::class)) {
            Event::listen(CartCheckedOut::class, CreateOrderFromCart::class);
        }

        // Cashier integration
        if (class_exists(\AIArmada\Cashier\CashierServiceProvider::class)) {
            Event::listen(PaymentCompleted::class, RecordPaymentOnOrder::class);
            Event::listen(PaymentFailed::class, MarkOrderFailed::class);
        }

        // Inventory integration
        if (class_exists(\AIArmada\Inventory\InventoryServiceProvider::class)) {
            Event::listen(OrderPaid::class, DeductInventory::class);
            Event::listen(OrderCanceled::class, RestoreInventory::class);
        }

        // Shipping integration
        if (class_exists(\AIArmada\Shipping\ShippingServiceProvider::class)) {
            Event::listen(ShipmentDelivered::class, CheckOrderDelivery::class);
        }

        // Affiliates integration
        if (class_exists(\AIArmada\Affiliates\AffiliatesServiceProvider::class)) {
            Event::listen(OrderCompleted::class, AttributeCommission::class);
        }
    }
}
```

---

## Navigation

**Previous:** [05-fulfillment-flow.md](05-fulfillment-flow.md)  
**Next:** [07-database-schema.md](07-database-schema.md)

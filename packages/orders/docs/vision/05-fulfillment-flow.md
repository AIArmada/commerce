# Fulfillment Flow

> **Document:** 05 of 08  
> **Package:** `aiarmada/orders`  
> **Status:** Vision

---

## Overview

Orders integrate with the Shipping package for fulfillment. An order can have multiple shipments (split shipments), and each shipment has its own state machine.

---

## Fulfillment Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         FULFILLMENT FLOW                                     │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   Order (Processing)                              Shipping Package           │
│        │                                               │                     │
│        │  1. Create Shipment                           │                     │
│        │──────────────────────────────────────────────▶│                     │
│        │                                               │                     │
│        │                                 2. Generate Label                   │
│        │                                 3. Carrier Pickup                   │
│        │                                               │                     │
│        │  4. Shipment Shipped Event                    │                     │
│        │◀──────────────────────────────────────────────│                     │
│        │                                               │                     │
│        │  5. Check Fulfillment                         │                     │
│        │  (All items shipped?)                         │                     │
│        │                                               │                     │
│        │  6. Transition to Shipped                     │                     │
│        │  (if fully fulfilled)                         │                     │
│        │                                               │                     │
│        │                                 7. Carrier Tracking                 │
│        │                                               │                     │
│        │  8. Delivery Confirmed Event                  │                     │
│        │◀──────────────────────────────────────────────│                     │
│        │                                               │                     │
│        │  9. Transition to Delivered                   │                     │
│        │                                               │                     │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Creating a Shipment

```php
namespace AIArmada\Orders\Services;

class FulfillmentService
{
    public function createShipment(
        Order $order,
        string $carrier,
        array $items,
        ?Address $shippingAddress = null
    ): Shipment {
        // Validate items are from this order and not already fulfilled
        $this->validateFulfillableItems($order, $items);

        // Use order shipping address if not provided
        $shippingAddress ??= $order->shippingAddress;

        // Create shipment via Shipping package
        $shipment = app(ShippingService::class)->createShipment([
            'order_id' => $order->id,
            'carrier' => $carrier,
            'recipient' => [
                'name' => $shippingAddress->getFullName(),
                'phone' => $shippingAddress->phone,
                'address' => $shippingAddress->toArray(),
            ],
            'items' => $this->mapItemsForShipment($items),
        ]);

        // Update fulfilled quantities
        foreach ($items as $item) {
            $orderItem = $order->items()->find($item['order_item_id']);
            $orderItem->increment('fulfilled_quantity', $item['quantity']);
        }

        // Record history
        $order->history()->create([
            'event' => OrderEvent::ShipmentCreated,
            'description' => "Shipment created with carrier: {$carrier}",
            'metadata' => ['shipment_id' => $shipment->id],
        ]);

        // Check if order is fully fulfilled
        if ($this->isFullyFulfilled($order)) {
            $order->status->transitionTo(Shipped::class);
        }

        return $shipment;
    }

    protected function isFullyFulfilled(Order $order): bool
    {
        return $order->items->every(fn ($item) => $item->isFullyFulfilled());
    }
}
```

---

## Split Shipments

Orders can be split across multiple shipments:

```
┌────────────────────────────────────────────────────────────────┐
│ ORDER #10234                                                    │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Items:                                                          │
│ ├── Product A (qty: 2)  →  Shipment #1 (qty: 2) ✓ Delivered    │
│ ├── Product B (qty: 3)  →  Shipment #1 (qty: 3) ✓ Delivered    │
│ └── Product C (qty: 1)  →  Shipment #2 (qty: 1) 🚚 In Transit  │
│                                                                 │
│ Status: Partially Shipped                                       │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

---

## Fulfillment Status Tracking

```php
class Order extends Model
{
    public function getFulfillmentStatus(): FulfillmentStatus
    {
        $itemsWithShipment = $this->items->sum('fulfilled_quantity');
        $totalItems = $this->items->sum('quantity');

        if ($itemsWithShipment === 0) {
            return FulfillmentStatus::Unfulfilled;
        }

        if ($itemsWithShipment < $totalItems) {
            return FulfillmentStatus::PartiallyFulfilled;
        }

        // Check if all shipments are delivered
        $allDelivered = $this->shipments->every(
            fn ($s) => $s->status instanceof Delivered
        );

        return $allDelivered
            ? FulfillmentStatus::Delivered
            : FulfillmentStatus::Shipped;
    }
}
```

---

## Navigation

**Previous:** [04-payment-integration.md](04-payment-integration.md)  
**Next:** [06-integration.md](06-integration.md)

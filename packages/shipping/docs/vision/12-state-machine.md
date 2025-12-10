# Shipment State Machine Architecture

> **Document:** 12 of 12  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision (Future Enhancement)  
> **Implementation:** `spatie/laravel-model-states` v2

---

## Overview

The Shipping package uses **Spatie Laravel Model States** to manage:
1. **Shipment Status** - Lifecycle of a shipment from creation to delivery
2. **Return Authorization Status** - Lifecycle of return/RMA requests

---

## Shipment State Machine

### State Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         SHIPMENT STATE MACHINE                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│                           ┌─────────────┐                                    │
│                           │   PENDING   │                                    │
│                           │  (Initial)  │                                    │
│                           └──────┬──────┘                                    │
│                                  │                                           │
│                    ┌─────────────┼─────────────┐                            │
│                    │             │             │                            │
│                    ▼             ▼             ▼                            │
│            ┌────────────┐ ┌────────────┐ ┌────────────┐                     │
│            │  CANCELED  │ │ PROCESSING │ │   FAILED   │                     │
│            └────────────┘ └──────┬─────┘ └────────────┘                     │
│                                  │                                           │
│                                  ▼                                           │
│                           ┌────────────┐                                    │
│                           │  LABELED   │                                    │
│                           └──────┬─────┘                                    │
│                                  │                                           │
│                                  ▼                                           │
│                           ┌────────────┐                                    │
│                           │ PICKED_UP  │                                    │
│                           └──────┬─────┘                                    │
│                                  │                                           │
│                                  ▼                                           │
│                           ┌────────────┐                                    │
│                           │ IN_TRANSIT │                                    │
│                           └──────┬─────┘                                    │
│                                  │                                           │
│                    ┌─────────────┼─────────────┐                            │
│                    │             │             │                            │
│                    ▼             ▼             ▼                            │
│            ┌────────────┐ ┌────────────┐ ┌────────────┐                     │
│            │ OUT_DELIV  │ │ EXCEPTION  │ │  ON_HOLD   │                     │
│            └──────┬─────┘ └────────────┘ └──────┬─────┘                     │
│                   │                             │                            │
│                   ▼                             │                            │
│            ┌────────────┐                       │                            │
│            │ DELIVERED  │◄──────────────────────┘                            │
│            │  (Final)   │                                                    │
│            └────────────┘                                                    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Shipment States

```php
namespace AIArmada\Shipping\States\Shipment;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ShipmentStatus extends State
{
    abstract public function color(): string;
    abstract public function icon(): string;
    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            // Creation flow
            ->allowTransition(Pending::class, Processing::class)
            ->allowTransition(Pending::class, Canceled::class)
            ->allowTransition(Pending::class, Failed::class)
            // Label generation
            ->allowTransition(Processing::class, Labeled::class, LabelGeneratedTransition::class)
            // Pickup
            ->allowTransition(Labeled::class, PickedUp::class, PickupRecordedTransition::class)
            // Transit
            ->allowTransition(PickedUp::class, InTransit::class)
            ->allowTransition(InTransit::class, OutForDelivery::class)
            ->allowTransition(InTransit::class, Exception::class)
            ->allowTransition(InTransit::class, OnHold::class)
            // Delivery
            ->allowTransition(OutForDelivery::class, Delivered::class, DeliveryConfirmedTransition::class)
            // Recovery
            ->allowTransition(OnHold::class, InTransit::class)
            ->allowTransition(OnHold::class, Delivered::class);
    }
}
```

### State Classes

| State | Color | Icon | Description |
|-------|-------|------|-------------|
| `Pending` | gray | clock | Shipment created, awaiting processing |
| `Processing` | info | cog | Being prepared for shipping |
| `Labeled` | primary | document | Label generated, ready for pickup |
| `PickedUp` | primary | truck | Carrier has collected package |
| `InTransit` | info | arrow-right | In carrier network |
| `OutForDelivery` | warning | map-pin | Out for final delivery |
| `Delivered` | success | check-circle | Successfully delivered |
| `Exception` | danger | exclamation | Delivery problem |
| `OnHold` | gray | pause | Temporarily held |
| `Canceled` | gray | x-circle | Shipment canceled |
| `Failed` | danger | x-mark | Shipment failed |

---

## Return Authorization State Machine

### State Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    RETURN AUTHORIZATION STATE MACHINE                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│                           ┌─────────────┐                                    │
│                           │  REQUESTED  │                                    │
│                           │  (Initial)  │                                    │
│                           └──────┬──────┘                                    │
│                                  │                                           │
│                    ┌─────────────┼─────────────┐                            │
│                    │             │             │                            │
│                    ▼             ▼             ▼                            │
│            ┌────────────┐ ┌────────────┐ ┌────────────┐                     │
│            │  REJECTED  │ │  APPROVED  │ │  EXPIRED   │                     │
│            │  (Final)   │ └──────┬─────┘ │  (Final)   │                     │
│            └────────────┘        │       └────────────┘                     │
│                                  │                                           │
│                                  ▼                                           │
│                           ┌────────────┐                                    │
│                           │ LABEL_SENT │                                    │
│                           └──────┬─────┘                                    │
│                                  │                                           │
│                                  ▼                                           │
│                           ┌────────────┐                                    │
│                           │ IN_TRANSIT │                                    │
│                           └──────┬─────┘                                    │
│                                  │                                           │
│                                  ▼                                           │
│                           ┌────────────┐                                    │
│                           │  RECEIVED  │                                    │
│                           └──────┬─────┘                                    │
│                                  │                                           │
│                    ┌─────────────┼─────────────┐                            │
│                    │             │             │                            │
│                    ▼             ▼             ▼                            │
│            ┌────────────┐ ┌────────────┐ ┌────────────┐                     │
│            │ INSPECTING │ │  REFUNDED  │ │ EXCHANGED  │                     │
│            └──────┬─────┘ │  (Final)   │ │  (Final)   │                     │
│                   │       └────────────┘ └────────────┘                     │
│                   │                                                          │
│                   ▼                                                          │
│            ┌────────────┐                                                   │
│            │  COMPLETE  │                                                   │
│            │  (Final)   │                                                   │
│            └────────────┘                                                   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Return Authorization States

```php
namespace AIArmada\Shipping\States\ReturnAuthorization;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ReturnStatus extends State
{
    abstract public function color(): string;
    abstract public function icon(): string;
    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Requested::class)
            // Approval flow
            ->allowTransition(Requested::class, Approved::class, ReturnApprovedTransition::class)
            ->allowTransition(Requested::class, Rejected::class, ReturnRejectedTransition::class)
            ->allowTransition(Requested::class, Expired::class)
            // Return label
            ->allowTransition(Approved::class, LabelSent::class, ReturnLabelSentTransition::class)
            // Return shipping
            ->allowTransition(LabelSent::class, InTransit::class)
            ->allowTransition(InTransit::class, Received::class, ReturnReceivedTransition::class)
            // Inspection
            ->allowTransition(Received::class, Inspecting::class)
            ->allowTransition(Received::class, Refunded::class, RefundIssuedTransition::class)
            ->allowTransition(Received::class, Exchanged::class, ExchangeProcessedTransition::class)
            // Completion
            ->allowTransition(Inspecting::class, Complete::class)
            ->allowTransition(Inspecting::class, Refunded::class, RefundIssuedTransition::class)
            ->allowTransition(Inspecting::class, Exchanged::class, ExchangeProcessedTransition::class);
    }
}
```

### Return State Classes

| State | Color | Icon | Description |
|-------|-------|------|-------------|
| `Requested` | warning | arrow-uturn-left | Customer requested return |
| `Approved` | success | check | Return approved |
| `Rejected` | danger | x-mark | Return rejected |
| `Expired` | gray | clock | Return window expired |
| `LabelSent` | info | document | Return label sent to customer |
| `InTransit` | info | truck | Items being returned |
| `Received` | primary | inbox | Items received at warehouse |
| `Inspecting` | warning | magnifying-glass | Items under inspection |
| `Refunded` | success | banknotes | Refund processed |
| `Exchanged` | success | arrows-right-left | Exchange shipped |
| `Complete` | success | check-circle | Return process complete |

---

## Transition Classes

### LabelGeneratedTransition

```php
namespace AIArmada\Shipping\Transitions;

use Spatie\ModelStates\Transition;

class LabelGeneratedTransition extends Transition
{
    public function __construct(
        private Shipment $shipment,
        private string $labelUrl,
        private string $trackingNumber,
    ) {}

    public function handle(): Shipment
    {
        // Create label record
        $this->shipment->labels()->create([
            'url' => $this->labelUrl,
            'tracking_number' => $this->trackingNumber,
            'generated_at' => now(),
        ]);

        // Update shipment
        $this->shipment->tracking_number = $this->trackingNumber;
        $this->shipment->status = new Labeled($this->shipment);
        $this->shipment->save();

        // Dispatch event
        event(new ShipmentLabelGenerated($this->shipment));

        return $this->shipment;
    }
}
```

### DeliveryConfirmedTransition

```php
namespace AIArmada\Shipping\Transitions;

use Spatie\ModelStates\Transition;
use AIArmada\Shipping\Events\ShipmentDelivered;

class DeliveryConfirmedTransition extends Transition
{
    public function __construct(
        private Shipment $shipment,
        private ?string $signedBy = null,
        private ?string $proofOfDelivery = null,
    ) {}

    public function handle(): Shipment
    {
        // Update shipment
        $this->shipment->status = new Delivered($this->shipment);
        $this->shipment->delivered_at = now();
        $this->shipment->signed_by = $this->signedBy;
        $this->shipment->proof_of_delivery = $this->proofOfDelivery;
        $this->shipment->save();

        // Notify customer
        $this->shipment->order->customer->notify(
            new ShipmentDeliveredNotification($this->shipment)
        );

        // Update order status (if Orders package present)
        if (class_exists(\AIArmada\Orders\Models\Order::class)) {
            $this->shipment->order->status->transitionTo(
                \AIArmada\Orders\States\Delivered::class
            );
        }

        // Dispatch event
        event(new ShipmentDelivered($this->shipment));

        return $this->shipment;
    }
}
```

---

## Integration with Carriers (J&T, etc.)

State transitions can be triggered by webhook events from carriers:

```php
// In JntWebhookController
public function handle(Request $request)
{
    $shipment = Shipment::where('tracking_number', $request->tracking_number)->first();
    
    match ($request->status_code) {
        'PICKED_UP' => $shipment->status->transitionTo(PickedUp::class),
        'IN_TRANSIT' => $shipment->status->transitionTo(InTransit::class),
        'OUT_FOR_DELIVERY' => $shipment->status->transitionTo(OutForDelivery::class),
        'DELIVERED' => $shipment->status->transitionTo(Delivered::class, [
            'signedBy' => $request->signed_by,
        ]),
        'EXCEPTION' => $shipment->status->transitionTo(Exception::class),
    };
}
```

---

## Navigation

**Previous:** [11-implementation-roadmap.md](11-implementation-roadmap.md)  
**Back to:** [PROGRESS.md](PROGRESS.md)

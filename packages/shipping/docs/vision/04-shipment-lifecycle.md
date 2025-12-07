# Shipping Vision: Shipment Lifecycle

> **Document:** 04 of 11  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision Document  
> **Last Updated:** December 7, 2025

---

## Overview

The Shipment Lifecycle defines the complete journey of a shipment from creation to delivery (or return). This document outlines the `Shipment` model, status workflow, label generation, and multi-package handling.

---

## Shipment Model

### Model Structure

```php
<?php

namespace AIArmada\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\CommerceSupport\Traits\HasOwner;

class Shipment extends Model
{
    use SoftDeletes, HasOwner;

    protected $table = 'shipments';

    protected $fillable = [
        'owner_id',
        'owner_type',
        'reference', // Internal reference (order ID, etc.)
        'carrier_code',
        'service_code',
        'tracking_number',
        'carrier_reference',
        'status',
        'origin_address',
        'destination_address',
        'package_count',
        'total_weight',
        'declared_value',
        'currency',
        'shipping_cost',
        'insurance_cost',
        'label_url',
        'label_format',
        'shipped_at',
        'estimated_delivery_at',
        'delivered_at',
        'metadata',
    ];

    protected $casts = [
        'status' => ShipmentStatus::class,
        'origin_address' => 'array',
        'destination_address' => 'array',
        'package_count' => 'integer',
        'total_weight' => 'integer',
        'declared_value' => 'integer',
        'shipping_cost' => 'integer',
        'insurance_cost' => 'integer',
        'shipped_at' => 'datetime',
        'estimated_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'metadata' => 'array',
    ];

    // ─────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ShipmentPackage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('occurred_at', 'desc');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(ShipmentLabel::class);
    }

    public function returnAuthorizations(): HasMany
    {
        return $this->hasMany(ReturnAuthorization::class);
    }

    /**
     * Polymorphic relationship to the "shippable" (order, cart, etc.)
     */
    public function shippable(): MorphTo
    {
        return $this->morphTo();
    }

    // ─────────────────────────────────────────────────────────────
    // STATUS HELPERS
    // ─────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === ShipmentStatus::Pending 
            || $this->status === ShipmentStatus::Draft;
    }

    public function isInTransit(): bool
    {
        return in_array($this->status, [
            ShipmentStatus::Shipped,
            ShipmentStatus::InTransit,
            ShipmentStatus::OutForDelivery,
        ]);
    }

    public function isDelivered(): bool
    {
        return $this->status === ShipmentStatus::Delivered;
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [
            ShipmentStatus::Draft,
            ShipmentStatus::Pending,
        ]);
    }

    public function getLatestEvent(): ?ShipmentEvent
    {
        return $this->events()->latest('occurred_at')->first();
    }
}
```

### ShipmentItem Model

```php
<?php

namespace AIArmada\Shipping\Models;

class ShipmentItem extends Model
{
    protected $fillable = [
        'shipment_id',
        'shippable_item_id', // Polymorphic
        'shippable_item_type',
        'sku',
        'name',
        'quantity',
        'weight',
        'declared_value',
        'metadata',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function shippableItem(): MorphTo
    {
        return $this->morphTo();
    }
}
```

### ShipmentPackage Model

For multi-package shipments:

```php
<?php

namespace AIArmada\Shipping\Models;

class ShipmentPackage extends Model
{
    protected $fillable = [
        'shipment_id',
        'package_number',
        'tracking_number',
        'weight',
        'length',
        'width', 
        'height',
        'packaging_type',
        'label_url',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentPackageItem::class);
    }
}
```

---

## Status Workflow

### ShipmentStatus Enum

```php
<?php

namespace AIArmada\Shipping\Enums;

enum ShipmentStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case AwaitingPickup = 'awaiting_pickup';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case DeliveryFailed = 'delivery_failed';
    case ReturnToSender = 'return_to_sender';
    case Cancelled = 'cancelled';
    case OnHold = 'on_hold';
    case Exception = 'exception';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::AwaitingPickup => 'Awaiting Pickup',
            self::Shipped => 'Shipped',
            self::InTransit => 'In Transit',
            self::OutForDelivery => 'Out for Delivery',
            self::Delivered => 'Delivered',
            self::DeliveryFailed => 'Delivery Failed',
            self::ReturnToSender => 'Return to Sender',
            self::Cancelled => 'Cancelled',
            self::OnHold => 'On Hold',
            self::Exception => 'Exception',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Pending => 'yellow',
            self::AwaitingPickup => 'blue',
            self::Shipped => 'indigo',
            self::InTransit => 'blue',
            self::OutForDelivery => 'cyan',
            self::Delivered => 'green',
            self::DeliveryFailed => 'red',
            self::ReturnToSender => 'orange',
            self::Cancelled => 'gray',
            self::OnHold => 'yellow',
            self::Exception => 'red',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-document',
            self::Pending => 'heroicon-o-clock',
            self::AwaitingPickup => 'heroicon-o-truck',
            self::Shipped => 'heroicon-o-paper-airplane',
            self::InTransit => 'heroicon-o-truck',
            self::OutForDelivery => 'heroicon-o-map-pin',
            self::Delivered => 'heroicon-o-check-circle',
            self::DeliveryFailed => 'heroicon-o-x-circle',
            self::ReturnToSender => 'heroicon-o-arrow-uturn-left',
            self::Cancelled => 'heroicon-o-x-mark',
            self::OnHold => 'heroicon-o-pause-circle',
            self::Exception => 'heroicon-o-exclamation-triangle',
        };
    }

    /**
     * Get valid transitions from this status.
     */
    public function getAllowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending, self::Cancelled],
            self::Pending => [self::AwaitingPickup, self::Shipped, self::Cancelled],
            self::AwaitingPickup => [self::Shipped, self::Cancelled],
            self::Shipped => [self::InTransit, self::Exception],
            self::InTransit => [self::OutForDelivery, self::Delivered, self::DeliveryFailed, self::Exception, self::OnHold],
            self::OutForDelivery => [self::Delivered, self::DeliveryFailed, self::Exception],
            self::DeliveryFailed => [self::InTransit, self::OutForDelivery, self::ReturnToSender],
            self::Exception => [self::InTransit, self::OnHold, self::ReturnToSender],
            self::OnHold => [self::InTransit, self::Cancelled],
            default => [],
        };
    }
}
```

### Status Workflow Diagram

```
                     ┌───────────────┐
                     │    Draft      │
                     └───────┬───────┘
                             │
                             ▼
                     ┌───────────────┐
     ┌──────────────►│   Pending     │◄──────────────┐
     │               └───────┬───────┘               │
     │                       │                       │
     │                       ▼                       │
     │               ┌───────────────┐               │
     │               │AwaitingPickup │               │
     │               └───────┬───────┘               │
     │                       │                       │
     │                       ▼                       │
     │               ┌───────────────┐               │
     │               │    Shipped    │               │
     │               └───────┬───────┘               │
     │                       │                       │
     │                       ▼                       │
     │               ┌───────────────┐      ┌───────────────┐
     │               │   InTransit   │─────►│   Exception   │
     │               └───────┬───────┘      └───────┬───────┘
     │                       │                      │
     │                       ▼                      │
     │               ┌───────────────┐              │
     │               │OutForDelivery │              │
     │               └───────┬───────┘              │
     │                       │                      │
     │    ┌──────────────────┼──────────────────┐   │
     │    │                  │                  │   │
     │    ▼                  ▼                  ▼   │
┌─────────────┐      ┌───────────────┐      ┌──────▼────────┐
│ DeliveryFail│      │   Delivered   │      │ReturnToSender │
└─────────────┘      └───────────────┘      └───────────────┘
                            ✓
```

---

## ShipmentService

### Core Service

```php
<?php

namespace AIArmada\Shipping\Services;

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Data\CreateShipmentData;
use AIArmada\Shipping\ShippingManager;
use AIArmada\Shipping\Events\ShipmentCreated;
use AIArmada\Shipping\Events\ShipmentStatusChanged;
use AIArmada\Shipping\Events\ShipmentShipped;
use AIArmada\Shipping\Events\ShipmentDelivered;

class ShipmentService
{
    public function __construct(
        private readonly ShippingManager $shippingManager
    ) {}

    /**
     * Create a new shipment.
     */
    public function create(CreateShipmentData $data): Shipment
    {
        $shipment = Shipment::create([
            'owner_id' => $data->ownerId,
            'owner_type' => $data->ownerType,
            'reference' => $data->reference,
            'carrier_code' => $data->carrierCode,
            'service_code' => $data->serviceCode,
            'status' => ShipmentStatus::Draft,
            'origin_address' => $data->origin->toArray(),
            'destination_address' => $data->destination->toArray(),
            'total_weight' => $data->totalWeight,
            'declared_value' => $data->declaredValue,
            'currency' => $data->currency ?? 'MYR',
            'metadata' => $data->metadata,
        ]);

        // Create items
        foreach ($data->items as $item) {
            $shipment->items()->create($item->toArray());
        }

        event(new ShipmentCreated($shipment));

        return $shipment;
    }

    /**
     * Ship the shipment (create with carrier).
     */
    public function ship(Shipment $shipment): Shipment
    {
        if (! $shipment->isPending()) {
            throw new ShipmentAlreadyShippedException($shipment);
        }

        $driver = $this->shippingManager->driver($shipment->carrier_code);

        $result = $driver->createShipment(
            ShipmentData::fromModel($shipment)
        );

        if (! $result->success) {
            throw new ShipmentCreationFailedException($result->error);
        }

        $shipment->update([
            'tracking_number' => $result->trackingNumber,
            'carrier_reference' => $result->carrierReference,
            'status' => ShipmentStatus::Shipped,
            'shipped_at' => now(),
        ]);

        // Generate label if supported
        if ($driver->supports(DriverCapability::LabelGeneration->value)) {
            $this->generateLabel($shipment);
        }

        $this->recordEvent($shipment, 'shipped', 'Shipment created with carrier');
        event(new ShipmentShipped($shipment));

        return $shipment->refresh();
    }

    /**
     * Update shipment status.
     */
    public function updateStatus(
        Shipment $shipment,
        ShipmentStatus $newStatus,
        ?string $note = null,
        ?array $eventData = null
    ): Shipment {
        $oldStatus = $shipment->status;

        if (! in_array($newStatus, $oldStatus->getAllowedTransitions())) {
            throw new InvalidStatusTransitionException($oldStatus, $newStatus);
        }

        $shipment->update(['status' => $newStatus]);

        $this->recordEvent($shipment, 'status_changed', $note, [
            'from_status' => $oldStatus->value,
            'to_status' => $newStatus->value,
            ...$eventData ?? [],
        ]);

        event(new ShipmentStatusChanged($shipment, $oldStatus, $newStatus));

        if ($newStatus === ShipmentStatus::Delivered) {
            $shipment->update(['delivered_at' => now()]);
            event(new ShipmentDelivered($shipment));
        }

        return $shipment;
    }

    /**
     * Cancel a shipment.
     */
    public function cancel(Shipment $shipment, ?string $reason = null): Shipment
    {
        if (! $shipment->isCancellable()) {
            throw new ShipmentNotCancellableException($shipment);
        }

        // If already submitted to carrier, cancel there too
        if ($shipment->tracking_number) {
            $driver = $this->shippingManager->driver($shipment->carrier_code);
            $driver->cancelShipment($shipment->tracking_number);
        }

        $shipment->update(['status' => ShipmentStatus::Cancelled]);

        $this->recordEvent($shipment, 'cancelled', $reason);
        event(new ShipmentCancelled($shipment, $reason));

        return $shipment;
    }

    /**
     * Generate shipping label.
     */
    public function generateLabel(Shipment $shipment, array $options = []): ShipmentLabel
    {
        $driver = $this->shippingManager->driver($shipment->carrier_code);

        $labelData = $driver->generateLabel($shipment->tracking_number, $options);

        $label = $shipment->labels()->create([
            'format' => $labelData->format,
            'size' => $labelData->size,
            'url' => $labelData->url,
            'content' => $labelData->content,
            'generated_at' => now(),
        ]);

        $shipment->update(['label_url' => $labelData->url]);

        return $label;
    }

    protected function recordEvent(
        Shipment $shipment,
        string $type,
        ?string $note = null,
        array $data = []
    ): ShipmentEvent {
        return $shipment->events()->create([
            'type' => $type,
            'status' => $shipment->status->value,
            'note' => $note,
            'data' => $data,
            'occurred_at' => now(),
        ]);
    }
}
```

---

## Order-to-Shipment Flow

### Creating Shipment from Order

```php
<?php

namespace AIArmada\Shipping\Actions;

class CreateShipmentFromOrder
{
    public function __construct(
        private readonly ShipmentService $shipmentService
    ) {}

    public function execute(Order $order, ?string $carrierCode = null): Shipment
    {
        $carrierCode ??= config('shipping.default');

        $data = new CreateShipmentData(
            ownerId: $order->owner_id,
            ownerType: $order->owner_type,
            reference: $order->order_number,
            carrierCode: $carrierCode,
            serviceCode: $order->selected_shipping_method['service'] ?? 'standard',
            origin: $this->getOriginAddress($order),
            destination: AddressData::from($order->shipping_address),
            items: $this->mapOrderItems($order),
            totalWeight: $order->total_weight,
            declaredValue: $order->subtotal,
            metadata: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        );

        $shipment = $this->shipmentService->create($data);

        // Associate shipment with order
        $order->shipments()->attach($shipment);

        return $shipment;
    }

    protected function mapOrderItems(Order $order): array
    {
        return $order->items->map(fn ($item) => new ShipmentItemData(
            sku: $item->sku,
            name: $item->name,
            quantity: $item->quantity,
            weight: $item->weight,
            declaredValue: $item->price * $item->quantity,
            shippableItemId: $item->id,
            shippableItemType: get_class($item),
        ))->toArray();
    }
}
```

---

## Bulk Operations

### BulkShipmentService

```php
<?php

namespace AIArmada\Shipping\Services;

class BulkShipmentService
{
    /**
     * Create shipments for multiple orders.
     */
    public function createBatch(array $orderIds, string $carrierCode): BulkResult
    {
        $results = collect();

        foreach ($orderIds as $orderId) {
            try {
                $order = Order::findOrFail($orderId);
                $shipment = $this->createShipmentFromOrder->execute($order, $carrierCode);
                $results->push(['order_id' => $orderId, 'success' => true, 'shipment' => $shipment]);
            } catch (\Exception $e) {
                $results->push(['order_id' => $orderId, 'success' => false, 'error' => $e->getMessage()]);
            }
        }

        return new BulkResult($results);
    }

    /**
     * Ship multiple pending shipments.
     */
    public function shipBatch(array $shipmentIds): BulkResult
    {
        $results = collect();

        foreach ($shipmentIds as $shipmentId) {
            try {
                $shipment = Shipment::findOrFail($shipmentId);
                $this->shipmentService->ship($shipment);
                $results->push(['shipment_id' => $shipmentId, 'success' => true]);
            } catch (\Exception $e) {
                $results->push(['shipment_id' => $shipmentId, 'success' => false, 'error' => $e->getMessage()]);
            }
        }

        return new BulkResult($results);
    }

    /**
     * Generate labels for multiple shipments.
     */
    public function generateLabelsBatch(array $shipmentIds): ZipArchive
    {
        $labels = collect();

        foreach ($shipmentIds as $shipmentId) {
            $shipment = Shipment::find($shipmentId);
            if ($shipment && $shipment->tracking_number) {
                $label = $this->shipmentService->generateLabel($shipment);
                $labels->push($label);
            }
        }

        return $this->createLabelArchive($labels);
    }
}
```

---

## Manifest Generation

### ManifestService

```php
<?php

namespace AIArmada\Shipping\Services;

class ManifestService
{
    /**
     * Generate end-of-day manifest for carrier pickup.
     */
    public function generateManifest(string $carrierCode, Carbon $date): Manifest
    {
        $shipments = Shipment::query()
            ->where('carrier_code', $carrierCode)
            ->where('status', ShipmentStatus::AwaitingPickup)
            ->whereDate('created_at', $date)
            ->get();

        $manifest = Manifest::create([
            'carrier_code' => $carrierCode,
            'date' => $date,
            'shipment_count' => $shipments->count(),
            'total_weight' => $shipments->sum('total_weight'),
            'status' => 'generated',
        ]);

        $manifest->shipments()->attach($shipments->pluck('id'));

        // Generate PDF
        $manifest->update([
            'pdf_url' => $this->generateManifestPdf($manifest),
        ]);

        return $manifest;
    }
}
```

---

## Navigation

**Previous:** [03-rate-shopping-engine.md](03-rate-shopping-engine.md)  
**Next:** [05-tracking-aggregation.md](05-tracking-aggregation.md) - Unified Tracking & Webhooks

---

*A well-defined shipment lifecycle ensures consistent handling across carriers while enabling the flexibility needed for diverse shipping scenarios.*

# Shipping Vision: Tracking Aggregation

> **Document:** 05 of 11  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision Document  
> **Last Updated:** December 7, 2025

---

## Overview

Tracking Aggregation provides a **unified view of shipment tracking** across all carriers. Each carrier has different event codes, timestamps, and data structures - this layer normalizes everything into a consistent format.

---

## Normalized Tracking Status

### TrackingStatus Enum

```php
enum TrackingStatus: string
{
    case LabelCreated = 'label_created';
    case AwaitingPickup = 'awaiting_pickup';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case ArrivedAtFacility = 'arrived_at_facility';
    case DepartedFacility = 'departed_facility';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case DeliveryAttemptFailed = 'delivery_attempt_failed';
    case AddressIssue = 'address_issue';
    case OnHold = 'on_hold';
    case ReturnToSender = 'return_to_sender';
    case ReturnDelivered = 'return_delivered';
}
```

---

## TrackingAggregator Service

### Core Features

- **Status Mappers**: Carrier-specific code → normalized status
- **Event Deduplication**: Prevent duplicate events
- **Batch Sync**: Efficiently sync multiple shipments
- **Auto Status Update**: Shipment status from tracking

### Key Methods

```php
class TrackingAggregator
{
    public function syncTracking(Shipment $shipment): Shipment;
    public function syncBatch(Collection $shipments): BulkResult;
    public function getShipmentsNeedingUpdate(): Collection;
}
```

---

## Webhook Aggregation Hub

Universal webhook endpoint for all carriers:

```
POST /api/shipping/webhooks/{carrier}
```

The `WebhookProcessor` routes to carrier-specific handlers and creates normalized tracking events.

---

## Tracking Sync Job

Scheduled sync for active shipments:

```bash
php artisan shipping:sync-tracking --limit=100
```

---

## Navigation

**Previous:** [04-shipment-lifecycle.md](04-shipment-lifecycle.md)  
**Next:** [06-returns-management.md](06-returns-management.md)

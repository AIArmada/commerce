# Shipping Vision: Returns Management

> **Document:** 06 of 11  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision Document  
> **Last Updated:** December 7, 2025

---

## Overview

Returns Management provides a complete **RMA (Return Merchandise Authorization)** workflow including return requests, authorization, label generation, tracking, and refund orchestration.

---

## Core Models

### ReturnAuthorization Model

```php
class ReturnAuthorization extends Model
{
    protected $fillable = [
        'owner_id', 'owner_type',
        'rma_number',
        'order_id',
        'customer_id',
        'status', // pending, approved, rejected, received, completed, cancelled
        'type', // refund, exchange, store_credit
        'reason',
        'reason_details',
        'approved_by',
        'approved_at',
        'expires_at',
        'metadata',
    ];

    public function items(): HasMany;
    public function shipment(): BelongsTo; // Original shipment
    public function returnShipment(): HasOne; // Return shipment
}
```

### ReturnAuthorizationItem Model

```php
class ReturnAuthorizationItem extends Model
{
    protected $fillable = [
        'return_authorization_id',
        'original_item_id',
        'sku', 'name',
        'quantity_requested',
        'quantity_approved',
        'quantity_received',
        'reason',
        'condition', // unused, opened, damaged
    ];
}
```

---

## Return Reasons

```php
enum ReturnReason: string
{
    case Damaged = 'damaged';
    case Defective = 'defective';
    case WrongItem = 'wrong_item';
    case NotAsDescribed = 'not_as_described';
    case DoesNotFit = 'does_not_fit';
    case ChangedMind = 'changed_mind';
    case BetterPrice = 'better_price';
    case NoLongerNeeded = 'no_longer_needed';
}
```

---

## ReturnService

```php
class ReturnService
{
    public function requestReturn(Order $order, array $items, ReturnReason $reason): ReturnAuthorization;
    public function approve(ReturnAuthorization $rma): ReturnAuthorization;
    public function reject(ReturnAuthorization $rma, string $reason): ReturnAuthorization;
    public function generateReturnLabel(ReturnAuthorization $rma): ShipmentLabel;
    public function markReceived(ReturnAuthorization $rma, array $receivedItems): ReturnAuthorization;
    public function processRefund(ReturnAuthorization $rma): RefundResult;
}
```

---

## Return Workflow

```
Customer Request → Pending RMA
        ↓
Admin Review → Approved / Rejected
        ↓
Return Label Generated → Customer Ships
        ↓
Warehouse Receives → Items Inspected
        ↓
Refund/Exchange Processed → Complete
```

---

## Integration Points

- **Inventory**: Restock items when received
- **Chip/Cashier**: Process refund payments
- **Vouchers**: Issue store credit vouchers
- **Notifications**: Email customer on status changes

---

## Navigation

**Previous:** [05-tracking-aggregation.md](05-tracking-aggregation.md)  
**Next:** [07-shipping-zones.md](07-shipping-zones.md)

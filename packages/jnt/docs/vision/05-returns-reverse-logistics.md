# Returns & Reverse Logistics

> **Document:** 5 of 9  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision

---

## Overview

Build a **complete returns management system** supporting RMA creation, return label generation, customer self-service, and refund/exchange automation.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    CUSTOMER REQUEST                              │
│  Return Request → Reason Selection → Items Selection            │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                     RMA CREATION                                 │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────────┐ │
│  │ Eligibility  │  │ Return       │  │ Label                  │ │
│  │ Check        │  │ Authorization│  │ Generation             │ │
│  └──────────────┘  └──────────────┘  └────────────────────────┘ │
│                                                                  │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    RETURN JOURNEY                                │
│                                                                  │
│  Customer Ships → In Transit → Received → Inspected             │
│                                                                  │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    RESOLUTION                                    │
│  Refund | Exchange | Repair | Reject                            │
└─────────────────────────────────────────────────────────────────┘
```

---

## Return Request

### ReturnRequest Model

```php
final class ReturnRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'customer_id',
        'rma_number',
        'status',
        'reason',
        'customer_notes',
        'internal_notes',
        'resolution_type',
        'refund_amount_minor',
        'return_shipping_paid_by',
        'return_tracking_number',
        'return_carrier_id',
        'received_at',
        'inspected_at',
        'resolved_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'reason' => ReturnReason::class,
            'resolution_type' => ResolutionType::class,
            'return_shipping_paid_by' => ReturnShippingPayer::class,
            'received_at' => 'datetime',
            'inspected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ReturnTrackingEvent::class);
    }

    public function generateRmaNumber(): string
    {
        return 'RMA-' . strtoupper(Str::random(8));
    }
}
```

### ReturnRequestItem Model

```php
final class ReturnRequestItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'return_request_id',
        'order_item_id',
        'quantity',
        'reason',
        'condition_received',
        'inspection_notes',
        'resolution',
        'refund_amount_minor',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ReturnReason::class,
            'condition_received' => ItemCondition::class,
            'resolution' => ItemResolution::class,
        ];
    }
}
```

---

## Enums

### ReturnStatus

```php
enum ReturnStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case LabelGenerated = 'label_generated';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Received = 'received';
    case Inspecting = 'inspecting';
    case Inspected = 'inspected';
    case Resolving = 'resolving';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Resolved,
            self::Rejected,
            self::Cancelled,
        ]);
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Requested => in_array($next, [self::Approved, self::Rejected]),
            self::Approved => in_array($next, [self::LabelGenerated, self::Cancelled]),
            self::LabelGenerated => in_array($next, [self::Shipped, self::Cancelled]),
            self::Shipped => [self::InTransit],
            self::InTransit => [self::Delivered],
            self::Delivered => [self::Received],
            self::Received => [self::Inspecting],
            self::Inspecting => [self::Inspected],
            self::Inspected => [self::Resolving],
            self::Resolving => [self::Resolved, self::Rejected],
            default => [],
        };
    }
}
```

### ReturnReason

```php
enum ReturnReason: string
{
    case Defective = 'defective';
    case WrongItem = 'wrong_item';
    case NotAsDescribed = 'not_as_described';
    case ChangedMind = 'changed_mind';
    case TooSmall = 'too_small';
    case TooLarge = 'too_large';
    case Damaged = 'damaged';
    case LateDelivery = 'late_delivery';
    case BetterPriceElsewhere = 'better_price';
    case NoLongerNeeded = 'no_longer_needed';
    case Other = 'other';

    public function isSellerFault(): bool
    {
        return in_array($this, [
            self::Defective,
            self::WrongItem,
            self::NotAsDescribed,
            self::Damaged,
        ]);
    }

    public function qualifiesForFreeReturn(): bool
    {
        return $this->isSellerFault();
    }
}
```

### ResolutionType

```php
enum ResolutionType: string
{
    case FullRefund = 'full_refund';
    case PartialRefund = 'partial_refund';
    case StoreCredit = 'store_credit';
    case Exchange = 'exchange';
    case Repair = 'repair';
    case Reject = 'reject';
}
```

---

## Return Service

### ReturnService

```php
final class ReturnService
{
    public function __construct(
        private readonly ShippingManager $shipping,
        private readonly ReturnPolicyService $policy,
        private readonly EventDispatcher $events,
    ) {}

    /**
     * Create a return request.
     */
    public function createRequest(CreateReturnData $data): ReturnRequest
    {
        // Validate eligibility
        $eligibility = $this->policy->checkEligibility($data);
        
        if (! $eligibility->isEligible) {
            throw new ReturnNotEligibleException($eligibility->reason);
        }

        $return = ReturnRequest::create([
            'order_id' => $data->orderId,
            'customer_id' => $data->customerId,
            'rma_number' => ReturnRequest::generateRmaNumber(),
            'status' => ReturnStatus::Requested,
            'reason' => $data->reason,
            'customer_notes' => $data->notes,
            'return_shipping_paid_by' => $eligibility->shippingPaidBy,
        ]);

        foreach ($data->items as $itemData) {
            $return->items()->create([
                'order_item_id' => $itemData['order_item_id'],
                'quantity' => $itemData['quantity'],
                'reason' => $itemData['reason'] ?? $data->reason,
            ]);
        }

        $this->events->dispatch(new ReturnRequested($return));

        // Auto-approve if policy allows
        if ($this->policy->shouldAutoApprove($return)) {
            $this->approve($return);
        }

        return $return;
    }

    /**
     * Approve a return request.
     */
    public function approve(ReturnRequest $return): ReturnRequest
    {
        $return->update(['status' => ReturnStatus::Approved]);
        
        $this->events->dispatch(new ReturnApproved($return));

        // Auto-generate label if enabled
        if (config('shipping.returns.auto_generate_label', true)) {
            $this->generateReturnLabel($return);
        }

        return $return->refresh();
    }

    /**
     * Generate return shipping label.
     */
    public function generateReturnLabel(ReturnRequest $return): ReturnRequest
    {
        $order = $return->order;
        
        // Determine carrier
        $carrierId = config('shipping.returns.default_carrier')
            ?? $order->shipping_carrier_id;

        // Create return shipment
        $shipmentData = new ShipmentData(
            sender: $this->buildAddressFromOrder($order, 'recipient'),
            recipient: $this->getReturnAddress(),
            package: $this->estimatePackage($return),
            reference: $return->rma_number,
        );

        $result = $this->shipping->carrier($carrierId)->createShipment($shipmentData);

        $return->update([
            'status' => ReturnStatus::LabelGenerated,
            'return_tracking_number' => $result->trackingNumber,
            'return_carrier_id' => $carrierId,
            'metadata' => array_merge($return->metadata ?? [], [
                'return_label_url' => $result->labelUrl,
                'return_shipment_id' => $result->shipmentId,
            ]),
        ]);

        $this->events->dispatch(new ReturnLabelGenerated($return, $result));

        return $return->refresh();
    }

    /**
     * Mark return as received.
     */
    public function markReceived(ReturnRequest $return): ReturnRequest
    {
        $return->update([
            'status' => ReturnStatus::Received,
            'received_at' => now(),
        ]);

        $this->events->dispatch(new ReturnReceived($return));

        return $return->refresh();
    }

    /**
     * Complete inspection and set resolution.
     */
    public function completeInspection(
        ReturnRequest $return,
        array $itemConditions,
        ResolutionType $resolution,
        ?int $refundAmountMinor = null
    ): ReturnRequest {
        // Update item conditions
        foreach ($itemConditions as $itemId => $data) {
            $return->items()
                ->where('id', $itemId)
                ->update([
                    'condition_received' => $data['condition'],
                    'inspection_notes' => $data['notes'] ?? null,
                    'resolution' => $data['resolution'] ?? $resolution,
                ]);
        }

        $return->update([
            'status' => ReturnStatus::Inspected,
            'inspected_at' => now(),
            'resolution_type' => $resolution,
            'refund_amount_minor' => $refundAmountMinor ?? $this->calculateRefund($return),
        ]);

        $this->events->dispatch(new ReturnInspected($return));

        return $return->refresh();
    }

    /**
     * Resolve the return (process refund/exchange).
     */
    public function resolve(ReturnRequest $return): ReturnRequest
    {
        match ($return->resolution_type) {
            ResolutionType::FullRefund,
            ResolutionType::PartialRefund => $this->processRefund($return),
            ResolutionType::StoreCredit => $this->issueStoreCredit($return),
            ResolutionType::Exchange => $this->createExchangeOrder($return),
            ResolutionType::Repair => $this->initiateRepair($return),
            default => null,
        };

        $return->update([
            'status' => ReturnStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $this->events->dispatch(new ReturnResolved($return));

        return $return->refresh();
    }
}
```

---

## Return Policy Service

### ReturnPolicyService

```php
final class ReturnPolicyService
{
    /**
     * Check if order/items are eligible for return.
     */
    public function checkEligibility(CreateReturnData $data): ReturnEligibility
    {
        $order = Order::findOrFail($data->orderId);
        
        // Check return window
        $returnWindow = config('shipping.returns.window_days', 14);
        $deliveredAt = $order->delivered_at ?? $order->created_at;
        
        if (now()->diffInDays($deliveredAt) > $returnWindow) {
            return new ReturnEligibility(
                isEligible: false,
                reason: "Return window of {$returnWindow} days has expired",
            );
        }

        // Check if items are returnable
        foreach ($data->items as $item) {
            $orderItem = $order->items()->find($item['order_item_id']);
            
            if (! $orderItem->product->is_returnable) {
                return new ReturnEligibility(
                    isEligible: false,
                    reason: "Item '{$orderItem->name}' is not eligible for return",
                );
            }

            // Check if already returned
            $alreadyReturned = ReturnRequestItem::where('order_item_id', $orderItem->id)
                ->whereHas('returnRequest', fn ($q) => 
                    $q->whereNotIn('status', [
                        ReturnStatus::Rejected,
                        ReturnStatus::Cancelled,
                    ])
                )
                ->sum('quantity');

            $remainingQty = $orderItem->quantity - $alreadyReturned;
            
            if ($item['quantity'] > $remainingQty) {
                return new ReturnEligibility(
                    isEligible: false,
                    reason: "Only {$remainingQty} units available for return",
                );
            }
        }

        // Determine who pays for return shipping
        $reason = ReturnReason::from($data->reason);
        $shippingPaidBy = $reason->qualifiesForFreeReturn()
            ? ReturnShippingPayer::Merchant
            : ReturnShippingPayer::Customer;

        return new ReturnEligibility(
            isEligible: true,
            shippingPaidBy: $shippingPaidBy,
        );
    }

    /**
     * Check if return should be auto-approved.
     */
    public function shouldAutoApprove(ReturnRequest $return): bool
    {
        // Auto-approve if seller fault
        if ($return->reason->isSellerFault()) {
            return true;
        }

        // Auto-approve for trusted customers
        $trustThreshold = config('shipping.returns.auto_approve_order_count', 5);
        $customerOrderCount = Order::where('customer_id', $return->customer_id)
            ->where('status', 'completed')
            ->count();

        return $customerOrderCount >= $trustThreshold;
    }
}
```

---

## Customer Self-Service

### CustomerReturnPortal

```php
final class CustomerReturnPortal
{
    public function __construct(
        private readonly ReturnService $returnService,
        private readonly ReturnPolicyService $policyService,
    ) {}

    /**
     * Get returnable orders for customer.
     */
    public function getReturnableOrders(string $customerId): Collection
    {
        $returnWindow = config('shipping.returns.window_days', 14);

        return Order::where('customer_id', $customerId)
            ->where('status', 'completed')
            ->where('delivered_at', '>=', now()->subDays($returnWindow))
            ->with(['items.product' => fn ($q) => $q->where('is_returnable', true)])
            ->get()
            ->filter(fn ($order) => $order->items->isNotEmpty());
    }

    /**
     * Start return wizard for an order.
     */
    public function getReturnWizardData(string $orderId): array
    {
        $order = Order::with('items.product')->findOrFail($orderId);

        return [
            'order' => $order,
            'returnable_items' => $order->items->filter(
                fn ($item) => $item->product->is_returnable
            ),
            'reasons' => ReturnReason::cases(),
            'return_window' => config('shipping.returns.window_days'),
            'return_policy_url' => config('shipping.returns.policy_url'),
        ];
    }

    /**
     * Submit return request from portal.
     */
    public function submitReturn(array $data): ReturnRequest
    {
        return $this->returnService->createRequest(
            new CreateReturnData(...$data)
        );
    }

    /**
     * Get return status page data.
     */
    public function getReturnStatus(string $rmaNumber): array
    {
        $return = ReturnRequest::where('rma_number', $rmaNumber)
            ->with(['items.orderItem', 'trackingEvents'])
            ->firstOrFail();

        return [
            'return' => $return,
            'timeline' => $this->buildTimeline($return),
            'label_url' => $return->metadata['return_label_url'] ?? null,
            'tracking_url' => $this->getTrackingUrl($return),
        ];
    }
}
```

---

## Events

```php
class ReturnRequested { public function __construct(public ReturnRequest $return) {} }
class ReturnApproved { public function __construct(public ReturnRequest $return) {} }
class ReturnLabelGenerated { public function __construct(public ReturnRequest $return, public ShipmentResult $shipment) {} }
class ReturnReceived { public function __construct(public ReturnRequest $return) {} }
class ReturnInspected { public function __construct(public ReturnRequest $return) {} }
class ReturnResolved { public function __construct(public ReturnRequest $return) {} }
class ReturnRejected { public function __construct(public ReturnRequest $return, public string $reason) {} }
```

---

## Navigation

**Previous:** [04-carrier-selection-rules.md](04-carrier-selection-rules.md)  
**Next:** [06-tracking-notifications.md](06-tracking-notifications.md)

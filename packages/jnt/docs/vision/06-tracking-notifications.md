# Tracking & Customer Notifications

> **Document:** 6 of 9  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision

---

## Overview

Build an **enhanced tracking and notification system** with predictive delivery, proactive alerts, branded tracking pages, and multi-channel notifications.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    TRACKING SOURCES                              │
│  Carrier Webhooks → Polling → Manual Updates                    │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                  TRACKING NORMALIZER                             │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────────┐ │
│  │ Event        │  │ Status       │  │ Location               │ │
│  │ Parser       │  │ Mapper       │  │ Enricher               │ │
│  └──────────────┘  └──────────────┘  └────────────────────────┘ │
│                                                                  │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                  DELIVERY ESTIMATOR                              │
│  Transit Analysis → ETA Calculation → Delay Detection           │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                  NOTIFICATION ENGINE                             │
│  Template Selection → Channel Routing → Delivery                │
└─────────────────────────────────────────────────────────────────┘
```

---

## Normalized Tracking Status

### TrackingStatus Enum

```php
enum TrackingStatus: string
{
    // Pre-transit
    case LabelCreated = 'label_created';
    case PickupScheduled = 'pickup_scheduled';
    case PickedUp = 'picked_up';
    
    // In Transit
    case InTransit = 'in_transit';
    case ArrivedAtFacility = 'arrived_at_facility';
    case DepartedFacility = 'departed_facility';
    case InTransitToDestination = 'in_transit_to_destination';
    
    // Delivery
    case OutForDelivery = 'out_for_delivery';
    case DeliveryAttempted = 'delivery_attempted';
    case Delivered = 'delivered';
    case DeliveredToNeighbor = 'delivered_to_neighbor';
    case DeliveredToLocker = 'delivered_to_locker';
    
    // Exceptions
    case Exception = 'exception';
    case Delayed = 'delayed';
    case AddressIssue = 'address_issue';
    case CustomerRefused = 'customer_refused';
    case DamagedInTransit = 'damaged_in_transit';
    case Lost = 'lost';
    
    // Returns
    case ReturnToSender = 'return_to_sender';
    case ReturnedToSender = 'returned_to_sender';
    
    // Holds
    case OnHold = 'on_hold';
    case AwaitingCustomerPickup = 'awaiting_customer_pickup';

    public function getStage(): TrackingStage
    {
        return match ($this) {
            self::LabelCreated, self::PickupScheduled, self::PickedUp 
                => TrackingStage::PreTransit,
            self::InTransit, self::ArrivedAtFacility, self::DepartedFacility, 
            self::InTransitToDestination 
                => TrackingStage::InTransit,
            self::OutForDelivery 
                => TrackingStage::OutForDelivery,
            self::Delivered, self::DeliveredToNeighbor, self::DeliveredToLocker 
                => TrackingStage::Delivered,
            self::Exception, self::Delayed, self::AddressIssue, 
            self::CustomerRefused, self::DamagedInTransit, self::Lost 
                => TrackingStage::Exception,
            default => TrackingStage::Unknown,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::DeliveredToNeighbor,
            self::DeliveredToLocker,
            self::ReturnedToSender,
            self::Lost,
        ]);
    }

    public function requiresNotification(): bool
    {
        return in_array($this, [
            self::PickedUp,
            self::OutForDelivery,
            self::Delivered,
            self::DeliveryAttempted,
            self::Exception,
            self::Delayed,
        ]);
    }
}
```

### TrackingStage Enum

```php
enum TrackingStage: string
{
    case PreTransit = 'pre_transit';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Exception = 'exception';
    case Unknown = 'unknown';

    public function percentage(): int
    {
        return match ($this) {
            self::PreTransit => 20,
            self::InTransit => 50,
            self::OutForDelivery => 80,
            self::Delivered => 100,
            self::Exception => 0,
            self::Unknown => 0,
        };
    }
}
```

---

## Tracking Event

### TrackingEvent Model

```php
final class TrackingEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'shipment_id',
        'carrier_id',
        'tracking_number',
        'status',
        'carrier_status_code',
        'carrier_status_description',
        'location_city',
        'location_state',
        'location_country',
        'location_postcode',
        'latitude',
        'longitude',
        'occurred_at',
        'estimated_delivery_at',
        'signature_name',
        'signature_image_url',
        'photo_urls',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => TrackingStatus::class,
            'occurred_at' => 'datetime',
            'estimated_delivery_at' => 'datetime',
            'photo_urls' => 'array',
            'metadata' => 'array',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function getLocationString(): string
    {
        return collect([
            $this->location_city,
            $this->location_state,
            $this->location_country,
        ])->filter()->implode(', ');
    }
}
```

---

## Delivery Estimation

### DeliveryEstimationService

```php
final class DeliveryEstimationService
{
    public function __construct(
        private readonly TransitDataRepository $transitData,
        private readonly HolidayService $holidays,
    ) {}

    /**
     * Estimate delivery date for a shipment.
     */
    public function estimate(Shipment $shipment): DeliveryEstimate
    {
        $latestEvent = $shipment->latestTrackingEvent;
        $zone = $this->getZone($shipment);
        
        // Get historical transit times for this carrier/zone
        $historicalData = $this->transitData->getForCarrierAndZone(
            $shipment->carrier_id,
            $zone
        );

        // Calculate base ETA
        $baseDeliveryDate = $this->calculateBaseEta(
            $shipment->created_at,
            $historicalData->averageTransitDays
        );

        // Adjust for current status
        $adjustedDate = $this->adjustForCurrentStatus(
            $baseDeliveryDate,
            $latestEvent,
            $historicalData
        );

        // Skip weekends/holidays if carrier doesn't deliver
        $adjustedDate = $this->skipNonDeliveryDays($adjustedDate, $shipment->carrier_id);

        // Calculate confidence based on data quality
        $confidence = $this->calculateConfidence($historicalData, $latestEvent);

        return new DeliveryEstimate(
            earliestDate: $adjustedDate->subDays($historicalData->varianceDays),
            expectedDate: $adjustedDate,
            latestDate: $adjustedDate->addDays($historicalData->varianceDays),
            confidence: $confidence,
            basedOn: $historicalData->sampleSize,
        );
    }

    /**
     * Detect delays compared to original estimate.
     */
    public function detectDelay(Shipment $shipment): ?DelayInfo
    {
        $latestEstimate = $this->estimate($shipment);
        $originalEstimate = $shipment->original_estimated_delivery_at;

        if (! $originalEstimate) {
            return null;
        }

        $delayDays = $latestEstimate->expectedDate->diffInDays($originalEstimate);

        if ($delayDays <= 0) {
            return null;
        }

        return new DelayInfo(
            originalDate: $originalEstimate,
            newEstimatedDate: $latestEstimate->expectedDate,
            delayDays: $delayDays,
            reason: $this->inferDelayReason($shipment),
        );
    }

    private function adjustForCurrentStatus(
        Carbon $baseDate,
        ?TrackingEvent $latestEvent,
        TransitHistoricalData $data
    ): Carbon {
        if (! $latestEvent) {
            return $baseDate;
        }

        return match ($latestEvent->status) {
            TrackingStatus::OutForDelivery => now(),
            TrackingStatus::InTransitToDestination => now()->addDay(),
            TrackingStatus::ArrivedAtFacility => $this->adjustFromFacility($latestEvent, $data),
            TrackingStatus::Exception => $baseDate->addDays(2),
            default => $baseDate,
        };
    }
}
```

---

## Notification Service

### ShipmentNotificationService

```php
final class ShipmentNotificationService
{
    public function __construct(
        private readonly NotificationRouter $router,
        private readonly TemplateEngine $templates,
        private readonly NotificationPreferences $preferences,
    ) {}

    /**
     * Handle tracking event and send notifications.
     */
    public function handleTrackingEvent(TrackingEvent $event): void
    {
        if (! $event->status->requiresNotification()) {
            return;
        }

        $shipment = $event->shipment;
        $customer = $shipment->customer;

        // Check customer preferences
        $channels = $this->preferences->getChannelsFor($customer, $event->status);

        if (empty($channels)) {
            return;
        }

        // Build notification data
        $notificationData = $this->buildNotificationData($shipment, $event);

        // Send to each channel
        foreach ($channels as $channel) {
            $this->sendToChannel($channel, $notificationData, $customer);
        }
    }

    private function sendToChannel(
        NotificationChannel $channel,
        array $data,
        Customer $customer
    ): void {
        $template = $this->templates->getForChannel($channel, $data['status']);
        $content = $this->templates->render($template, $data);

        match ($channel) {
            NotificationChannel::Email => $this->sendEmail($customer->email, $content),
            NotificationChannel::Sms => $this->sendSms($customer->phone, $content),
            NotificationChannel::WhatsApp => $this->sendWhatsApp($customer->phone, $content),
            NotificationChannel::Push => $this->sendPush($customer, $content),
        };
    }

    private function buildNotificationData(Shipment $shipment, TrackingEvent $event): array
    {
        return [
            'status' => $event->status,
            'tracking_number' => $shipment->tracking_number,
            'carrier_name' => $shipment->carrier->getName(),
            'location' => $event->getLocationString(),
            'occurred_at' => $event->occurred_at,
            'estimated_delivery' => $event->estimated_delivery_at,
            'tracking_url' => $this->getTrackingUrl($shipment),
            'order_number' => $shipment->order?->order_number,
            'recipient_name' => $shipment->recipient_name,
        ];
    }
}
```

### NotificationChannel Enum

```php
enum NotificationChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
    case Push = 'push';
}
```

---

## Notification Templates

### Template Structure

```php
// config/shipping.notifications.php
return [
    'templates' => [
        'picked_up' => [
            'email' => [
                'subject' => 'Your order is on its way! 📦',
                'template' => 'shipping::emails.picked-up',
            ],
            'sms' => [
                'template' => 'Your order {order_number} has been picked up. Track: {tracking_url}',
            ],
        ],
        'out_for_delivery' => [
            'email' => [
                'subject' => 'Your order is out for delivery! 🚚',
                'template' => 'shipping::emails.out-for-delivery',
            ],
            'sms' => [
                'template' => 'Arriving today! Order {order_number} is out for delivery. Track: {tracking_url}',
            ],
        ],
        'delivered' => [
            'email' => [
                'subject' => 'Your order has been delivered! ✅',
                'template' => 'shipping::emails.delivered',
            ],
            'sms' => [
                'template' => 'Order {order_number} delivered! Signed by: {signature_name}',
            ],
        ],
        'exception' => [
            'email' => [
                'subject' => 'Issue with your delivery ⚠️',
                'template' => 'shipping::emails.exception',
            ],
            'sms' => [
                'template' => 'Delivery issue for order {order_number}. Check status: {tracking_url}',
            ],
        ],
    ],
];
```

---

## Branded Tracking Page

### TrackingPageController

```php
final class TrackingPageController
{
    public function __construct(
        private readonly TrackingService $tracking,
        private readonly DeliveryEstimationService $estimation,
    ) {}

    public function show(string $trackingNumber): View
    {
        $shipment = Shipment::where('tracking_number', $trackingNumber)
            ->with(['trackingEvents' => fn ($q) => $q->orderByDesc('occurred_at')])
            ->firstOrFail();

        $estimate = $this->estimation->estimate($shipment);
        $delay = $this->estimation->detectDelay($shipment);

        return view('shipping::tracking.show', [
            'shipment' => $shipment,
            'events' => $shipment->trackingEvents,
            'current_stage' => $shipment->latestTrackingEvent?->status->getStage(),
            'progress_percentage' => $shipment->latestTrackingEvent?->status->getStage()->percentage(),
            'estimate' => $estimate,
            'delay' => $delay,
            'carrier' => $shipment->carrier,
            'branding' => $this->getBranding($shipment),
        ]);
    }

    private function getBranding(Shipment $shipment): array
    {
        $merchant = $shipment->order?->merchant;

        return [
            'logo_url' => $merchant?->logo_url ?? config('app.logo_url'),
            'primary_color' => $merchant?->brand_color ?? '#3B82F6',
            'support_email' => $merchant?->support_email ?? config('mail.from.address'),
            'support_phone' => $merchant?->support_phone,
        ];
    }
}
```

---

## Webhook Reliability

### WebhookProcessingService

```php
final class WebhookProcessingService
{
    public function __construct(
        private readonly EventDispatcher $events,
        private readonly WebhookLogRepository $logs,
    ) {}

    /**
     * Process incoming webhook with idempotency.
     */
    public function process(string $carrierId, array $payload, string $signature): WebhookResult
    {
        // Generate idempotency key
        $idempotencyKey = $this->generateIdempotencyKey($carrierId, $payload);

        // Check for duplicate
        if ($existing = $this->logs->findByIdempotencyKey($idempotencyKey)) {
            return new WebhookResult(
                success: true,
                message: 'Already processed',
                isIdempotent: true,
            );
        }

        // Verify signature
        if (! $this->verifySignature($carrierId, $payload, $signature)) {
            return new WebhookResult(
                success: false,
                message: 'Invalid signature',
            );
        }

        // Log webhook
        $log = $this->logs->create([
            'carrier_id' => $carrierId,
            'idempotency_key' => $idempotencyKey,
            'payload' => $payload,
            'status' => 'processing',
        ]);

        try {
            // Process based on carrier
            $event = $this->parseCarrierWebhook($carrierId, $payload);
            
            // Save tracking event
            TrackingEvent::create($event->toArray());

            // Dispatch events
            $this->events->dispatch(new TrackingEventReceived($event));

            $log->markAsProcessed();

            return new WebhookResult(success: true);
        } catch (Throwable $e) {
            $log->markAsFailed($e->getMessage());

            // Queue for retry
            ProcessFailedWebhook::dispatch($log->id)->delay(now()->addMinutes(5));

            return new WebhookResult(
                success: false,
                message: $e->getMessage(),
                shouldRetry: true,
            );
        }
    }

    /**
     * Replay a webhook for debugging.
     */
    public function replay(string $logId): WebhookResult
    {
        $log = $this->logs->findOrFail($logId);

        // Reset idempotency for replay
        $log->update(['idempotency_key' => null]);

        return $this->process(
            $log->carrier_id,
            $log->payload,
            'replay' // Skip signature verification for replays
        );
    }
}
```

---

## Navigation

**Previous:** [05-returns-reverse-logistics.md](05-returns-reverse-logistics.md)  
**Next:** [07-database-evolution.md](07-database-evolution.md)

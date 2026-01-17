---
title: Events & Webhooks
---

# Events & Webhooks

The package dispatches events for key actions and supports webhook delivery to external systems.

## Events

### AffiliateAttributed

Dispatched when a cart or session is attributed to an affiliate.

```php
use AIArmada\Affiliates\Events\AffiliateAttributed;

class AffiliateAttributed
{
    public function __construct(
        public readonly Affiliate $affiliate,
        public readonly AffiliateAttribution $attribution,
    ) {}
}
```

**Example Listener:**

```php
use AIArmada\Affiliates\Events\AffiliateAttributed;

class SendAttributionNotification
{
    public function handle(AffiliateAttributed $event): void
    {
        $affiliate = $event->affiliate;
        $attribution = $event->attribution;

        // Send notification
        Notification::send($affiliate->contact_email, new NewVisitorAttributed(
            affiliate: $affiliate,
            landingUrl: $attribution->landing_url,
            source: $attribution->source,
        ));
    }
}
```

### AffiliateConversionRecorded

Dispatched when a conversion is recorded.

```php
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;

class AffiliateConversionRecorded
{
    public function __construct(
        public readonly Affiliate $affiliate,
        public readonly AffiliateConversion $conversion,
    ) {}
}
```

### Orders Integration Event

When the Orders package is installed, it dispatches a `CommissionAttributionRequired` event
after payment. The Affiliates package listens for this event and records conversions when the
order metadata contains a `cart_id`.

**Example Listener:**

```php
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;

class UpdateAffiliateBalance
{
    public function handle(AffiliateConversionRecorded $event): void
    {
        $conversion = $event->conversion;
        $affiliate = $event->affiliate;

        // Update balance
        $balance = $affiliate->balance ?? $affiliate->balance()->create([
            'currency' => $affiliate->currency,
        ]);

        $balance->increment('pending_minor', $conversion->commission_minor);
    }
}
```

### Other Events

```php
// Affiliate status changed
AffiliateStatusChanged::class

// Payout created
AffiliatePayoutCreated::class

// Payout completed
AffiliatePayoutCompleted::class

// Fraud threshold reached
FraudThresholdReached::class

// Rank upgraded
AffiliateRankUpgraded::class
```

## Registering Listeners

```php
// app/Providers/EventServiceProvider.php

protected $listen = [
    \AIArmada\Affiliates\Events\AffiliateAttributed::class => [
        \App\Listeners\LogAttribution::class,
        \App\Listeners\NotifySlackChannel::class,
    ],
    \AIArmada\Affiliates\Events\AffiliateConversionRecorded::class => [
        \App\Listeners\UpdateAffiliateBalance::class,
        \App\Listeners\SendConversionEmail::class,
        \App\Listeners\TriggerWebhook::class,
    ],
];
```

## Event Configuration

Control which events are dispatched:

```php
// config/affiliates.php
'events' => [
    'dispatch_attributed' => env('AFFILIATES_EVENT_ATTRIBUTED', true),
    'dispatch_conversion' => env('AFFILIATES_EVENT_CONVERSION', true),
    'dispatch_webhooks' => env('AFFILIATES_EVENT_WEBHOOKS', false),
],
```

## Webhooks

The package can dispatch webhooks to external endpoints for real-time integration.

### Configuration

```php
// config/affiliates.php
'webhooks' => [
    'signature_secret' => env('AFFILIATES_WEBHOOK_SIGNATURE_SECRET'),
    'endpoints' => [
        'attribution' => [
            'https://your-crm.com/webhooks/affiliate-attribution',
        ],
        'conversion' => [
            'https://your-crm.com/webhooks/affiliate-conversion',
            'https://slack-webhook.com/...',
        ],
        'payout' => [
            'https://accounting-system.com/webhooks/payout',
        ],
    ],
    'headers' => [
        'X-Affiliates-Signature' => env('AFFILIATES_WEBHOOKS_SIGNATURE'),
    ],
],
```

### Webhook Payloads

**Attribution Webhook:**

```json
{
    "event": "affiliate.attributed",
    "timestamp": "2024-01-15T10:30:00Z",
    "data": {
        "attribution_id": "uuid",
        "affiliate": {
            "id": "uuid",
            "code": "PARTNER42",
            "name": "Partner Name"
        },
        "cart_identifier": "cart-123",
        "landing_url": "https://example.com/products",
        "source": "instagram",
        "medium": "social",
        "campaign": "summer-sale"
    }
}
```

**Conversion Webhook:**

```json
{
    "event": "affiliate.conversion",
    "timestamp": "2024-01-15T10:30:00Z",
    "data": {
        "conversion_id": "uuid",
        "affiliate": {
            "id": "uuid",
            "code": "PARTNER42",
            "name": "Partner Name"
        },
        "order_reference": "ORD-12345",
        "total_minor": 15000,
        "commission_minor": 1500,
        "currency": "USD",
        "status": "pending"
    }
}
```

### Using WebhookDispatcher

```php
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;

$dispatcher = app(WebhookDispatcher::class);

// Dispatch attribution webhook
$dispatcher->dispatchAttribution($attribution);

// Dispatch conversion webhook
$dispatcher->dispatchConversion($conversion);

// Dispatch payout webhook
$dispatcher->dispatchPayout($payout);

// Custom webhook
$dispatcher->dispatch('custom-event', [
    'data' => $customData,
], ['https://endpoint.com/webhook']);
```

### Webhook Signatures

Webhooks are signed for verification:

```php
// Receiving webhook
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_AFFILIATES_SIGNATURE'] ?? '';
$secret = config('affiliates.webhooks.signature_secret');

$expected = hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected, $signature)) {
    abort(401, 'Invalid signature');
}
```

### Retry Logic

Failed webhooks are queued for retry:

```php
// WebhookDispatcher uses Laravel's HTTP client with retry
Http::retry(3, 100)
    ->withHeaders($headers)
    ->post($endpoint, $payload);
```

## Custom Event Listeners

Create custom listeners for business logic:

```php
namespace App\Listeners;

use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendConversionToAnalytics implements ShouldQueue
{
    public function handle(AffiliateConversionRecorded $event): void
    {
        $conversion = $event->conversion;

        // Send to Google Analytics
        Analytics::trackEvent('affiliate_conversion', [
            'affiliate_code' => $conversion->affiliate_code,
            'order_total' => $conversion->total_minor / 100,
            'commission' => $conversion->commission_minor / 100,
        ]);
    }
}
```

## Queueing Events

For high-volume sites, queue event processing:

```php
class SendConversionNotification implements ShouldQueue
{
    public $queue = 'affiliates';

    public function handle(AffiliateConversionRecorded $event): void
    {
        // Processed asynchronously
    }
}
```

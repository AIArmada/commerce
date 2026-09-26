---
title: Events & Webhooks
---

# Events & Webhooks

The package dispatches events for key actions and supports webhook delivery to external systems.

## Events

### AffiliateAttributed

Dispatched when a cart or session is attributed to an affiliate. It carries
spatie/laravel-data DTOs, not models.

```php
use AIArmada\Affiliates\Events\AffiliateAttributed;
use AIArmada\Affiliates\Data\AffiliateData;
use AIArmada\Affiliates\Data\AffiliateAttributionData;

class AffiliateAttributed
{
    public function __construct(
        public readonly AffiliateData $affiliate,
        public readonly AffiliateAttributionData $attribution,
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

Dispatched when a conversion is recorded. It carries only the conversion DTO —
there is no `$affiliate` property.

```php
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Data\AffiliateConversionData;

class AffiliateConversionRecorded
{
    public function __construct(
        public readonly AffiliateConversionData $conversion
    ) {}
}
```

### Orders Integration Event

When the Orders package is installed, it dispatches a `CommissionAttributionRequired` event
after payment. The Affiliates package listens for this event and records conversions when the
order metadata contains a `cart_id`.

You usually do not need to update affiliate balances inside your own conversion listeners. The `AffiliateConversion` model already synchronizes holding and available balances as conversion states change.

**Example Listener:**

```php
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;

class SendConversionToAnalytics
{
    public function handle(AffiliateConversionRecorded $event): void
    {
        $conversion = $event->conversion;

        Analytics::trackEvent('affiliate_conversion_recorded', [
            'affiliate_code' => $conversion->affiliate_code,
            'external_reference' => $conversion->external_reference,
            'value_minor' => $conversion->value_minor,
            'commission_minor' => $conversion->commission_minor,
        ]);
    }
}
```

### Other Events

These are the full set of events in `AIArmada\Affiliates\Events`:

```php
AffiliateActivated::class          // Affiliate $affiliate
AffiliateCreated::class             // Affiliate $affiliate
AffiliateProgramJoined::class       // Affiliate, AffiliateProgram, AffiliateProgramMembership
AffiliateProgramLeft::class         // Affiliate, AffiliateProgram
AffiliateTierUpgraded::class        // Affiliate, AffiliateProgram, ?fromTier, toTier
AffiliateRankChanged::class         // Affiliate, ?fromRank, toRank, RankQualificationReason
DailyStatsAggregated::class         // CarbonImmutable $date, int $affiliateCount
FraudSignalDetected::class          // AffiliateFraudSignal $signal
```

> **warning:**
> There are no `AffiliateStatusChanged`, `AffiliatePayoutCreated`,
> `AffiliatePayoutCompleted`, `FraudThresholdReached`, or
> `AffiliateRankUpgraded` events. Payout state changes are model transitions on
> `AffiliatePayout` (see `UpdatePayoutStatus`), and rank movement surfaces as
> `AffiliateRankChanged`.

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
        // Comma-separated string per event type, exploded into an array.
        'attribution' => explode(',', (string) env('AFFILIATES_WEBHOOKS_ATTRIBUTION', '')),
        'conversion' => explode(',', (string) env('AFFILIATES_WEBHOOKS_CONVERSION', '')),
        'payout' => explode(',', (string) env('AFFILIATES_WEBHOOKS_PAYOUT', '')),
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
        "external_reference": "ORD-12345",
        "value_minor": 15000,
        "commission_minor": 1500,
        "currency": "USD",
        "status": "pending"
    }
}
```

### Using WebhookDispatcher

`WebhookDispatcher` has a single public method. Endpoints come from
`affiliates.webhooks.endpoints.{type}`; there is no per-call endpoint argument
and no `dispatchAttribution` / `dispatchConversion` / `dispatchPayout`
shorthand.

```php
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;

$dispatcher = app(WebhookDispatcher::class);

// type must match a key under affiliates.webhooks.endpoints
$dispatcher->dispatch('attribution', $payload);
$dispatcher->dispatch('conversion', $payload);
$dispatcher->dispatch('payout', $payload);
$dispatcher->dispatch('custom-event', $payload);
```

Dispatch is a no-op unless `affiliates.events.dispatch_webhooks` is `true` and
`affiliates.webhooks.signature_secret` is a non-empty string. Each endpoint
gets a durable `AffiliateWebhookDelivery` row and a queued
`DispatchAffiliateWebhook` job.

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
            'value_minor' => $conversion->value_minor,
            'commission_minor' => $conversion->commission_minor,
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

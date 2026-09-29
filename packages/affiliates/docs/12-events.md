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
use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Data\AffiliateData;
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
        $affiliate = $event->affiliate;       // AffiliateData DTO
        $attribution = $event->attribution;   // AffiliateAttributionData DTO

        // DTO fields: $affiliate->code, $affiliate->name,
        // $attribution->source, $attribution->campaign, ...
    }
}
```

### AffiliateConversionRecorded

Dispatched when a conversion is recorded. It carries only the conversion DTO —
there is no `$affiliate` property.

```php
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
        $conversion = $event->conversion; // AffiliateConversionData DTO

        Analytics::trackEvent('affiliate_conversion_recorded', [
            'affiliate_code' => $conversion->affiliateCode,
            'external_reference' => $conversion->externalReference,
            'value_minor' => $conversion->valueMinor,
            'commission_minor' => $conversion->commissionMinor,
        ]);
    }
}
```

### Other Events

These are the full set of events in `AIArmada\Affiliates\Events`:

```php
use AIArmada\Affiliates\Events\AffiliateActivated;
use AIArmada\Affiliates\Events\AffiliateCreated;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Events\AffiliateProgramLeft;
use AIArmada\Affiliates\Events\AffiliateRankChanged;
use AIArmada\Affiliates\Events\AffiliateTierUpgraded;
use AIArmada\Affiliates\Events\DailyStatsAggregated;
use AIArmada\Affiliates\Events\FraudSignalDetected;
use AIArmada\Affiliates\Events\HoldingShortfallDetected;

// Affiliate created (model hook) / activated
AffiliateCreated::class;        // (Affiliate $affiliate)
AffiliateActivated::class;      // (Affiliate $affiliate)

// Program membership changes
AffiliateProgramJoined::class;  // (AffiliateProgramMembership $membership)
AffiliateProgramLeft::class;    // (AffiliateProgramMembership $membership)

// Rank and tier upgrades
AffiliateRankChanged::class;    // (Affiliate $affiliate, AffiliateRank $newRank, ?AffiliateRank $previousRank)
AffiliateTierUpgraded::class;   // (AffiliateProgramMembership $membership, AffiliateProgramTier $newTier, ?AffiliateProgramTier $previousTier, Affiliate $affiliate)

// Daily aggregation finished
DailyStatsAggregated::class;    // (CarbonImmutable $date, int $affiliateCount)

// Fraud signal recorded (model create from any path; dispatched after
// commit; rolled-back signals never dispatch)
FraudSignalDetected::class;     // (AffiliateFraudSignal $signal)

// Holding pool carried less than a conversion recorded (release/void clamped;
// the affiliate was still made whole — signal only, no in-repo consumer)
HoldingShortfallDetected::class; // (AffiliateConversion $conversion, string $operation, int $requestedMinor, int $appliedMinor)
```

> [!NOTE]
> `FraudSignalDetected` implements `ShouldDispatchAfterCommit`: host listeners such as auto-suspend run after the signal transaction commits. A listener failure therefore never rolls back the recorded signal.

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

Every webhook shares one envelope; only `type` and `data` vary:

```json
{
    "type": "conversion",
    "id": "uuid",
    "data": {
        "external_reference": "ORD-12345",
        "value_minor": 15000,
        "commission_minor": 1500,
        "currency": "USD"
    },
    "sent_at": "2024-01-15T10:30:00Z"
}
```

`type` selects the endpoint list from `affiliates.webhooks.endpoints.{type}` (e.g. `attribution`, `conversion`, `payout`).

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
$signature = $_SERVER['HTTP_X_AFFILIATES_WEBHOOK_SIGNATURE'] ?? '';
$secret = config('affiliates.webhooks.signature_secret');

$expected = hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected, $signature)) {
    abort(401, 'Invalid signature');
}
```

### Retry Logic

Failed webhooks retry through the queued `DispatchAffiliateWebhook` job (attempts from `webhooks.delivery.max_attempts`, default 5, with `webhooks.delivery.backoff_seconds` between tries, default `[10, 30, 120, 300]`).

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
        $conversion = $event->conversion; // AffiliateConversionData DTO

        // Send to Google Analytics
        Analytics::trackEvent('affiliate_conversion', [
            'affiliate_code' => $conversion->affiliateCode,
            'value_minor' => $conversion->valueMinor,
            'commission_minor' => $conversion->commissionMinor,
            'currency' => $conversion->commissionCurrency,
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

---
title: Webhooks
---

# Webhooks

Cashier CHIP has no webhook endpoint of its own. It reacts to the `aiarmada/chip` webhook flow:
CHIP owns the route, controller, signature verification, and replay handling, and Cashier CHIP
subscribes to the Laravel events the CHIP package dispatches to update payment statuses, save
recurring tokens, and manage subscription states.

## Webhook Route

`aiarmada/chip` registers the webhook route:

```
POST /chip/webhooks   (route name: chip.webhook)
```

The path comes from `chip.webhooks.route` (`CHIP_WEBHOOK_ROUTE`, default `/chip/webhooks`).
Point your CHIP dashboard at that URL.

## Configuration

### CSRF Protection

No action needed. The route runs the `api` middleware group
(`chip.webhooks.middleware` = `['api', 'throttle:120,1']`), which is CSRF-free by default.

### Signature Verification

CHIP verification is **asymmetric**: the signature is verified against CHIP's RSA public key, not a
shared secret.

- Header: `X-Signature` (base64-encoded)
- Algorithm: `OPENSSL_ALGO_SHA256` over the **raw request body** (`$request->getContent()`), never
  the parsed JSON
- Public key: `chip.collect.public_key` if configured, otherwise fetched from CHIP's
  `GET /public_key/` and cached. Per-webhook keys can be supplied through
  `chip.collect.webhook_keys`

```php
// config/chip.php (the chip package owns signature verification)
'webhooks' => [
    'verify_signature' => env('CHIP_WEBHOOK_VERIFY_SIGNATURE', true),
],
```

> **warning**
> Setting `chip.webhooks.verify_signature` to `false` only works outside production. In production
> the validator logs an error and rejects the request with `401` even when the flag is disabled.

> **info**
> `cashier-chip.webhooks.secret` (`CHIP_WEBHOOK_SECRET`) exists in `config/cashier-chip.php` but is
> never read. It is inert — CHIP signature verification uses a public key, not a shared secret.

### Timestamp Tolerance

There is no timestamp or nonce check. CHIP's signature covers the raw body only, so replay
protection comes from webhook deduplication, not from a freshness window.

### Replay and Idempotency

Two layers:

1. `chip.webhooks.store_webhooks` / `chip.webhooks.deduplication` (both default `true`) claim an
   `idempotency_key` per delivery. A redelivery whose key is already processed is dropped before any
   handler runs.
2. `SyncChipPurchaseStatus` additionally short-circuits on a `purchase_id` already recorded as a
   `completed` `RenewalAttempt`, so a re-delivered purchase cannot double-apply a subscription
   state change.

## Handled Events

Cashier CHIP registers three listeners on CHIP's events
(`packages/cashier-chip/src/CashierChipServiceProvider.php`):

| CHIP event type | CHIP event class | Cashier CHIP listener | Description |
|-----------------|------------------|-----------------------|-------------|
| `purchase.paid` | `AIArmada\Chip\Events\PurchasePaid` | `HandlePurchasePaid` | Payment completed; saves recurring token, syncs subscription |
| `purchase.payment_failure` | `AIArmada\Chip\Events\PurchasePaymentFailure` | `HandlePurchasePaymentFailure` | Payment failed; moves subscription to Past Due |
| `purchase.preauthorized` | `AIArmada\Chip\Events\PurchasePreauthorized` | `HandlePurchasePreauthorized` | Setup purchase; saves recurring token |

The full set of CHIP event types is `AIArmada\Chip\Enums\WebhookEventType` (for example
`purchase.captured`, `payment.refunded`, `purchase.expired`). There are no
`recurring_token.created` / `recurring_token.deleted` webhook types — token lifecycle rides on the
purchase events above.

> **info**
> Event type extraction is `$payload['event_type']`, done by the CHIP package. There is no
> `handlePurchasePaymentSuccess()` / `handleUnknownEvent()` override point, and no cashier-chip
> webhook controller to extend.

## Events Dispatched

Cashier CHIP dispatches its own Laravel events from `SyncChipPurchaseStatus` and the renewal path:

```php
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentRefunded;
use AIArmada\CashierChip\Events\SubscriptionRenewed;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Events\SubscriptionCreated;
use AIArmada\CashierChip\Events\SubscriptionCanceled;
use AIArmada\CashierChip\Events\SubscriptionResumed;
use AIArmada\CashierChip\Events\SubscriptionUpdated;
use AIArmada\CashierChip\Events\SettledPeriodPurchaseConflict;

protected $listen = [
    PaymentSucceeded::class => [
        SendPaymentConfirmation::class,
        UpdateOrderStatus::class,
    ],
    PaymentFailed::class => [
        NotifyPaymentFailure::class,
    ],
];
```

There is no `WebhookReceived` / `WebhookHandled` / `PaymentMethodAdded` / `PaymentMethodRemoved` /
`DefaultPaymentMethodChanged` event in this package. Webhook-level events live in
`aiarmada/chip` (`AIArmada\Chip\Events\WebhookReceived`) and, for the unified cross-gateway layer,
in `aiarmada/cashier` (`AIArmada\Cashier\Events\WebhookReceived`).

## Custom Webhook Handling

Listen to the CHIP events (or the Cashier CHIP events) rather than subclassing a controller:

```php
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\CashierChip\Billing\Cashier;

class LogPurchasePaid
{
    public function handle(PurchasePaid $event): void
    {
        $purchase = $event->purchase;
        $clientId = $purchase->getClientId();
        $billable = $clientId ? Cashier::findBillable($clientId) : null;

        Log::info('CHIP purchase paid', [
            'purchase_id' => $purchase->id,
            'client_id' => $clientId,
            'billable' => $billable?->getKey(),
            'status' => $purchase->status,
        ]);
    }
}
```

## Payload Structure

CHIP webhooks are the purchase object with an `event_type` envelope added, so the event fields
(`id`, `status`, `recurring_token`, `reference`, `transaction_data`) sit at the top level and the
amount details live under `purchase`.

All monetary amounts are integers in the smallest currency unit (for MYR, this is minor units).

```json
{
    "event_type": "purchase.paid",
    "id": "purchase-uuid",
    "type": "purchase",
    "brand_id": "brand-uuid",
    "client_id": "client-uuid",
    "status": "paid",
    "is_test": false,
    "recurring_token": "tok_xxxxx",
    "reference": "Order #123",
    "checkout_url": "https://pay.chip-in.asia/...",
    "created_on": 1750000000,
    "purchase": {
        "currency": "MYR",
        "total": 10000,
        "products": [
            { "name": "Product Name", "price": 10000, "quantity": 1 }
        ],
        "metadata": {
            "billable_type": "App\\Models\\User",
            "billable_id": "uuid",
            "subscription_type": "default"
        }
    },
    "transaction_data": {
        "payment_method": "card",
        "extra": { "masked_pan": "************1234" }
    }
}
```

`subscription_type` inside `purchase.metadata` is what links an inbound payment back to a local
subscription.

## Accessing Webhook Data

In your event listener:

```php
use AIArmada\CashierChip\Events\PaymentSucceeded;

class HandlePaymentSuccess
{
    public function handle(PaymentSucceeded $event): void
    {
        // The purchase array is on $event->purchase, not $event->payload
        $purchase = $event->purchase;
        $billable = $event->billable;

        $purchaseId = $purchase['id'] ?? null;
        $amount = data_get($purchase, 'purchase.total');
        $currency = data_get($purchase, 'purchase.currency');

        if ($billable) {
            $billable->notify(new PaymentReceived((int) $amount));
        }
    }
}
```

`PaymentSucceeded`, `PaymentFailed`, and `PaymentRefunded` all carry `public Model $billable` and
`public array $purchase`, plus a `metadata()` helper. Subscription events
(`SubscriptionCreated`, `SubscriptionCanceled`, `SubscriptionUpdated`, `SubscriptionResumed`) carry a
single `Subscription`. `SubscriptionRenewed` carries `($subscription, $payment = null)` and
`SubscriptionRenewalFailed` carries `($subscription, $reason = '')`.

## Owner Scoping

When `cashier-chip.features.owner.enabled` is true, every listener returns early if no owner is
resolved. The CHIP package resolves the owner from `brand_id` before dispatching and wraps handling
in `OwnerContext::withOwner()`.

## Testing Webhooks

### Local Development

Use a tunnel service like ngrok:

```bash
ngrok http 8000
```

Configure the ngrok URL in your CHIP dashboard.

### Faking the Gateway

```php
use AIArmada\CashierChip\Billing\Cashier;

Cashier::fake();

// Now all CHIP API calls are faked
$payment = $user->charge(10000);
```

### Simulating a Webhook

Use the CHIP package's simulator, which signs the payload for you:

```php
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Testing\SimulatesWebhooks;

class WebhookTest extends TestCase
{
    use SimulatesWebhooks;

    public function test_paid_webhook_syncs_payment(): void
    {
        withoutWebhookSignatureVerification();

        $this->postWebhook('/chip/webhooks', [
            'event_type' => 'purchase.paid',
            'id' => 'purchase-123',
            'client_id' => $user->chipId(),
            'status' => 'paid',
        ])->assertOk();
    }
}
```

`WebhookSimulator::forEvent(WebhookEventType::PurchasePaid)` and
`simulatePaidWebhook()` build correctly shaped, signed payloads.

### Disabling Signature Verification

For testing only:

```php
// config/chip.php
'webhooks' => [
    'verify_signature' => env('CHIP_WEBHOOK_VERIFY_SIGNATURE', true),
],

// .env.testing
CHIP_WEBHOOK_VERIFY_SIGNATURE=false
```

## Error Handling

The CHIP controller returns `401` for a missing or invalid signature and `500` when owner
resolution fails. Anything else that throws propagates so CHIP redelivers. Handle retries by
checking whether the `purchase_id` has already been applied — see
[Replay and Idempotency](#replay-and-idempotency).

## Logging Webhook Activity

`chip.webhooks.log_payloads` (`CHIP_WEBHOOK_LOG_PAYLOADS`, default `false`) logs full payloads to
`chip.logging.channel`, with sensitive fields masked unless
`chip.logging.mask_sensitive_data` is disabled.

## Webhook Security

1. **Leave `chip.webhooks.verify_signature` enabled** — it cannot be turned off in production
2. Use HTTPS for webhook endpoints
3. Keep `chip.webhooks.deduplication` enabled for replay protection
4. Store the CHIP public key through `chip.collect.public_key` or `chip.collect.webhook_keys` so
   verification does not depend on a live `GET /public_key/` call
5. Log webhook activity for debugging

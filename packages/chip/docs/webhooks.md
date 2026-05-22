---
title: Webhooks
---

# Webhooks

Use the built-in CHIP webhook route when you want signature verification, queued processing, typed events, and local persistence updates without wiring your own controller.

## Built-in route

The package registers a POST route at `config('chip.webhooks.route', '/chip/webhook')`:

- controller: `AIArmada\Chip\Http\Controllers\WebhookController`
- route name: `chip.webhook`
- processor job: `AIArmada\Chip\Webhooks\ProcessChipWebhook`

This path integrates with `spatie/laravel-webhook-client` and the package's webhook event dispatcher, so incoming deliveries can be verified, deduplicated, stored, and translated into typed CHIP events.

```env
CHIP_WEBHOOKS_ENABLED=true
CHIP_WEBHOOK_ROUTE=/chip/webhook
CHIP_WEBHOOK_VERIFY_SIGNATURE=true
CHIP_COMPANY_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----..."
CHIP_WEBHOOK_PUBLIC_KEYS='{"webhook-id":"-----BEGIN PUBLIC KEY-----..."}'
```

Relevant config:

```php
'webhooks' => [
    'enabled' => env('CHIP_WEBHOOKS_ENABLED', true),
    'route' => env('CHIP_WEBHOOK_ROUTE', '/chip/webhook'),
    'middleware' => ['api'],
    'company_public_key' => env('CHIP_COMPANY_PUBLIC_KEY'),
    'webhook_keys' => json_decode(env('CHIP_WEBHOOK_PUBLIC_KEYS', '[]'), true) ?: [],
    'verify_signature' => env('CHIP_WEBHOOK_VERIFY_SIGNATURE', true),
    'store_webhooks' => env('CHIP_WEBHOOK_STORE', true),
    'deduplication' => env('CHIP_WEBHOOK_DEDUPLICATION', true),
],
```

## Signature verification

CHIP signs callback payloads with RSA PKCS#1 v1.5 over the SHA-256 digest of the raw request body. The signature is delivered in the `X-Signature` header.

According to the CHIP Collect docs:

- purchase success callbacks use the company public key from `GET /public_key/`
- registered webhooks use the webhook-specific public key from `Webhook.public_key`

This package supports both sources:

- `chip.webhooks.company_public_key`
- `chip.webhooks.webhook_keys`

At runtime, `AIArmada\Chip\Services\WebhookService` attempts a webhook-specific key first when it can resolve a webhook ID, then falls back to configured webhook keys and finally the company key.

Low-level verification example:

```php
use AIArmada\Chip\Services\WebhookService;
use Illuminate\Http\Request;

Route::post('/webhooks/chip/manual', function (Request $request) {
    $service = app(WebhookService::class);

    abort_unless($service->verifySignature($request), 400, 'Invalid signature');

    $payload = (array) $service->parsePayload($request->getContent());

    return response()->json([
        'received' => true,
        'event_type' => $payload['event_type'] ?? 'unknown',
    ]);
});
```

## Built-in processing flow

When the built-in route is enabled, successful deliveries flow through these steps:

1. signature verification
2. deduplication and webhook-call storage
3. `WebhookReceived` dispatch
4. typed event dispatch through `WebhookEventDispatcher`
5. local model synchronization and docs integration listeners

The generic event is:

- `AIArmada\Chip\Events\WebhookReceived`

Typed events include:

- `PurchasePaid`
- `PurchaseCancelled`
- `PurchasePendingRefund`
- `PaymentRefunded`
- payout events

If `chip.webhooks.store_webhooks` is enabled, `AIArmada\Chip\Listeners\StoreWebhookData` persists purchase payloads plus purchase-related payment payloads such as `payment.refunded`.

## Manual gateway handling

If you need the universal payment-gateway adapter instead of the built-in queued processor, use `ChipGateway`'s webhook handler:

```php
use AIArmada\Chip\Gateways\ChipGateway;
use Illuminate\Http\Request;

Route::post('/webhooks/chip/manual-gateway', function (Request $request) {
    $gateway = app(ChipGateway::class);
    $handler = $gateway->getWebhookHandler();

    abort_unless($handler->verifyWebhook($request), 400, 'Invalid signature');

    $payload = $handler->parseWebhook($request);

    match ($payload->eventType) {
        'purchase.paid' => handlePurchasePaid($payload),
        'purchase.cancelled' => handlePurchaseCancelled($payload),
        'purchase.pending_refund' => handleRefundPending($payload),
        'payment.refunded' => handleRefundCompleted($payload),
        default => null,
    };

    return response()->json(['ok' => true]);
});
```

`parseWebhook()` returns `AIArmada\CommerceSupport\Contracts\Payment\WebhookPayload` with:

- `eventType`
- `paymentId`
- `status`
- `reference`
- `gatewayName`
- `occurredAt`
- `rawData`

For payment-shaped refund callbacks, the handler resolves `paymentId` to the related purchase ID from `related_to.id`.

## Payload shapes

CHIP Collect callbacks are top-level JSON objects. They are not wrapped in a generic `{ event, data }` envelope.

### Purchase-shaped events

Purchase lifecycle webhooks such as `purchase.paid` and `purchase.pending_refund` are purchase-shaped:

- `type = purchase`
- `id = <purchase id>`
- `event_type = purchase.*`

### Refund completion events

Refund completion uses a payment-shaped payload:

- `event_type = payment.refunded`
- `type = payment`
- `id = <refund payment id>`
- `related_to.type = purchase`
- `related_to.id = <purchase id>`

That means the refund payment has its own identifier, while the original purchase is referenced through `related_to.id`.

## Common Collect events

The package currently handles these CHIP Collect webhook events:

| Event | Payload shape | Meaning |
| --- | --- | --- |
| `purchase.created` | Purchase | Purchase created |
| `purchase.paid` | Purchase | Purchase paid |
| `purchase.cancelled` | Purchase | Purchase cancelled |
| `purchase.payment_failure` | Purchase | Payment failed |
| `purchase.hold` | Purchase | Payment authorized and on hold |
| `purchase.captured` | Purchase | Authorized payment captured |
| `purchase.released` | Purchase | Held funds released |
| `purchase.preauthorized` | Purchase | Purchase preauthorized |
| `purchase.pending_execute` | Purchase | Payment execution pending |
| `purchase.pending_charge` | Purchase | Recurring charge pending |
| `purchase.pending_capture` | Purchase | Capture pending |
| `purchase.pending_release` | Purchase | Release pending |
| `purchase.pending_refund` | Purchase | Refund requested and still processing |
| `purchase.pending_recurring_token_delete` | Purchase | Recurring-token removal pending |
| `purchase.recurring_token_deleted` | Purchase | Recurring token removed |
| `payment.refunded` | Payment | Refund completed |

## Testing

Use `WebhookFactory` to build realistic CHIP payloads in tests:

```php
use AIArmada\Chip\Testing\WebhookFactory;

it('builds a payment.refunded payload', function () {
    $payload = WebhookFactory::paymentRefunded();

    expect($payload['event_type'])->toBe('payment.refunded')
        ->and($payload['type'])->toBe('payment')
        ->and(data_get($payload, 'related_to.type'))->toBe('purchase')
        ->and(data_get($payload, 'payment.is_outgoing'))->toBeTrue();
});
```

`WebhookSimulator::forEvent()` uses the same event-aware factory mapping, so pending purchase callbacks and payment-shaped `payment.refunded` callbacks keep their documented payload shapes when you simulate them in tests:

```php
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Testing\WebhookSimulator;

$pendingRefund = WebhookSimulator::forEvent(WebhookEventType::PurchasePendingRefund)->getPayload();
$completedRefund = WebhookSimulator::forEvent(WebhookEventType::PaymentRefunded)->getPayload();
```

Use the built-in testing helpers when you want to simulate the package's event flow instead of hand-rolling webhook arrays. When owner mode is enabled and you dispatch directly inside an active `OwnerContext`, `WebhookSimulator::dispatch()` also carries the current owner tuple into the payload so owner-aware listeners behave like the real HTTP path.

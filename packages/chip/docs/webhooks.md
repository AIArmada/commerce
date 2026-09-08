---
title: Webhooks
---

# Webhooks

Use the built-in CHIP webhook route when you want signature verification, queued processing, typed events, and local persistence updates without wiring your own controller. The route is for registered CHIP webhooks; purchase success callbacks use the separate company-key verifier described below.

## Built-in route

The package registers a POST route at `config('chip.webhooks.route', '/chip/webhooks')`:

- controller: `AIArmada\Chip\Http\Controllers\WebhookController`
- route name: `chip.webhook`
- processor job: `AIArmada\Chip\Webhooks\ProcessChipWebhook`

This path integrates with `spatie/laravel-webhook-client` and the package's webhook event dispatcher, so incoming deliveries can be verified, deduplicated, stored, and translated into typed CHIP events.

```env
CHIP_WEBHOOKS_ENABLED=true
CHIP_WEBHOOK_ROUTE=/chip/webhooks
CHIP_WEBHOOK_VERIFY_SIGNATURE=true
CHIP_COLLECT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----..."
CHIP_COLLECT_WEBHOOK_PUBLIC_KEYS='{"wh-uuid":"-----BEGIN PUBLIC KEY-----..."}'
CHIP_SEND_WEBHOOK_PUBLIC_KEYS='{"1":"-----BEGIN PUBLIC KEY-----..."}'
```

Relevant config:

```php
'webhooks' => [
    'enabled' => env('CHIP_WEBHOOKS_ENABLED', true),
    'route' => env('CHIP_WEBHOOK_ROUTE', '/chip/webhooks'),
    'middleware' => ['api'],
    'verify_signature' => env('CHIP_WEBHOOK_VERIFY_SIGNATURE', true),
    'store_webhooks' => env('CHIP_WEBHOOK_STORE', true),
    'deduplication' => env('CHIP_WEBHOOK_DEDUPLICATION', true),

    'collect' => [
        'webhook_keys' => json_decode(env('CHIP_COLLECT_WEBHOOK_PUBLIC_KEYS', '[]'), true) ?: [],
    ],

    'send' => [
        'webhook_keys' => json_decode(env('CHIP_SEND_WEBHOOK_PUBLIC_KEYS', '[]'), true) ?: [],
    ],
],
```

If `aiarmada/checkout` is installed with the default `checkout.integrations.chip.enabled=true`, register only this CHIP route in the CHIP dashboard. Checkout listens to the typed CHIP events emitted after this route processes the delivery, so you do not need to post the same webhook to `/webhooks/checkout`.

## Signature verification

CHIP Collect signs callback payloads with RSA PKCS#1 v1.5 over the SHA-256 digest of the raw request body. CHIP Send webhook deliveries use RSA PKCS#1 v1.5 over the SHA-512 digest instead. Both signatures are delivered in the `X-Signature` header.

According to the CHIP Collect docs:

- purchase success callbacks use the company public key from `GET /public_key/`
- registered webhooks use the webhook-specific public key from `Webhook.public_key`

This package supports both sources:

- `chip.collect.public_key` (company key for Collect success callbacks)
- `chip.webhooks.collect.webhook_keys` (per-webhook keys for Collect registered webhooks)
Send webhook verification requires the dedicated key returned by the CHIP Send webhook API. Send webhooks must not be posted to the Collect webhook route because their payloads have no Collect `event_type` and use SHA-512 verification.

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

For a `success_callback` URL configured on a Purchase, use the company-wide
public key returned by `GET /public_key/`:

```php
$service = app(\AIArmada\Chip\Services\WebhookService::class);

abort_unless($service->verifySuccessCallbackSignature($request), 400, 'Invalid signature');
```

## Built-in processing flow

When the built-in route is enabled, successful deliveries flow through these steps:

1. signature verification
2. deduplication and webhook-call storage
3. `WebhookReceived` dispatch
4. typed event dispatch through `WebhookEventDispatcher`
5. local model synchronization

The generic event is:

- `AIArmada\Chip\Events\WebhookReceived`

Typed events include:

- `PurchasePaid`
- `PurchaseCancelled`
- `PurchasePendingRefund`
- `PaymentRefunded`
- payout events

If `chip.webhooks.store_webhooks` is enabled, `AIArmada\Chip\Listeners\StoreWebhookData` persists purchase payloads plus purchase-related payment payloads such as `payment.refunded`.

The typed events emitted by `WebhookEventDispatcher` are the integration seam for downstream packages. `PurchaseEvent` and payment events expose stable IDs, amounts, currencies, statuses, customer details, references, metadata, and the original payload. CHIP does not generate documents or link checkout customers; those subscribers belong to their owning packages.

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

## Alternative handler: DispatchChipWebhookAction

Use `DispatchChipWebhookAction` when you want to programmatically dispatch a webhook event from non-HTTP surfaces (queued jobs, console commands, tests) while still benefiting from owner-scoped routing:

```php
use AIArmada\Chip\Actions\DispatchChipWebhookAction;

$result = app(DispatchChipWebhookAction::class)->execute(
    event: 'purchase.paid',
    payload: ['id' => 'purchase_abc123', 'status' => 'paid'],
    owner: $tenant, // optional — resolved from payload brand_id when omitted
);

if ($result->wasHandled()) {
    // Event was routed to a handler
}
```

The action enriches the payload, resolves owner context from the payload or provided model, and routes through the same `WebhookRouter` used by the built-in HTTP controller.

## CHIP Send webhooks

CHIP Send has a separate webhook API and signature scheme from CHIP Collect. The package registers a second route at `config('chip.webhooks.send.route', '/chip/send/webhooks')`:

- controller: `AIArmada\Chip\Http\Controllers\SendWebhookController`
- route name: `chip.send.webhook`
- event: `AIArmada\Chip\Events\SendWebhookReceived`

The controller verifies the raw request body with the Send webhook's dedicated public key and SHA-512 RSA signature, then dispatches the raw JSON object as `SendWebhookReceived::$payload`. CHIP Send identifies the subscribed delivery through the configured `event_hooks` resource type (`bank_account_status`, `budget_allocation_status`, or `send_instruction_status`); it does not use the Collect `event_type` envelope. Applications can interpret the verified resource payload according to the hook they configured.

Configure the Send webhook public key directly or let the package retrieve it using `CHIP_SEND_WEBHOOK_ID`:

```dotenv
CHIP_SEND_WEBHOOK_ID=123
CHIP_SEND_WEBHOOK_ROUTE=/chip/send/webhooks
```

For multiple Send webhooks, use `CHIP_SEND_WEBHOOK_PUBLIC_KEYS` as a JSON object keyed by the integer webhook ID. The route returns HTTP 200 only after signature and JSON-object validation; a non-2xx response allows CHIP to retry the delivery.

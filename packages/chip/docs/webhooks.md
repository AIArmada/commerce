---
title: Webhooks
---

# Webhooks

Handle CHIP payment and disbursement events.

## Configuration

```env
CHIP_COMPANY_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----..."
CHIP_WEBHOOK_VERIFY_SIGNATURE=true
```

```php
// config/chip.php
'webhooks' => [
    'company_public_key' => env('CHIP_COMPANY_PUBLIC_KEY'),
    'webhook_keys' => [], // Per-webhook keys if needed
    'verify_signature' => env('CHIP_WEBHOOK_VERIFY_SIGNATURE', true),
],
```

## Gateway Handler

```php
use AIArmada\Chip\Gateways\ChipGateway;

Route::post('/webhooks/chip', function (Request $request) {
    $gateway = app(ChipGateway::class);
    $handler = $gateway->getWebhookHandler();
    
    $payload = $handler->verify($request);
    
    match ($payload->event) {
        'purchase.paid' => handlePurchasePaid($payload),
        'purchase.cancelled' => handlePurchaseCancelled($payload),
        'payment.failed' => handlePaymentFailed($payload),
        default => null,
    };
    
    return response('OK');
});
```

## WebhookService

```php
use AIArmada\Chip\Services\WebhookService;

$service = app(WebhookService::class);

// Verify signature
$isValid = $service->verifySignature(
    payload: $request->getContent(),
    signature: $request->header('X-Signature'),
    publicKey: config('chip.webhooks.company_public_key'),
);
```

## Webhook Data Object

```php
use AIArmada\Chip\Data\WebhookData;

$webhook = WebhookData::from($request->all());

$webhook->event;           // 'purchase.paid'
$webhook->data;            // Event payload
$webhook->timestamp;       // ISO8601
$webhook->verified;        // Signature valid
$webhook->getPurchase();   // Purchase object (if applicable)
```

## Collect Events

| Event | Description |
|-------|-------------|
| `purchase.created` | Purchase created |
| `purchase.paid` | Payment completed |
| `purchase.cancelled` | Purchase cancelled |
| `purchase.refunded` | Refund processed |
| `payment.created` | Payment attempt started |
| `payment.paid` | Payment confirmed |
| `payment.failed` | Payment attempt failed |

### Handle Purchase Paid

```php
function handlePurchasePaid($payload): void
{
    $purchase = $payload->getPurchase();
    
    $order = Order::where('payment_reference', $purchase->id)->first();
    
    if ($order && !$order->isPaid()) {
        $order->markAsPaid();
        $order->notify(new OrderConfirmation());
    }
}
```

## Send Events

| Event | Description |
|-------|-------------|
| `send_instruction.received` | Instruction received |
| `send_instruction.completed` | Transfer successful |
| `send_instruction.rejected` | Transfer failed |
| `bank_account.verified` | Account verified |
| `bank_account.rejected` | Account rejected |

### Handle Send Completed

```php
function handleSendCompleted($payload): void
{
    $data = $payload->data;
    
    $payout = Payout::where('reference', $data['reference'])->first();
    
    if ($payout) {
        $payout->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
```

## Manage Webhooks

### Collect Webhooks

```php
use AIArmada\Chip\Facades\Chip;

// Create
Chip::createWebhook([
    'url' => 'https://example.com/webhooks/chip',
    'events' => ['purchase.paid', 'purchase.cancelled'],
]);

// List
$webhooks = Chip::listWebhooks();

// Update
Chip::updateWebhook('wh_123', ['events' => ['purchase.paid']]);

// Delete
Chip::deleteWebhook('wh_123');
```

### Send Webhooks

```php
use AIArmada\Chip\Facades\ChipSend;

ChipSend::createSendWebhook([
    'url' => 'https://example.com/webhooks/chip-send',
    'events' => ['send_instruction.completed'],
]);

ChipSend::listSendWebhooks();
ChipSend::updateSendWebhook('wh_123', $data);
ChipSend::deleteSendWebhook('wh_123');
```

## Signature Verification

CHIP signs webhooks using RSA-SHA256. The signature is in the `X-Signature` header.

```php
// Manual verification
$publicKey = Chip::getPublicKey();

$isValid = openssl_verify(
    $request->getContent(),
    base64_decode($request->header('X-Signature')),
    $publicKey,
    OPENSSL_ALGO_SHA256
) === 1;
```

## Retry Webhooks

```php
// Resend purchase webhook
Chip::resendInvoice('pur_123');

// Resend send instruction webhook
ChipSend::resendSendInstructionWebhook('inst_123');

// Resend bank account webhook
ChipSend::resendBankAccountWebhook('bank_123');
```

## Testing

```php
it('handles purchase.paid webhook', function () {
    $payload = [
        'event' => 'purchase.paid',
        'data' => [
            'id' => 'pur_test123',
            'status' => 'paid',
            'purchase' => ['total' => 9900, 'currency' => 'MYR'],
        ],
    ];
    
    $response = $this->postJson('/webhooks/chip', $payload);
    
    $response->assertOk();
    expect(Order::find(1)->status)->toBe('paid');
});
```

## Next Steps

- [API Reference](api-reference.md) – Complete methods
- [Payment Gateway](payment-gateway.md) – Gateway interface

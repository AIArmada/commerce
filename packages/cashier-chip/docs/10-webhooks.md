---
title: Webhooks
---

# Webhooks

Cashier CHIP handles incoming CHIP webhooks to update payment statuses, save recurring tokens, and manage subscription states.

## Webhook Route

The `aiarmada/chip` package registers a webhook route at:

```
POST /chip/webhooks
```

Configure your CHIP dashboard to send webhooks to this URL. Cashier CHIP
registers no webhook route or controller of its own; it subscribes to the
typed events CHIP dispatches from that route (see Handled Events below).

## Configuration

### CSRF Protection

Exclude the webhook route from CSRF verification:

```php
// bootstrap/app.php (Laravel 11+)
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'chip/*',
    ]);
})
```

### Webhook Secret

Configure the webhook secret in your `.env`:

```env
CHIP_WEBHOOK_SECRET=your-webhook-secret
```

### Signature Verification

Enable/disable signature verification:

```php
// config/cashier-chip.php (stored for display; verification lives in the chip package)
'webhooks' => [
    'secret' => env('CHIP_WEBHOOK_SECRET'),
],

// config/chip.php (the chip package owns signature verification)
'webhooks' => [
    'verify_signature' => true,  // Set to false for testing
],
```

## Handled Events

The package subscribes to these typed CHIP events:

| CHIP event | Listener | Description |
|-------|---------|-------------|
| `purchase.paid` | `HandlePurchasePaid` | Payment completed; syncs status and renewals |
| `purchase.payment_failure` | `HandlePurchasePaymentFailure` | Payment failed |
| `purchase.preauthorized` | `HandlePurchasePreauthorized` | Preauthorization complete (setup purchases) |

The listeners resolve the billable from the purchase's CHIP client ID and
run `SyncChipPurchaseStatus`, which dispatches the Cashier CHIP events below.

## Events Dispatched

Each handled webhook dispatches Laravel events you can listen for:

### Payment Events

```php
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentRefunded;

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

### Subscription Events

```php
use AIArmada\CashierChip\Events\SubscriptionCreated;
use AIArmada\CashierChip\Events\SubscriptionRenewed;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
```

## Custom Webhook Handling

Cashier CHIP ships no webhook controller to extend. Add custom handling
with your own listeners on the Cashier CHIP events above (or on the
underlying `AIArmada\Chip\Events\PurchasePaid` and related CHIP events):

```php
use AIArmada\CashierChip\Events\PaymentSucceeded;

class NotifyTeamOfPayment
{
    public function handle(PaymentSucceeded $event): void
    {
        $purchaseId = $event->purchase['id'];

        $this->notifyTeam($purchaseId);
    }
}
```

To customize the HTTP route itself (path, middleware), configure the
`aiarmada/chip` webhook route instead; see the
[CHIP webhooks documentation](../../chip/docs/09-webhooks.md).

## Payload Structure

CHIP webhooks contain this structure:

All monetary amounts are integers in the smallest currency unit (for MYR, this is cents).

```json
{
    "event_type": "purchase.paid",
    "id": "purchase-uuid",
    "client_id": "client-uuid",
    "status": "paid",
    "is_recurring_token": true,
    "recurring_token": "tok_xxxxx",
    "purchase": {
        "total": 10000,
        "currency": "MYR",
        "products": [
            {
                "name": "Product Name",
                "price": 10000,
                "quantity": 1
            }
        ]
    }
}
```

## Accessing Webhook Data

In your event listener:

```php
class HandlePaymentSuccess
{
    public function handle(PaymentSucceeded $event): void
    {
        $purchase = $event->purchase;
        $billable = $event->billable;

        // Access purchase data
        $purchaseId = $purchase['id'];
        $amount = $purchase['purchase']['total'];
        
        // Access billable (user)
        if ($billable) {
            $billable->notify(new PaymentReceived($amount));
        }
    }
}
```

## Testing Webhooks

### Local Development

Use a tunnel service like ngrok:

```bash
ngrok http 8000
```

Configure the ngrok URL in your CHIP dashboard.

### Faking Webhooks

```php
use AIArmada\CashierChip\Billing\Cashier;

Cashier::fake();

// Now all CHIP API calls are faked
$user->charge(10000);

// Simulate a webhook
$response = $this->postJson('/chip/webhooks', [
    'event_type' => 'purchase.paid',
    'id' => 'purchase-123',
    'status' => 'paid',
]);

$response->assertOk();
```

### Disabling Signature Verification

For testing:

```php
// config/chip.php
'webhooks' => [
    'verify_signature' => env('CHIP_WEBHOOK_VERIFY_SIGNATURE', true),
],

// .env.testing
CHIP_WEBHOOK_VERIFY_SIGNATURE=false
```

## Webhook Queues

The package listeners run synchronously. For high-volume applications,
queue your own heavy work from a listener instead of doing it inline:

```php
use AIArmada\CashierChip\Events\PaymentSucceeded;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessPaymentWebhook implements ShouldQueue
{
    public function handle(PaymentSucceeded $event): void
    {
        $this->processPayment($event->purchase);
    }
}
```

## Error Handling

### Listener Failures

Throwing from a queued listener releases the job back onto the queue for
retry with the queue's backoff policy. Log and skip events you cannot
handle instead of retrying forever:

```php
use Illuminate\Database\Eloquent\ModelNotFoundException;

public function handle(PaymentSucceeded $event): void
{
    try {
        $this->processPayment($event->purchase);
    } catch (ModelNotFoundException $e) {
        // Unknown local reference: log and skip instead of retrying forever.
        Log::warning('Webhook reference not found', ['id' => $event->purchase['id']]);
    }
}
```

### Logging

Enable webhook logging from a listener:

```php
use AIArmada\CashierChip\Events\PaymentSucceeded;

class LogChipWebhook
{
    public function handle(PaymentSucceeded $event): void
    {
        Log::channel('webhooks')->info('CHIP webhook received', [
            'id' => $event->purchase['id'] ?? null,
        ]);
    }
}
```

## Webhook Security

1. **Always verify signatures** in production
2. Use HTTPS for webhook endpoints
3. Validate payload structure before processing
4. Store webhook secret securely (environment variable)
5. Log webhook activity for debugging

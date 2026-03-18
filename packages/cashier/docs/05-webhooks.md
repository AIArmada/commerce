# Webhooks

This guide covers webhook handling for payment gateway events.

## Overview

Webhooks allow payment gateways to notify your application about events like:

- Successful payments
- Failed payments
- Subscription renewals
- Subscription cancellations
- Invoice creation

## Configuration

### Environment Variables

```env
# Stripe webhook secret
STRIPE_WEBHOOK_SECRET=whsec_xxx

# CHIP webhook key
CHIP_WEBHOOK_KEY=your_webhook_key
```

### Webhook Endpoints

Default webhook routes (can be customized in config):

| Gateway | Endpoint |
|---------|----------|
| Stripe | `/cashier/webhook/stripe` |
| CHIP | `/cashier/webhook/chip` |

## Gateway-Specific Setup

### Stripe Webhooks

1. Go to [Stripe Dashboard > Developers > Webhooks](https://dashboard.stripe.com/webhooks)
2. Add endpoint: `https://yourdomain.com/cashier/webhook/stripe`
3. Select events to listen to:
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`

4. Copy the signing secret to your `.env`:
   ```env
   STRIPE_WEBHOOK_SECRET=whsec_xxx
   ```

### CHIP Webhooks

1. Go to CHIP Dashboard > Webhooks
2. Add endpoint: `https://yourdomain.com/cashier/webhook/chip`
3. Copy the webhook key to your `.env`:
   ```env
   CHIP_WEBHOOK_KEY=your_webhook_key
   ```

## Event Dispatching

Webhooks dispatch unified events that you can listen to:

### Payment Events

```php
use AIArmada\Cashier\Events\PaymentSucceeded;
use AIArmada\Cashier\Events\PaymentFailed;
use AIArmada\Cashier\Events\PaymentRefunded;

// In EventServiceProvider
protected $listen = [
    PaymentSucceeded::class => [
        Listeners\SendPaymentReceipt::class,
        Listeners\FulfillOrder::class,
    ],
    PaymentFailed::class => [
        Listeners\NotifyPaymentFailure::class,
        Listeners\RetryPayment::class,
    ],
    PaymentRefunded::class => [
        Listeners\ProcessRefund::class,
    ],
];
```

### Subscription Events

```php
use AIArmada\Cashier\Events\SubscriptionCreated;
use AIArmada\Cashier\Events\SubscriptionUpdated;
use AIArmada\Cashier\Events\SubscriptionCanceled;
use AIArmada\Cashier\Events\SubscriptionRenewed;
use AIArmada\Cashier\Events\SubscriptionTrialEnding;

protected $listen = [
    SubscriptionCreated::class => [
        Listeners\SendWelcomeEmail::class,
        Listeners\GrantAccess::class,
    ],
    SubscriptionCanceled::class => [
        Listeners\SendCancellationSurvey::class,
        Listeners\RevokeAccess::class,
    ],
    SubscriptionRenewed::class => [
        Listeners\SendRenewalConfirmation::class,
    ],
    SubscriptionTrialEnding::class => [
        Listeners\SendTrialEndingReminder::class,
    ],
];
```

## Event Listeners

### Basic Listener

```php
<?php

namespace App\Listeners;

use AIArmada\Cashier\Events\PaymentSucceeded;

class SendPaymentReceipt
{
    public function handle(PaymentSucceeded $event): void
    {
        $payment = $event->payment();
        $gateway = $event->gateway();
        $billable = $event->billable();
        
        // Send receipt email
        Mail::to($billable->email)->send(
            new PaymentReceiptMail($payment)
        );
    }
}
```

### Gateway-Specific Handling

```php
<?php

namespace App\Listeners;

use AIArmada\Cashier\Events\SubscriptionCreated;

class HandleSubscriptionCreated
{
    public function handle(SubscriptionCreated $event): void
    {
        $subscription = $event->subscription();
        $gateway = $event->gateway();
        
        match ($gateway) {
            'stripe' => $this->handleStripe($subscription),
            'chip' => $this->handleChip($subscription),
            default => $this->handleDefault($subscription),
        };
    }
    
    private function handleStripe($subscription): void
    {
        // Stripe-specific logic
    }
    
    private function handleChip($subscription): void
    {
        // CHIP-specific logic
    }
}
```

## Webhook Security

### Signature Verification

Webhooks are automatically verified using the secrets in your config:

```php
// This happens automatically in the webhook handlers
// Throws WebhookVerificationException if signature is invalid
```

### CSRF Protection

Webhook routes are automatically excluded from CSRF protection. If you've customized your routes, ensure they're excluded:

```php
use Illuminate\Foundation\Configuration\Middleware;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: [
        'cashier/webhook/*',
    ]);
})
```

## Custom Webhook Handlers

### Extending Default Handlers

```php
<?php

namespace App\Http\Controllers;

use AIArmada\Cashier\Http\Controllers\WebhookController;
use Illuminate\Http\Request;

class CustomStripeWebhookController extends WebhookController
{
    protected function handleCustomerCreated(array $payload): void
    {
        // Handle customer.created event
    }
    
    protected function handleInvoiceCreated(array $payload): void
    {
        // Handle invoice.created event
    }
}
```

### Registering Custom Handler

```php
// In routes/web.php
Route::post('cashier/webhook/stripe', [CustomStripeWebhookController::class, 'handleWebhook']);
```

## Error Handling

### Webhook Exceptions

```php
use AIArmada\Cashier\Exceptions\WebhookVerificationException;

// In your exception handler
public function render($request, Throwable $exception)
{
    if ($exception instanceof WebhookVerificationException) {
        Log::error('Webhook verification failed', [
            'gateway' => $exception->gateway(),
            'message' => $exception->getMessage(),
        ]);
        
        return response('Invalid signature', 400);
    }
    
    return parent::render($request, $exception);
}
```

### Logging Webhook Events

```php
<?php

namespace App\Listeners;

use AIArmada\Cashier\Events\WebhookReceived;

class LogWebhookEvent
{
    public function handle(WebhookReceived $event): void
    {
        Log::info('Webhook received', [
            'gateway' => $event->gateway,
            'type' => $event->payload['type'] ?? 'unknown',
            'timestamp' => now(),
        ]);
    }
}
```

## Testing Webhooks

### Local Development

Use tools like ngrok to expose your local server:

```bash
ngrok http 8000
```

Then use the ngrok URL in your gateway's webhook settings.

### Stripe CLI

```bash
# Install Stripe CLI
brew install stripe/stripe-cli/stripe

# Login
stripe login

# Forward webhooks to local
stripe listen --forward-to localhost:8000/cashier/webhook/stripe
```

### Unit Testing

```php
use AIArmada\Cashier\Events\SubscriptionCreated;

it('handles subscription created webhook', function () {
    Event::fake();
    
    $user = createUser();
    $subscription = createSubscription($user);
    
    event(new SubscriptionCreated($subscription, $user));
    
    Event::assertDispatched(SubscriptionCreated::class, function ($event) use ($subscription) {
        return $event->subscription()->id === $subscription->id;
    });
});
```

## Best Practices

### 1. Respond Quickly

Webhook handlers should return 200 quickly. Queue heavy processing:

```php
class SendPaymentReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    
    public function handle(PaymentSucceeded $event): void
    {
        // This runs in a queue worker
    }
}
```

### 2. Idempotency

Webhooks may be delivered multiple times. Make handlers idempotent:

```php
public function handle(PaymentSucceeded $event): void
{
    $payment = $event->payment();
    
    // Check if already processed
    if (Order::where('payment_id', $payment->id())->exists()) {
        return;
    }
    
    // Process payment
    Order::create([
        'payment_id' => $payment->id(),
        // ...
    ]);
}
```

### 3. Store Raw Webhooks

For debugging and compliance:

```php
class LogRawWebhook
{
    public function handle(WebhookReceived $event): void
    {
        WebhookLog::create([
            'gateway' => $event->gateway,
            'payload' => $event->payload,
            'processed_at' => now(),
        ]);
    }
}
```

### 4. Handle All States

Ensure your listeners handle edge cases:

```php
public function handle(SubscriptionUpdated $event): void
{
    $subscription = $event->subscription();
    
    if ($subscription->ended()) {
        $this->revokeAccess();
    } elseif ($subscription->pastDue()) {
        $this->sendPaymentReminder();
    } elseif ($subscription->active()) {
        $this->ensureAccess();
    }
}
```

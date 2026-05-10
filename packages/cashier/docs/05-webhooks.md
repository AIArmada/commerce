---
title: Webhooks
---

# Webhooks

This guide covers how `aiarmada/cashier` fits into webhook handling across multiple gateways.

## Important Architecture Note

`aiarmada/cashier` does **not** own the concrete Stripe or CHIP webhook endpoints.

Instead:

- `laravel/cashier` owns the Stripe webhook controller / route
- `aiarmada/cashier-chip` owns the CHIP webhook flow
- `aiarmada/cashier` listens at the **unified event layer** and dispatches cross-gateway events such as `PaymentSucceeded` and `SubscriptionCreated`

That means you configure gateway webhooks in the underlying packages, then listen to unified events here.

## Configuration

### Environment Variables

```env
STRIPE_WEBHOOK_SECRET=whsec_xxx
CHIP_WEBHOOK_SECRET=your_webhook_secret
```

## Default Endpoints

Default webhook routes depend on the installed gateway packages:

| Gateway | Owner | Default endpoint |
|---------|-------|------------------|
| Stripe | `laravel/cashier` | `/stripe/webhook` |
| CHIP | `aiarmada/cashier-chip` | `/chip/webhook` |

If you customize the path in those packages, update your gateway dashboard to match.

## Gateway-Specific Setup

### Stripe Webhooks

1. Go to [Stripe Dashboard > Developers > Webhooks](https://dashboard.stripe.com/webhooks)
2. Add endpoint: `https://yourdomain.com/stripe/webhook`
3. Select the events your app needs, for example:
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
4. Copy the signing secret into `.env`:

   ```env
   STRIPE_WEBHOOK_SECRET=whsec_xxx
   ```

### CHIP Webhooks

1. Go to the CHIP dashboard
2. Add endpoint: `https://yourdomain.com/chip/webhook`
3. Copy the webhook secret into `.env`:

   ```env
   CHIP_WEBHOOK_SECRET=your_webhook_secret
   ```

## Unified Events

Once the gateway packages receive and verify their webhooks, `aiarmada/cashier` can dispatch
gateway-agnostic events that your application listens to.

### Payment Events

```php
use AIArmada\Cashier\Events\PaymentFailed;
use AIArmada\Cashier\Events\PaymentRefunded;
use AIArmada\Cashier\Events\PaymentSucceeded;

protected $listen = [
    PaymentSucceeded::class => [
        Listeners\SendPaymentReceipt::class,
        Listeners\FulfillOrder::class,
    ],
    PaymentFailed::class => [
        Listeners\NotifyPaymentFailure::class,
    ],
    PaymentRefunded::class => [
        Listeners\ProcessRefund::class,
    ],
];
```

### Subscription Events

```php
use AIArmada\Cashier\Events\SubscriptionCanceled;
use AIArmada\Cashier\Events\SubscriptionCreated;
use AIArmada\Cashier\Events\SubscriptionRenewed;
use AIArmada\Cashier\Events\SubscriptionTrialEnding;
use AIArmada\Cashier\Events\SubscriptionUpdated;

protected $listen = [
    SubscriptionCreated::class => [
        Listeners\GrantAccess::class,
    ],
    SubscriptionCanceled::class => [
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

## Writing Listeners

### Payment Listener

```php
use AIArmada\Cashier\Events\PaymentSucceeded;

class SendPaymentReceipt
{
    public function handle(PaymentSucceeded $event): void
    {
        $payment = $event->payment();
        $gateway = $event->gateway();
        $billable = $event->billable();

        // Send a receipt using the unified payment contract.
    }
}
```

### Gateway-Aware Listener

```php
use AIArmada\Cashier\Events\SubscriptionCreated;

class HandleSubscriptionCreated
{
    public function handle(SubscriptionCreated $event): void
    {
        $subscription = $event->subscription();

        match ($event->gateway()) {
            'stripe' => $this->handleStripe($subscription),
            'chip' => $this->handleChip($subscription),
            default => null,
        };
    }
}
```

## Security

### Signature Verification

Signature verification stays in the gateway packages and happens automatically when configured.

If verification fails, those packages may throw `WebhookVerificationException` or their own
gateway-specific exception before your application logic runs.

### CSRF Protection

Webhook routes should be excluded from CSRF protection if they are not already handled for you:

```php
use Illuminate\Foundation\Configuration\Middleware;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: [
        'stripe/*',
        'chip/*',
    ]);
})
```

## Customizing Webhook Controllers

Customize the **gateway package**, not `aiarmada/cashier` itself.

### Stripe

`laravel/cashier` exposes a real webhook controller you can extend:

```php
namespace App\Http\Controllers;

use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class StripeWebhookController extends CashierWebhookController
{
    protected function handleInvoicePaymentSucceeded(array $payload)
    {
        // Custom Stripe-specific logic...

        return parent::handleInvoicePaymentSucceeded($payload);
    }
}
```

Then register your customized Stripe route:

```php
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);
```

### CHIP

For CHIP, customize the concrete webhook flow in `aiarmada/cashier-chip` / `aiarmada/chip`
according to those packages' documentation. `aiarmada/cashier` does not ship its own CHIP
webhook controller.

## Logging Webhook Activity

```php
use AIArmada\Cashier\Events\WebhookReceived;

class LogWebhookEvent
{
    public function handle(WebhookReceived $event): void
    {
        logger()->info('Webhook received', [
            'gateway' => $event->gateway(),
            'type' => $event->eventType(),
            'timestamp' => now(),
        ]);
    }
}
```

## Testing Webhooks

### Local Development

Use a tunnel such as ngrok to expose your local app:

```bash
ngrok http 8000
```

Then configure your gateway dashboard to call the public ngrok URL.

### Stripe CLI

```bash
brew install stripe/stripe-cli/stripe
stripe login
stripe listen --forward-to localhost:8000/stripe/webhook
```

### Testing Unified Events

```php
use AIArmada\Cashier\Events\SubscriptionCreated;

it('handles subscription created events', function () {
    Event::fake();

    $user = createUser();
    $subscription = createSubscription($user);

    event(new SubscriptionCreated($subscription, $user));

    Event::assertDispatched(SubscriptionCreated::class, function ($event) use ($subscription) {
        return $event->subscription()->id() === $subscription->id();
    });
});
```

## Best Practices

### 1. Keep Handlers Fast

Queue heavy work from event listeners instead of doing it inline.

### 2. Make Processing Idempotent

Webhook deliveries can be retried. Check whether a payment or subscription change has already been processed before acting on it again.

### 3. Store Raw Payloads When Needed

Persist raw webhook payloads if you need audit trails, reconciliation, or easier debugging.

### 4. Prefer Unified Events for Application Logic

Use the gateway packages to receive webhooks, but prefer `aiarmada/cashier` events for your domain logic when the behavior should work across gateways.

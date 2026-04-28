---
title: Payment Flow
---

# Payment Flow

The checkout package provides complete payment redirect handling, including callbacks from payment gateways and webhook processing.

## Payment Flow Overview

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Checkout  │────▶│   Payment   │────▶│   Gateway   │
│   Started   │     │   Redirect  │     │   Payment   │
└─────────────┘     └─────────────┘     └──────┬──────┘
                                               │
                          ┌────────────────────┼────────────────────┐
                          │                    │                    │
                          ▼                    ▼                    ▼
                   ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
                   │   Success   │     │   Failure   │     │   Cancel    │
                   │  Callback   │     │  Callback   │     │  Callback   │
                   └──────┬──────┘     └──────┬──────┘     └──────┬──────┘
                          │                    │                    │
                          ▼                    ▼                    ▼
                   ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
                   │    Order    │     │   Payment   │     │   Checkout  │
                   │   Created   │     │   Failed    │     │  Cancelled  │
                   └─────────────┘     └─────────────┘     └─────────────┘
```

## Payment Redirect

When processing payment, the checkout service will redirect to the payment gateway if needed:

```php
$result = Checkout::processCheckout($session);

if ($result->requiresRedirect()) {
    // User will be redirected to payment gateway
    return redirect($result->redirectUrl);
}
```

The redirect URL includes the checkout session ID as a reference, allowing the gateway to return the user to the correct session.

## Callback Routes

The package registers routes for handling payment gateway callbacks:

| Route | Name | Purpose |
|-------|------|---------|
| `GET /checkout/payment/success` | `checkout.payment.success` | Successful payment return |
| `GET /checkout/payment/failure` | `checkout.payment.failure` | Failed payment return |
| `GET /checkout/payment/cancel` | `checkout.payment.cancel` | Cancelled payment return |
| `POST /webhooks/checkout` | `checkout.webhook` | Webhook notifications |

### Route Configuration

Configure routes in `config/checkout.php`:

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'checkout',
    'middleware' => ['web'],
    'webhook_prefix' => 'webhooks',
    'webhook_middleware' => ['api'],
],
```

### Redirect Configuration

Configure where users are redirected after payment:

```php
'redirects' => [
    'success' => '/orders/{order_id}',
    'failure' => '/checkout/failed',
    'cancel' => '/checkout/cancelled',
],
```

Placeholders available:
- `{order_id}` - The created order ID
- `{session_id}` - The checkout session ID

## Success Callback

When a user returns from a successful payment, the `PaymentCallbackController::success()` handler:

1. Resolves the checkout session from the query parameter
2. If already completed, redirects to the order success page
3. Verifies payment with the gateway
4. Completes the checkout if verification passes
5. Redirects to success or failure based on result

```php
// The controller handles this automatically, but you can also:
$result = Checkout::handlePaymentCallback($session, 'success');

if ($result->success) {
    return redirect()->route('orders.show', $result->orderId);
}
```

## Failure Callback

When payment fails at the gateway:

1. Session is transitioned to `PaymentFailed` state
2. User is redirected to the failure URL
3. Error message is flashed to session

The user can retry payment:

```php
if ($session->status->canRetryPayment()) {
    $result = Checkout::retryPayment($session);
}
```

## Cancel Callback

When user cancels at the gateway:

1. Session is transitioned to `Cancelled` state
2. User is redirected to the cancel URL
3. Session ID and message are flashed

## Webhook Handling

For asynchronous payment confirmation, use the webhook endpoint:

### Webhook Request Format

The webhook controller extracts the session ID from various payload formats:

**CHIP format:**
```json
{
    "reference": "checkout-session-uuid",
    "status": "paid"
}
```

**Stripe format:**
```json
{
    "data": {
        "object": {
            "client_reference_id": "checkout-session-uuid",
            "status": "complete"
        }
    }
}
```

**Metadata format:**
```json
{
    "metadata": {
        "checkout_session_id": "checkout-session-uuid"
    },
    "status": "succeeded"
}
```

### Webhook Response

The webhook returns JSON with the processing result:

```json
{
    "status": "success",
    "checkout_completed": true,
    "order_id": "order-uuid"
}
```

Possible status values:
- `success` - Payment processed, checkout completed
- `processed` - Payment handled but checkout not completed
- `acknowledged` - Webhook received but no action needed
- `ignored` - Session not found or invalid state

### Idempotent Handling

Both callbacks and webhooks handle idempotent completion:

1. If session is already `Completed`, respond with acknowledgment
2. If payment verification passes, complete checkout
3. Multiple webhook calls won't duplicate orders

## Building Callback URLs

When integrating with payment gateways, get the callback URLs:

```php
use Illuminate\Support\Facades\URL;

$successUrl = route('checkout.payment.success', ['session' => $session->id]);
$failureUrl = route('checkout.payment.failure', ['session' => $session->id]);
$cancelUrl = route('checkout.payment.cancel', ['session' => $session->id]);

// Or with absolute URLs for external gateways
$successUrl = URL::signedRoute('checkout.payment.success', ['session' => $session->id]);
```

## Webhook Security

For production, implement webhook signature verification:

```php
// In a custom middleware
class VerifyWebhookSignature
{
    public function handle($request, Closure $next)
    {
        $signature = $request->header('X-Signature');
        $payload = $request->getContent();
        
        if (!$this->verifySignature($payload, $signature)) {
            abort(401, 'Invalid signature');
        }
        
        return $next($request);
    }
}
```

Configure webhook middleware in `config/checkout.php`:

```php
'routes' => [
    'webhook_middleware' => ['api'], // Signature verification is handled by the checkout webhook signature validator
],
```

When an incoming webhook carries an event ID that has already been processed for the same event type, checkout returns:

```json
{
    "status": "acknowledged",
    "reason": "duplicate_event"
}
```

## State Transitions During Payment

```
Processing ─────────────▶ AwaitingPayment ◀─────────┐
     │                          │                    │
     │                          │                    │
     │                    ┌─────┴─────┐              │
     │                    ▼           ▼              │
     │            PaymentProcessing  Cancelled       │
     │                    │                          │
     │              ┌─────┴─────┐                    │
     │              ▼           ▼                    │
     │         Completed   PaymentFailed ───────────┘
     │                          (retry)
     │
     └──────────▶ PaymentProcessing ──────▶ Completed
                         │
                         ▼
                   PaymentFailed
```

## Error Handling

Handle payment callback errors gracefully:

```php
use AIArmada\Checkout\Exceptions\PaymentException;

try {
    $result = Checkout::handlePaymentCallback($session, 'success');
} catch (PaymentException $e) {
    Log::error('Payment callback error', [
        'session_id' => $session->id,
        'error' => $e->getMessage(),
    ]);
    
    return redirect()
        ->route('checkout.retry', $session)
        ->with('error', 'Payment verification failed. Please try again.');
}
```

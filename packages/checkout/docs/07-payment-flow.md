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

The redirect URL includes the configured session query parameter (default `session`) plus a per-session `checkout_callback_token`. `ProcessPaymentStep` preserves that callback token across retries by merging new gateway payload data into the existing `payment_data` instead of overwriting it.

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

1. Resolves the checkout session using the configured session query parameter (or `checkout_session_id` as a fallback)
2. Performs an intentional cross-tenant lookup because gateway redirects do not carry owner context
3. Guards that lookup with `hash_equals()` against the per-session callback token stored in `payment_data.callback_token`
4. Wraps the callback in a database transaction and locks the session row with `lockForUpdate()` to avoid duplicate completion races
5. Short-circuits straight to the success response if the session is already `Completed`
6. Verifies payment with the gateway and redirects or renders based on the configured response mode

```php
// The controller handles this automatically, but you can also:
$result = Checkout::handlePaymentCallback($session, 'success');

if ($result->success) {
    return redirect()->route('orders.show', $result->orderId);
}
```

## Failure Callback

When payment fails at the gateway:

1. The callback resolves and token-validates the checkout session inside the same transaction-safe path as success callbacks
2. Failure handling only runs when the session is still in a pending-like state (`Pending`, `AwaitingPayment`, `PaymentProcessing`, or `Processing`)
3. Already-completed sessions short-circuit to the success response instead of mutating the session backward
4. The user is redirected to the failure URL and the error message is flashed to session

The user can retry payment:

```php
if ($session->status->canRetryPayment()) {
    $result = Checkout::retryPayment($session);
}
```

## Cancel Callback

When user cancels at the gateway:

1. The callback uses the same transaction + token validation path as success and failure
2. Cancellation is only processed while the session is still pending-like
3. Completed sessions short-circuit to the success response
4. Otherwise the user is redirected to the cancel URL and the session ID/message are flashed

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

1. Payment callbacks wrap session resolution and mutation in a database transaction
2. The session row is locked with `lockForUpdate()` before state-changing callback logic runs
3. If the session is already `Completed`, callbacks short-circuit instead of re-processing the order
4. Failure and cancel callbacks do nothing once the session has moved past the pending-like states
5. Multiple webhook or callback deliveries therefore do not duplicate orders or regress completed sessions

## Building Callback URLs

When integrating with payment gateways, get the callback URLs:

```php
$sessionParam = config('checkout.defaults.session_query_param', 'session');
$callbackToken = $session->payment_data['callback_token'] ?? null;

$successUrl = route('checkout.payment.success', [
    $sessionParam => $session->id,
    'checkout_callback_token' => $callbackToken,
]);

$failureUrl = route('checkout.payment.failure', [
    $sessionParam => $session->id,
    'checkout_callback_token' => $callbackToken,
]);

$cancelUrl = route('checkout.payment.cancel', [
    $sessionParam => $session->id,
    'checkout_callback_token' => $callbackToken,
]);
```

Package-generated URLs use `checkout_callback_token`; the callback controller also accepts `callback_token` and `token` aliases when a gateway renames the query parameter on return.

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

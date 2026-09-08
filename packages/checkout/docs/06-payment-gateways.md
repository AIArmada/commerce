---
title: Payment Gateways
---

# Payment Gateways

The checkout package supports multiple payment gateways through the `PaymentGatewayResolver`.

## Supported Gateways

| Gateway | Package | Description |
|---------|---------|-------------|
| `cashier` | `aiarmada/cashier` | Unified payment gateway |
| `cashier-chip` | `aiarmada/cashier-chip` | CHIP billing bridge that uses billable `charge()` flows first and falls back to guest CHIP purchases |
| `chip` | `aiarmada/chip` | Direct Chip integration |

## Gateway Resolution

At runtime the resolver uses one documented order: an explicitly requested
gateway, `payment.default_gateway` when that processor is registered,
`payment.gateway_priority`, then the first registered processor. The service
provider reads both config values directly, so config is the precedence source.

```php
// config/checkout.php
'payment' => [
    'default_gateway' => 'chip',
    'gateway_priority' => ['cashier', 'cashier-chip', 'chip'],
],
```

The first available gateway in that order is used unless a specific gateway is
requested. The payment attempt is incremented atomically and cannot exceed
`payment.retry_limit` before a new provider purchase is created.

## Cashier CHIP Bridge Behavior

The `cashier-chip` processor now bridges checkout, customers, and CHIP billing in two stages:

1. `ResolveCustomerStep` resolves any existing customer or billable subject before payment and writes that subject onto the checkout session when one already exists.
2. `ProcessPaymentStep` builds the `PaymentRequest` from `billing_data`, then falls back to the resolved customer or billable model when direct billing payload fields are missing.
3. After successful payment, checkout enters a post-payment phase. When inventory is configured to reserve after payment, `ReserveInventoryStep` runs first; `PersistCustomerStep` then creates or syncs the `Customer` for guest/direct-capable flows before checkout creates the order.

`CashierChipProcessor` then chooses the payment path:

- if the resolved billable subject exposes `charge()`, checkout uses the Billable payment flow from `aiarmada/cashier-chip`
- otherwise checkout falls back to a direct guest CHIP purchase using the normalized request payload

This keeps authenticated billing flows and guest checkout flows on the same processor without requiring separate gateway selection logic.

## Cashier Pre-Payment Requirement

The generic `cashier` processor still requires a persisted, chargeable billable subject before payment is created. Checkout therefore preserves the pre-payment customer materialization path for that gateway while deferring guest/direct customer creation for gateways that can pay from normalized checkout payload data.

## Forcing a Gateway

### Via Config

```php
'payment' => [
    'default_gateway' => 'chip',
],
```

### Via Payment Request

```php
$request = new PaymentRequest(
    amount: 10000,
    currency: 'MYR',
    gateway: 'cashier-chip', // Force specific gateway
);
```

## Payment Flow

### Standard Flow

```
┌─────────┐    ┌──────────┐    ┌─────────┐    ┌─────────┐
│ Checkout│───▶│ Gateway  │───▶│ Payment │───▶│ Complete│
│ Start   │    │ Resolver │    │ Process │    │ Order   │
└─────────┘    └──────────┘    └─────────┘    └─────────┘
```

### Redirect Flow (3D Secure, FPX)

```
┌─────────┐    ┌──────────┐    ┌──────────┐    ┌─────────┐
│ Checkout│───▶│ Payment  │───▶│ Redirect │───▶│ Webhook │
│ Start   │    │ Gateway  │    │ Customer │    │ Confirm │
└─────────┘    └──────────┘    └──────────┘    └─────────┘
                                    │
                               ┌────▼────┐
                               │ Complete│
                               │ Checkout│
                               └─────────┘
```

In redirect flows, checkout stores a callback token and
`payment_data.callback_token_created_at` before creating the payment and
preserves it across retries. The callback routes use that token to reject
forged or expired gateway returns, rate-limit attempts per session, and consume
the token after a successful completion. A completed session may safely replay
the same return URL without re-running payment confirmation.

Provider references sent to checkout are namespaced as `chk_<session-uuid>`.
The selected gateway is also recorded in `payment_data.gateway` and the
`checkout_gateway` metadata field; webhook entry points require that gateway
match before acting.

## Payment Request

Create payment requests:

```php
use AIArmada\Checkout\Data\PaymentRequest;

$request = new PaymentRequest(
    amount: 10000,           // In cents (MYR 100.00)
    currency: 'MYR',         // ISO currency code
    gateway: null,           // Auto-select
    description: 'Order #123',
    customerEmail: 'customer@example.com',
    customerName: 'John Doe',
    customerPhone: '+60123456789',
    successUrl: route('checkout.payment.chip.success'),
    failureUrl: route('checkout.payment.chip.failure'),
    cancelUrl: route('checkout.payment.chip.cancel'),
    metadata: ['order_id' => 'order_123'],
);
```

Or from array:

```php
$request = PaymentRequest::fromArray([
    'amount' => 10000,
    'currency' => 'MYR',
    'customer_email' => 'customer@example.com',
    'metadata' => ['order_id' => 'order_123'],
]);
```

## Payment Result

Payment processing returns a `PaymentResult`:

```php
use AIArmada\Checkout\Data\PaymentResult;

// Success
$result = PaymentResult::success('pay_123', 'txn_456', 10000);
$result->isSuccessful(); // true
$result->paymentId;      // 'pay_123'
$result->transactionId;  // 'txn_456'
$result->amount;         // 10000

// Pending (requires redirect)
$result = PaymentResult::pending('pay_123', 'https://pay.gateway.com');
$result->requiresRedirect(); // true
$result->redirectUrl;        // 'https://pay.gateway.com'

// Failed
$result = PaymentResult::failed('Card declined', ['card' => 'invalid']);
$result->isSuccessful(); // false
$result->message;        // 'Card declined'
$result->errors;         // ['card' => 'invalid']
```

## Custom Payment Processor

Implement `PaymentProcessorInterface`:

```php
<?php

namespace App\Checkout\Payment;

use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Models\CheckoutSession;

class StripeProcessor implements PaymentProcessorInterface
{
    public function getIdentifier(): string
    {
        return 'stripe';
    }

    public function getName(): string
    {
        return 'Stripe';
    }

    public function isAvailable(CheckoutSession $session): bool
    {
        return ! empty(config('services.stripe.secret'));
    }

    public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
    {
        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $request->amount,
                'currency' => strtolower($request->currency),
                'customer' => $this->getStripeCustomer($request),
                'metadata' => $request->metadata,
            ]);

            if ($intent->status === 'requires_action') {
                return PaymentResult::pending($intent->id, $intent->next_action->redirect_to_url->url);
            }

            return PaymentResult::success(
                paymentId: $intent->id,
                transactionId: $intent->latest_charge,
                amount: $intent->amount
            );
        } catch (\Stripe\Exception\CardException $e) {
            return PaymentResult::failed($e->getMessage(), ['decline_code' => $e->getDeclineCode()]);
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        return PaymentResult::processing($payload['id'] ?? 'unknown');
    }

    public function getRedirectUrl(CheckoutSession $session): ?string
    {
        return $session->payment_redirect_url;
    }

    public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
    {
        // Implement refund logic
    }

    public function checkStatus(string $paymentId): PaymentResult
    {
        // Implement remote status lookup
    }
}
```

Register the processor:

```php
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;

public function boot(): void
{
    $resolver = app(PaymentGatewayResolverInterface::class);
    $resolver->register('stripe', new StripeProcessor());
}
```

Built-in processors (cashier, cashier-chip, chip) are registered by `RegisterBuiltInPaymentProcessors` during provider boot. To add a new built-in, extend that registrar instead of editing the provider.

## Webhook Handling

Checkout registers one explicit webhook route per gateway:

```php
// config/checkout.php
'routes' => [
    'webhook_prefix' => 'webhooks',
    'webhooks' => [
        'chip' => [
            'path' => 'chip',
            'config' => 'checkout.webhook.chip',
            'gateways' => ['chip', 'cashier-chip'],
        ],
        'stripe' => [
            'path' => 'stripe',
            'config' => 'checkout.webhook.stripe',
            'gateways' => ['cashier'],
        ],
    ],
],
```

Set `CHECKOUT_STRIPE_WEBHOOK_SECRET` for Stripe. Checkout does not read a
secret from Cashier; production boot fails closed when the checkout-owned
secret is missing.

The checkout package listens for payment completion events:

```php
use AIArmada\Checkout\Events\PaymentCompleted;
use AIArmada\Checkout\Facades\Checkout;
use AIArmada\Checkout\States\AwaitingPayment;
use Illuminate\Support\Facades\Event;

// Handle payment completed from gateway webhook
Event::listen(PaymentCompleted::class, function (PaymentCompleted $event): void {
    $session = $event->session;

    if ($session->status instanceof AwaitingPayment) {
        app(\AIArmada\Checkout\Contracts\CheckoutServiceInterface::class)->processCheckout($session);
    }
});
```

## Retrying Payments

```php
$result = Checkout::retryPayment($session);

if ($result->success) {
    return redirect()->route('orders.show', $result->orderId);
}

if ($result->requiresRedirect()) {
    return redirect($result->redirectUrl);
}

// Exceeded retry limit
if ($session->payment_attempts >= config('checkout.payment.retry_limit')) {
    return view('checkout.payment-failed', [
        'message' => 'Maximum retry attempts exceeded',
    ]);
}
```

## Testing Payments

Use test/sandbox modes:

```env
# Chip sandbox
CHIP_BRAND_ID=test_brand_id
CHIP_API_KEY=test_api_key
CHIP_SANDBOX=true

# Stripe test mode
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
```

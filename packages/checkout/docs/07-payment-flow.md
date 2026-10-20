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

## Payment Attempt Claim and Fencing

`payment_attempts` on the checkout session is the generation counter for
payment attempts. A separate monotonic `payment_attempt_revision`, stored in
`payment_data`, fences executions within a generation. A new claim, recovery
adoption, accepted gateway result, or verified callback advances the revision;
generation alone cannot distinguish an old execution from a newer recovery of
the same attempt.

Before any gateway call, the step claims exactly one attempt generation under the
session row lock and freezes the attempt identity in
`payment_data.payment_attempt`:

```json
{
    "number": 1,
    "gateway": "chip",
    "provider": null,
    "amount": 10000,
    "currency": "MYR",
    "reference": "chk_550e8400-e29b-41d4-a716-446655440000",
    "idempotency_key": "chk_550e8400-e29b-41d4-a716-446655440000:attempt:1",
    "payment_method": null,
    "claimed_at": "2026-10-18T01:00:00+00:00",
    "request": {
        "amount": 10000,
        "currency": "MYR",
        "gateway": "chip",
        "provider": null,
        "payment_method": null,
        "idempotency_key": "chk_550e8400-e29b-41d4-a716-446655440000:attempt:1",
        "billable_type": null,
        "billable_id": null,
        "billable_frozen": true,
        "client_frozen": true,
        "provider_customer_id": null,
        "provider_customer_frozen": true,
        "client": {
            "email": "buyer@example.com",
            "full_name": "Aisyah Rahman",
            "street_address": "12 Jalan Example",
            "city": "Kuala Lumpur",
            "country": "MY"
        }
    }
}
```

The `request` entry is the complete provider-relevant `PaymentRequest`
snapshot, built once by `PaymentRequestBuilder` under the claim lock. It also
carries the description, customer fields, callback URLs, and metadata; the
example is abridged. Later replays read this snapshot with
`PaymentRequest::fromSnapshot()` instead of rebuilding it from mutable session,
customer, billing, or billable state.

`billable_type` is the authorized concrete model class, not the stored morph
alias, so a later `Relation::morphMap()` change cannot redirect the replay.
The frozen model is owner-checked and then validated by the processor's own
contract; native `cashier-chip` billables do not need the unified Cashier
contract. `billable_frozen` marks the billable tuple as authoritative. A frozen
null tuple is an explicit guest, while a request that does not freeze a local
billable identity keeps using the live session billable. `provider_customer_id`
is the Stripe `stripe_id` or CHIP `chip_id` observed at claim time. When
`provider_customer_frozen` is true, a null id is an explicit decision and is
never replaced with the model's current id.

Only the claim owner performs gateway I/O. A concurrent `processCheckout()`, a
duplicate callback, or a stale retry observes the authoritative state instead of
creating a second purchase:

- completed session → replay success without gateway I/O
- recorded redirect for the current attempt → replay the redirect
- recorded failure or cancellation for the current attempt → start a new
  generation, subject to `payment.retry_limit`
- `pending`/`processing` outcome while the session is already `PaymentFailed` →
  start a new generation
- an in-flight attempt with no recorded outcome → report the session as
  processing; never re-drive the gateway
- cancelled or terminal session → quiet rejection with no I/O and no
  compensation

The gateway result is recorded under the row lock only while both the claimed
generation and its exact claim revision still own the session, and the session
is still `Processing` or `PaymentProcessing`. A late result that loses either
part of that fence is dropped. If it has a distinct payment id, the package
voids that orphan best-effort through the losing attempt's gateway and provider;
it never touches the winning payment.

Step execution captures the current generation and revision before `canSkip()`,
validation, and `handle()` run. Step-state writes and failure operations use that
captured origin, so a callback, cancellation, replacement claim, or same-
generation adoption that arrives during a hook or gateway call makes the old
execution observe the newer state without marking it failed or emitting a stale
failure event.

An accepted payment result advances the revision. `ProcessPaymentStep` carries
that committed origin into its returned `StepResult`, allowing the executor to
write the payment step's `Completed` or `Failed` state under the revision that
accepted the result.

### Breaking change for custom payment steps

Custom payment steps that call `PaymentAttemptManager::record()` must attach
the returned origin to every returned result that writes step state. This
replaces any pattern that records a result and then returns an ordinary result
bound to the claim's earlier revision. Update custom steps in the same way as
the example below:

```php
use AIArmada\Checkout\Data\PaymentAttemptClaim;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\PaymentAttemptManager;

public function __construct(private readonly PaymentAttemptManager $attempts) {}

// Add this method to a custom step. Call it after the processor returns.
public function returnAcceptedPaymentResult(
    CheckoutSession $session,
    PaymentAttemptClaim $claim,
    PaymentResult $gatewayResult,
): StepResult {
    $origin = $this->attempts->record($session, $claim, $gatewayResult);

    if ($origin === null) {
        return StepResult::observed(
            'process_payment',
            'Payment result was superseded by a concurrent update',
        );
    }

    if ($gatewayResult->requiresRedirect()) {
        return StepResult::success('process_payment', 'Payment initiated - redirect required', [
            'redirect_url' => $gatewayResult->redirectUrl,
            'payment_id' => $gatewayResult->paymentId,
        ])->withExecutionOrigin($origin);
    }

    if ($gatewayResult->isSuccessful()) {
        return StepResult::success('process_payment')
            ->withExecutionOrigin($origin);
    }

    if (! $gatewayResult->status->isFinal()) {
        return StepResult::observed(
            'process_payment',
            $gatewayResult->message ?? 'Payment outcome is not final',
        );
    }

    return StepResult::failed(
        'process_payment',
        $gatewayResult->message ?? 'Payment failed',
        $gatewayResult->errors,
        $origin,
    )->withExecutionOrigin($origin);
}
```

Add these methods to a custom step. The example returns `observed()` for pending
or processing results so it does not write step state. Ordinary steps do not
advance payment authority and should return their normal `StepResult`; the
executor writes them only against the origin captured before that step began.

`payment_data.payment_attempt_outcome` records the last observed result for the
claimed generation (`number`, `status`, `payment_id`, `provider`, `recorded_at`).
Terminal outcomes are never overwritten by late failures from earlier attempts.
A new generation clears the session-level `payment_id` and
`payment_redirect_url`; the previous payment id remains available in that
outcome record as evidence for its own attempt.

### Crash and Timeout Recovery

A crash or gateway timeout leaves the attempt claimed with no outcome or a
pending outcome. Calling `processCheckout()` again does not create a second
charge, because the claim observes the existing generation. Recovery happens
through `Checkout::retryPayment()` or `Checkout::recoverPayment()`:

1. The retry stamps `payment_data.retry_requested_for_attempt` with the current
   generation before resetting the payment step.
2. The claim adopts that generation and re-drives the same frozen identity,
   request snapshot, and idempotency key instead of incrementing the counter.
3. Gateways that honor the idempotency key return the original purchase, so a
   timeout can never double-charge.

Adoption is strict. The frozen gateway, amount, currency, reference,
idempotency key, payment-method key, provider, and complete request snapshot
must all be present; a claim missing any of them fails closed with
`PaymentException::incompleteAttemptIdentity` instead of falling back to
session state. Adoption also preserves the existing callback token exactly
rather than rotating it.

The claim uses no time-based leases. A crashed attempt without any callback stays
in flight until a callback records it or an explicit retry adopts it.

### Callback Generation Binding

A callback or webhook receipt is bound to the attempt generation that owns the
session before it may complete the checkout, fail it, cancel it, compensate a
gateway payment, or create an order. Both inbound paths — the gateway return
route and `ProcessCheckoutPaymentNotification` — resolve the gateway from the
current attempt's frozen identity when a generation has been claimed, falling
back to the mutable selection only for a new generation. The callback handler
revalidates that gateway under the row lock before acting, so changing
`selected_payment_gateway` after a claim cannot redirect a callback to another
processor.

The binding runs under the session row lock and checks the provider generation,
frozen gateway, concrete provider, and payment identity together:

- `metadata.checkout_attempt` (or the equivalent nested provider metadata) is
  the trusted generation evidence; `checkout_idempotency_key` ending in
  `:attempt:N` is accepted as a fallback.
- The frozen gateway must match any gateway evidence in the callback.
- A known frozen provider must match the actual provider returned by the
  processor.
- The payment id must match the payment identity recorded for the **current**
  generation. A previous generation's retained payment id is not authority for
  a newer attempt.

A callback that arrives before the current attempt's create response has no
recorded payment identity yet. It is accepted only when the provider generation
metadata proves the current attempt, the gateway matches, and the provider (when
known) matches. Without that evidence it fails closed: the active attempt is not
mutated, no verification failure is written, and no order is created.

An obsolete paid receipt is retained in
`payment_data.obsolete_attempt_receipts` with its own attempt, payment id,
gateway, provider, amount, and currency. It never replaces the active payment,
changes the active outcome, or triggers a refund of the winner. Checkout records
this evidence for host reconciliation and does not automatically refund or void
the obsolete receipt; resolve it through its original provider using the
recorded gateway and provider. Deliver a gateway receipt for an old generation
through that provider's verified webhook route so the reconciliation record
carries trusted provider evidence.

Browser redirect callbacks that carry no payload are bound by the rotated
per-attempt `checkout_callback_token` and, for success, the current payment
status check. Failure and cancel browser callbacks are accepted only while the
current generation has a recorded payment identity; they cannot end a
pre-response attempt by session reference alone.

CHIP purchases carry this evidence in `purchase.metadata`; Cashier forwards the
same request metadata to Stripe or CHIP. Direct integrations that build gateway
requests themselves must forward `checkout_attempt`, `checkout_gateway`, and
`checkout_provider` when the provider supports metadata, or provide equivalent
trusted reference evidence.

## Subject Resolution Before Payment

Before `process_payment` runs, the `resolve_customer` step asks Commerce Support's payment-subject resolver for the best subject for the current checkout session.

That step reads:

- the authenticated actor
- the current `customer_id`
- the current billable morph
- `billing_data`
- `shipping_data`
- the current owner context

When the resolver returns an existing `Customer`, checkout writes that customer back to the session and fills empty `billing_data` / `shipping_data` from the customer's default addresses. When it returns another model, checkout stores that model as the billable morph and continues the payment flow with that subject.

For guest/direct-capable flows, `resolve_customer` stays read-only. Checkout uses normalized billing/shipping payload data for the gateway request and defers any new `Customer` persistence until after payment succeeds.

This resolution stage is what lets `cashier-chip` use billable models for authenticated flows while still supporting guest purchases from the same checkout pipeline.

## Order attribution metadata

Checkout copies the validated `network_attribution` snapshot from cart
metadata into order metadata when it creates or reuses the order. The snapshot
contains the network link code, affiliate ID, offer ID, and click time. Network
listeners can therefore resume attribution after payment retries without
depending on request cookies.

## Post-Payment Customer Persistence

After `process_payment` succeeds, checkout enters a post-payment phase.

- when `integrations.inventory.reserve_before_payment` is `false`, checkout runs `reserve_inventory` first
- checkout then runs `persist_customer`
- checkout finally runs `create_order`

- direct-capable guest flows create or sync the `Customer` only after payment completion
- authenticated flows can promote or merge guest customers once the actor context is available
- pre-existing non-customer billable subjects remain untouched so billable-first gateway integrations keep their current order linkage behavior

## Callback Routes

The package registers routes for handling payment gateway callbacks:

| Route | Name | Purpose |
|-------|------|---------|
| `GET /checkout/payment/chip/success` | `checkout.payment.chip.success` | CHIP successful payment return |
| `GET /checkout/payment/chip/failure` | `checkout.payment.chip.failure` | CHIP failed payment return |
| `GET /checkout/payment/chip/cancel` | `checkout.payment.chip.cancel` | CHIP cancelled payment return |
| `GET /checkout/payment/cashier-chip/success` | `checkout.payment.cashier-chip.success` | Cashier CHIP successful payment return |
| `GET /checkout/payment/cashier/success` | `checkout.payment.cashier.success` | Cashier/Stripe successful payment return |
| `POST /webhooks/chip` | `checkout.webhook.chip` | CHIP and Cashier CHIP webhook notifications |
| `POST /webhooks/stripe` | `checkout.webhook.stripe` | Cashier/Stripe webhook notifications |

### CHIP Default Setup

When `aiarmada/chip` is installed and `checkout.integrations.chip.enabled` is `true` (the default), the recommended setup is to register only the CHIP webhook route from `config('chip.webhooks.route', '/chip/webhooks')` in the CHIP dashboard.

In that flow, CHIP verifies and processes the delivery first, then checkout listens to the resulting typed CHIP purchase events and calls `handlePaymentCallback()` internally. You do not need to send the same CHIP webhook to `POST /webhooks/chip` as well.

Keep the explicit Stripe route for Cashier/Stripe deliveries. The route, not a
header or payload shape, selects the verifier and the allowed gateway set.

### Route Configuration

Configure routes in `config/checkout.php`:

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'checkout',
    'middleware' => ['web'],
    'webhook_prefix' => 'webhooks',
    'webhook_middleware' => ['api'],
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
4. Enforces `payment.callback_token_ttl`, rate-limits attempts per session, and rejects consumed tokens unless the session is already completed
5. Wraps the callback in a database transaction and locks the session row with `lockForUpdate()` to avoid duplicate completion races
6. Consumes the token after a successful callback and short-circuits completed-session replays without re-confirming payment
7. Verifies payment with the gateway and redirects or renders based on the configured response mode

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
2. Failure handling only runs while the session is in a payment state (`AwaitingPayment`, `PaymentProcessing`, `Processing`, `PaymentFailed`) or still `Pending`
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
2. Cancellation is only processed while the session is in a payment state or still `Pending`
3. Completed sessions short-circuit to the success response
4. Otherwise the user is redirected to the cancel URL and the session ID/message are flashed

## Webhook Handling

For asynchronous payment confirmation, use the webhook endpoint:

### Webhook Request Format

The webhook controller is selected by the explicit gateway route and accepts
only the namespaced reference for that route's gateway set:

**CHIP format:**
```json
{
    "reference": "chk_550e8400-e29b-41d4-a716-446655440000",
    "status": "paid"
}
```

**Stripe format:**
```json
{
    "data": {
        "object": {
            "metadata": {
                "checkout_session_id": "chk_550e8400-e29b-41d4-a716-446655440000"
            },
            "status": "complete"
        }
    }
}
```

- `acknowledged` - Webhook received but no action needed
- `ignored` - Session not found, reference is not namespaced, or the selected gateway does not match the route

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

$successUrl = route('checkout.payment.chip.success', [
    $sessionParam => $session->id,
    'checkout_callback_token' => $callbackToken,
]);

$failureUrl = route('checkout.payment.chip.failure', [
    $sessionParam => $session->id,
    'checkout_callback_token' => $callbackToken,
]);

$cancelUrl = route('checkout.payment.chip.cancel', [
    $sessionParam => $session->id,
    'checkout_callback_token' => $callbackToken,
]);
```

Package-generated URLs use `checkout_callback_token`; the callback controller also accepts `callback_token` and `token` aliases when a gateway renames the query parameter on return.

## Webhook Security

Checkout's Spatie signature validator selects CHIP or Stripe from the explicit
webhook route. Keep `CHECKOUT_WEBHOOK_VERIFY_SIGNATURE=true` in production;
disabling verification is rejected there. Configure
`CHECKOUT_STRIPE_WEBHOOK_SECRET` for the Stripe route. CHIP verification
continues to use the CHIP package's own webhook configuration.

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

## Post-Payment Phase Steps

After payment confirmation, `CheckoutFinalizer::finalize()` runs:

1. Persist `order_id` on the session (before payment confirmation, so retries reuse the same order)
2. Confirm payment on the order when `create_order.confirm_payment` is enabled (paid orders only)
3. Redeem applied vouchers via the `CheckoutCompleted` listener
4. Transition the session status through `Processing` to `Completed` (which stamps `completed_at`)
5. Clear the cart

`CreateOrderStep` persists checkout metadata on the resulting order for both
live-cart and snapshot creation paths. It writes `cart_id`, `checkout_session_id`,
and the session `payment_data` before confirming payment, so `OrderPaid`
listeners can consume server-frozen attribution and other checkout provenance.
Integrations should capture decisions on `CheckoutSession` before payment
handoff and read the copied order metadata after payment; callback request
cookies are not a provenance source.

Payment confirmation reconciles before it commits. `CreateOrderStep::confirmPayment()`
checks the gateway result against the session first (present reference, matching
amount and currency), then against the persisted order's `grand_total` and
`currency`. Any drift records a `payment_data.reference_reconciliation`,
`amount_reconciliation`, or `order_reconciliation` diagnostic on the session and
skips confirmation — a live-cart reprice between order creation and callback can
never mark an underpaid order as fully paid. The free-order path records the
equivalent `free_order_reconciliation` mismatch.

### Inventory Timing

Checkout does not commit inventory reservations itself. `ReserveInventoryStep` places a group reservation before payment (or first in the post-payment phase when `reserve_before_payment` is `false`), and releases it on failure or cancellation through step compensation. Stock deduction flows through the orders package instead: its `PaymentConfirmed` transition dispatches `OrderProcessingStarted`, which the `DeductInventoryOnPaymentConfirmed` listener turns into an `InventoryDeductionRequired` event for the inventory package to handle idempotently. Free orders (`payment_data.type === 'free_order'`) use the dedicated `FreeOrderConfirmed` transition, which dispatches the same `OrderProcessingStarted` event without a payment record, paid timestamp, or `OrderPaid` event — and rejects the order when it no longer qualifies as free (for example after a live-cart reprice), recording a `free_order_reconciliation` mismatch on the session.

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
    
    return redirect($session->payment_redirect_url ?? config('app.url'))
        ->with('error', 'Payment verification failed. Please try again.');
}
```

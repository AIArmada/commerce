---
title: Usage
---

# Usage

## Basic Checkout Flow

### Starting a Checkout

```php
use AIArmada\Checkout\Facades\Checkout;

// Start checkout from a cart
$session = Checkout::startCheckout($cartId);

// With customer ID
$session = Checkout::startCheckout($cartId, $customerId);
```

### Processing Checkout

Process the entire checkout flow in one call:

```php
$result = Checkout::processCheckout($session);

if ($result->success) {
    return redirect()->route('orders.show', $result->orderId);
}

if ($result->requiresRedirect()) {
    return redirect($result->redirectUrl);
}

return back()->withErrors($result->errors);
```

## Customer and Billable Resolution

Checkout resolves the payment subject before payment is created, but it no longer persists brand-new guest customers during direct-capable pre-payment flows.

The `resolve_customer` step uses Commerce Support's `PaymentSubjectResolverInterface` to inspect:

- the authenticated user
- the current session customer
- the current session billable model
- `billing_data`
- `shipping_data`

When the resolver returns an existing model, checkout stores it on the session as `billable_type` and `billable_id`. If the resolved subject is an existing `Customer`, checkout also stores `customer_id` and hydrates empty `billing_data` / `shipping_data` from that customer's default addresses.

For direct-capable guest flows, `resolve_customer` intentionally stays read-only. Checkout uses the guest payment payload for the gateway request, then the `persist_customer` step creates or syncs the `Customer` after payment succeeds and before `create_order` runs.

When checkout owner mode is enabled, both steps use the checkout session owner context so post-payment customer creation and guest-customer lookups stay tenant-safe even across redirects and webhook callbacks.

This is why guest checkout, authenticated checkout, and billable-model checkout all flow through the same payment steps without separate controller logic.

## Unified Discount Code Resolution

Checkout treats the shared promo-code field as a single discount-code input.

- It reads `billing_data.metadata.promo_code` first, then falls back to `cart_snapshot.metadata.promo_code`.
- When vouchers are installed, checkout validates that code as a voucher first using a cart-aware validation context.
- Only when no valid voucher is found does checkout fall back to code-based promotion lookup.
- If the resolved code is a voucher, checkout merges it into the applied voucher-code list automatically, so landing-page forms and billing metadata can drive the same code path without separate voucher/promotion fields.

When a live cart is available, voucher application also dispatches `VoucherApplied`, which lets downstream listeners such as affiliate attribution react to the applied voucher immediately.

## Bootstrapping a Checkout Offer Product

For single-offer or landing-page checkouts, `EnsureCheckoutOfferProduct` can create or refresh the backing product, price list, and price row from one DTO.

```php
use AIArmada\Checkout\Actions\EnsureCheckoutOfferProduct;
use AIArmada\Checkout\Data\CheckoutOfferProductData;

$product = app(EnsureCheckoutOfferProduct::class)->handle(
    new CheckoutOfferProductData(
        productSlug: 'founders-pass',
        priceListSlug: 'public-offers',
        name: 'Founders Pass',
        description: 'One-time public checkout offer.',
        sku: 'FOUNDERS-PASS',
        priceAmount: 9900,
        currency: 'MYR',
        priceListName: 'Public Offers',
        compareAmount: 12900,
        minimumOnHand: 25,
    ),
);
```

The action:

- runs inside explicit global owner context so public offers stay ownerless unless your app wraps it differently
- creates or updates the `Product`, `PriceList`, and `Price` rows by slug
- seeds inventory only when inventory integration is enabled **and** the inventory package/tables are actually available

`CheckoutOfferProductData` also lets you control metadata, SEO copy, visibility, product type, taxability, shipping requirements, and optional inventory notes from one place.

### Step-by-Step Processing

Process individual steps for more control:

```php
// Get current step
$currentStep = Checkout::getCurrentStep($session);

// Process a specific step
$session = Checkout::processStep($session, 'validate_cart');
$session = Checkout::processStep($session, 'calculate_pricing');

// Check if can proceed
if (Checkout::canProceed($session)) {
    $session = Checkout::processStep($session, 'process_payment');
}
```

## Working with Sessions

### Resuming Checkout

```php
// Resume an existing session
$session = Checkout::resumeCheckout($sessionId);
```

### Canceling Checkout

```php
$session = Checkout::cancelCheckout($session);
```

### Session Properties

```php
$session->id;               // Session ID
$session->cart_id;          // Associated cart
$session->customer_id;      // Resolved customer ID (if any)
$session->billable_type;    // Resolved payment subject morph type
$session->billable_id;      // Resolved payment subject morph key
$session->order_id;         // Created order (after completion)
$session->status;           // Current status (CheckoutState state object)
$session->current_step;     // Current step name
$session->step_states;      // Per-step status map
$session->shipping_data;    // Shipping payload data
$session->billing_data;     // Billing payload data
$session->payment_id;       // Gateway payment identifier
$session->selected_payment_gateway; // Payment gateway used
$session->payment_data;     // Payment metadata, callback token, gateway response
$session->payment_redirect_url; // Redirect URL for hosted payment flows
$session->expires_at;       // Session expiration
```

### Cart Snapshot Schema

The checkout session stores a normalized cart snapshot in `cart_snapshot`:

```json
{
    "items": [
        {
            "id": "SKU-001",
            "name": "Product Name",
            "price": 4999,
            "quantity": 2,
            "attributes": {
                "weight": 250,
                "dimensions": {"length": 20, "width": 10, "height": 5}
            },
            "conditions": [],
            "associated_model": {
                "class": "App\\Models\\Product",
                "id": "uuid",
                "data": {"sku": "SKU-001"}
            }
        }
    ],
    "subtotal": 9998,
    "total": 9998,
    "item_count": 2,
    "captured_at": "2026-01-28T10:00:00+00:00"
}
```

Notes:
- `price` and totals are stored in the smallest currency unit (cents).
- `attributes.weight` is in grams when provided.
- `associated_model` is populated when cart items are linked to Eloquent models.

## Checkout States

The checkout session uses Spatie Model States for robust status management. States define allowed transitions and provide behavior methods.

### Available States

| State | Description | Terminal |
|-------|-------------|----------|
| `Pending` | Initial state, checkout not started | No |
| `Processing` | Checkout steps are being executed | No |
| `AwaitingPayment` | Waiting for payment gateway response | No |
| `PaymentProcessing` | Payment is being processed | No |
| `PaymentFailed` | Payment attempt failed | No |
| `Completed` | Checkout finished successfully | Yes |
| `Cancelled` | Checkout was cancelled | Yes |
| `Expired` | Session TTL exceeded | Yes |

### State Behavior Methods

Each state provides methods to check available actions:

```php
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Completed;

// Check if session can be cancelled
if ($session->status->canCancel()) {
    Checkout::cancelCheckout($session);
}

// Check if session can be modified
if ($session->status->canModify()) {
    $session->update(['shipping_data' => $newAddress]);
}

// Check if payment can be retried
if ($session->status->canRetryPayment()) {
    Checkout::retryPayment($session);
}

// Check if checkout is in a terminal state
if ($session->status->isTerminal()) {
    // No further transitions possible
}
```

### State Transitions

States enforce valid transitions:

```
Pending → Processing → AwaitingPayment → Completed
                    → PaymentProcessing → Completed
                                       → PaymentFailed → Processing (retry)
                    → Cancelled
                    → Expired
```

### Checking Status

```php
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed;

// Check if completed
if ($session->status instanceof Completed) {
    // Show order confirmation
}

// Check if payment failed
if ($session->status instanceof PaymentFailed) {
    // Offer retry option
}

// Get status name for display
$statusName = $session->status->name();  // 'pending', 'completed', etc.
$statusLabel = $session->status->label(); // Localized label
$statusColor = $session->status->color(); // Filament badge color
$statusIcon = $session->status->icon();   // Heroicon name
```

## Checkout Result

The `CheckoutResult` data object provides checkout outcome:

```php
$result = Checkout::processCheckout($session);

// Success check
$result->success;        // bool

// Status
$result->status;         // CheckoutState state object

// Check specific status
use AIArmada\Checkout\States\Completed;
if ($result->status instanceof Completed) {
    // Checkout was successful
}

// IDs
$result->sessionId;      // Checkout session ID
$result->orderId;        // Created order ID (if successful)
$result->paymentId;      // Payment ID

// Redirect handling
$result->redirectUrl;    // Payment redirect URL
$result->requiresRedirect(); // Check if redirect needed

// Errors
$result->message;        // Error message
$result->errors;         // Validation errors array

// Metadata
$result->metadata;       // Additional data
```

## Payment Handling

### How Checkout Builds the Payment Request

`ProcessPaymentStep` builds `PaymentRequest` from the session after customer resolution.

The request uses this fallback order for customer details:

1. `billing_data.name`, `billing_data.email`, and `billing_data.phone`
2. the resolved `customer` on the checkout session
3. the resolved `billable` model using `chipName()`, `chipEmail()`, `chipPhone()`, or the matching attributes

The resulting request metadata includes:

- `checkout_session_id`
- `cart_id`
- `customer_id`
- `billable_type`
- `billable_id`

For redirect-based gateways, `ProcessPaymentStep` also stores a per-session callback token in `payment_data.callback_token` and preserves it across retries so callback validation keeps working even after multiple payment attempts.

### Retry Failed Payment

```php
$result = Checkout::retryPayment($session);

if ($result->success) {
    return redirect()->route('orders.show', $result->orderId);
}
```

### Handling Webhooks

For payment gateway webhooks, use the respective gateway's webhook handler:

```php
// Example: Chip webhook handling
use AIArmada\Chip\Facades\Chip;

Route::post('/webhook/chip', function (Request $request) {
    $result = Chip::handleWebhook($request);
    
    if ($result->isPaid()) {
        // Payment confirmed, checkout will auto-complete
    }
    
    return response()->json(['status' => 'ok']);
});
```

## Address Handling

### Setting Addresses

```php
$session->update([
    'shipping_data' => [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'line1' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'state' => 'WP',
        'postcode' => '50000',
        'country' => 'MY',
        'phone' => '+60123456789',
        'email' => 'john@example.com',
    ],
    'billing_data' => [
        // Same structure as shipping
    ],
]);
```

### Using Same as Shipping

```php
$session->update([
    'billing_data' => $session->shipping_data,
]);
```

## Events

The checkout package dispatches these events:

```php
use AIArmada\Checkout\Events\CheckoutStarted;
use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Checkout\Events\CheckoutFailed;
use AIArmada\Checkout\Events\CheckoutStepCompleted;
use AIArmada\Checkout\Events\CheckoutPaymentCompleted;
use AIArmada\Checkout\Events\PaymentCompleted;
use AIArmada\Checkout\Events\PaymentFailed;

// Listen in EventServiceProvider
protected $listen = [
    CheckoutCompleted::class => [
        SendOrderConfirmation::class,
        NotifyWarehouse::class,
    ],
    PaymentFailed::class => [
        LogPaymentFailure::class,
        NotifyCustomerSupport::class,
    ],
    CheckoutPaymentCompleted::class => [
        UpdateOrderPaymentStatus::class,
    ],
];
```

## Error Handling

### Handling Exceptions

```php
use AIArmada\Checkout\Exceptions\CheckoutException;
use AIArmada\Checkout\Exceptions\InvalidCheckoutStateException;
use AIArmada\Checkout\Exceptions\PaymentException;

try {
    $result = Checkout::processCheckout($session);
} catch (InvalidCheckoutStateException $e) {
    // Session expired, already completed, etc.
    Log::error('Checkout state error', $e->context);
    return back()->with('error', $e->getMessage());
} catch (PaymentException $e) {
    // Payment processing failed
    Log::error('Payment error', $e->context);
    return back()->with('error', 'Payment failed. Please try again.');
} catch (CheckoutException $e) {
    // General checkout error
    Log::error('Checkout error', $e->context);
    return back()->with('error', $e->getMessage());
}
```

### Common Error Scenarios

| Exception | Cause | Resolution |
|-----------|-------|------------|
| `InvalidCheckoutStateException::sessionExpired` | Session TTL exceeded | Start new checkout |
| `InvalidCheckoutStateException::emptyCart` | Cart has no items | Add items to cart |
| `PaymentException::paymentFailed` | Payment declined | Retry with different method |
| `InventoryException::insufficientStock` | Item out of stock | Update quantities |

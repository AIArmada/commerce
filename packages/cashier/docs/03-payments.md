---
title: Payments
---

# Payments

This guide covers one-time charges, hosted checkout, payment methods, invoices, and customer sync.

## One-Time Charges

### Basic Charge

```php
$payment = $user->chargeWithGateway(1000, $paymentMethodId);

if ($payment->isSucceeded()) {
    $transactionId = $payment->id();
}
```

### Charge with Options

```php
$payment = $user->chargeWithGateway(2500, $paymentMethodId, options: [
    'description' => 'One-time purchase',
    'metadata' => [
        'order_id' => $order->id,
        'product' => 'Premium Widget',
    ],
]);
```

### Charge on a Specific Gateway

```php
$payment = $user->chargeWithGateway(5000, $paymentMethodId, 'chip', [
    'description' => 'Local Malaysian payment',
]);
```

### Handling Payment Status

```php
$payment = $user->chargeWithGateway(1000, $paymentMethodId);

if ($payment->isSucceeded()) {
    $amount = $payment->amount();
    $currency = $payment->currency();
} elseif ($payment->requiresAction() || $payment->requiresRedirect()) {
    return redirect($payment->redirectUrl());
} elseif ($payment->isFailed()) {
    report($payment->errorCode());
}
```

## Checkout Sessions

Checkout builders are gateway-specific under the hood, but you create them through the unified wrapper.

### Basic Checkout

```php
$checkout = $user->checkoutWithGateway('stripe')
    ->price('price_product_a')
    ->price('price_product_b', 2)
    ->successUrl(route('checkout.success'))
    ->cancelUrl(route('checkout.cancel'))
    ->create();

return redirect($checkout->url());
```

### Using `prices()`

```php
$checkout = $user->checkoutWithGateway('stripe')
    ->prices([
        'price_product_a' => 1,
        'price_product_b' => 2,
    ])
    ->successUrl(route('checkout.success'))
    ->cancelUrl(route('checkout.cancel'))
    ->metadata([
        'order_id' => $order->id,
        'user_id' => $user->id,
    ])
    ->create();
```

### Subscription Checkout

```php
$checkout = $user->checkoutWithGateway('stripe')
    ->price('price_monthly_subscription')
    ->mode('subscription')
    ->trialDays(14)
    ->successUrl(route('subscription.success'))
    ->cancelUrl(route('subscription.cancel'))
    ->create();
```

### Retrieving Checkout Status

```php
use AIArmada\Cashier\Facades\Cashier;

$checkout = Cashier::gateway('stripe')->retrieveCheckout($checkoutId);

if ($checkout && $checkout->isComplete()) {
    // Checkout reached a terminal state.
}

if ($checkout && $checkout->isSuccessful()) {
    // Payment completed.
}

if ($checkout && $checkout->isExpired()) {
    // Customer must start over.
}
```

## Payment Methods

### Listing Payment Methods

```php
$allMethods = $user->allGatewayPaymentMethods();

$stripeCards = $user->gatewayPaymentMethods('stripe', 'card');
$chipMethods = $user->gatewayPaymentMethods('chip');

$defaultStripeMethod = $user->defaultGatewayPaymentMethod('stripe');
```

### Inspecting a Payment Method

```php
$paymentMethod = $user->gatewayPaymentMethods('stripe')
    ->first(fn ($method) => $method->id() === 'pm_xxx');

if ($paymentMethod) {
    $paymentMethod->gateway();
    $paymentMethod->type();
    $paymentMethod->brand();
    $paymentMethod->lastFour();
    $paymentMethod->expirationMonth();
    $paymentMethod->expirationYear();
    $paymentMethod->isDefault();
}
```

### Deleting a Payment Method

```php
$paymentMethod = $user->gatewayPaymentMethods('stripe')
    ->first(fn ($method) => $method->id() === 'pm_xxx');

$paymentMethod?->delete();
```

> **Note:** Attaching payment methods, updating default methods, or using setup intents remains
> gateway-native behavior. Use the installed gateway package directly when you need those write APIs.

## Invoices

### Listing Invoices

```php
$stripeInvoices = $user->gatewayInvoices('stripe');

$stripeInvoicesIncludingPending = $user->gatewayInvoices('stripe', [
    'include_pending' => true,
]);

$allInvoices = $user->allGatewayInvoices([
    'include_pending' => true,
]);
```

### Finding an Invoice

```php
$invoice = $user->allGatewayInvoices(['include_pending' => true])
    ->first(fn ($invoice) => $invoice->id() === 'in_xxx');
```

### Invoice Details

```php
if ($invoice) {
    $invoice->gateway();
    $invoice->number();
    $invoice->date();
    $invoice->dueDate();
    $invoice->status();
    $invoice->total();
    $invoice->subtotal();
    $invoice->tax();
    $invoice->currency();
    $invoice->hostedUrl();
    $invoice->pdfUrl();

    foreach ($invoice->items() as $item) {
        $item->description();
        $item->quantity();
        $item->unitAmount();
        $item->amount();
    }
}
```

### Downloading / Viewing Invoices

```php
return $invoice->download();

return $invoice->view();
```

## Customer Sync

```php
$customer = $user->createOrGetCustomer(options: [
    'email' => $user->email,
    'name' => $user->name,
]);

$chipCustomer = $user->createOrGetCustomer(gateway: 'chip', options: [
    'email' => $user->email,
    'name' => $user->name,
    'phone' => $user->phone,
]);

$user->syncCustomer(gateway: 'stripe', options: [
    'email' => $user->email,
    'name' => $user->name,
]);
```

## Error Handling

```php
use AIArmada\Cashier\Exceptions\PaymentActionRequired;
use AIArmada\Cashier\Exceptions\PaymentFailedException;

try {
    $payment = $user->chargeWithGateway(1000, $paymentMethodId, 'stripe');
} catch (PaymentActionRequired $e) {
    return redirect($e->actionUrl());
} catch (PaymentFailedException $e) {
    report([
        'gateway' => $e->gateway(),
        'payment_id' => $e->paymentId(),
        'error_code' => $e->errorCode(),
    ]);

    return back()->with('error', 'Payment failed: ' . $e->getMessage());
}
```

## Best Practices

### 1. Prefer Explicit Gateways in Cross-Gateway Flows

```php
$payment = $user->chargeWithGateway(1000, $paymentMethodId, 'stripe');
```

### 2. Use Metadata for Traceability

```php
$payment = $user->chargeWithGateway(1000, $paymentMethodId, 'stripe', [
    'metadata' => [
        'order_id' => $order->id,
        'user_id' => $user->id,
        'source' => 'web',
    ],
]);
```

### 3. Handle Idempotency at the Gateway Layer

```php
$payment = $user->chargeWithGateway(1000, $paymentMethodId, 'stripe', [
    'idempotency_key' => 'order_' . $order->id,
]);
```

### 4. Keep Gateway-Native APIs for Gateway-Native Features

The wrapper is great for unified reads and common write flows. For gateway-specific setup intents,
payment-method attachment flows, or advanced refund controls, call the installed gateway package directly.

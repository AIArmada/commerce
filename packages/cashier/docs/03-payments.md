# Payments

This guide covers one-time payments, checkout sessions, and payment method management.

## One-Time Charges

### Basic Charge

```php
// Charge $10.00 (1000 cents)
$payment = $user->charge(1000, $paymentMethodId);

if ($payment->isSuccessful()) {
    // Payment completed
    $transactionId = $payment->id();
}
```

### Charge with Options

```php
$payment = $user->charge(2500, $paymentMethodId, [
    'description' => 'One-time purchase',
    'metadata' => [
        'order_id' => $order->id,
        'product' => 'Premium Widget',
    ],
]);
```

### Charge on Specific Gateway

```php
// Use CHIP for local Malaysian payments
$payment = $user->charge(5000, $paymentMethodId, [
    'gateway' => 'chip',
    'description' => 'Local payment',
]);
```

### Handling Payment Status

```php
$payment = $user->charge(1000, $paymentMethodId);

if ($payment->isSuccessful()) {
    // Payment completed successfully
    $amount = $payment->amount();
    $currency = $payment->currency();
    
} elseif ($payment->requiresAction()) {
    // 3D Secure or additional verification required
    $actionUrl = $payment->actionUrl();
    $clientSecret = $payment->clientSecret();
    
    // Redirect user to complete verification
    return redirect($actionUrl);
    
} elseif ($payment->isFailed()) {
    // Payment failed
    $errorMessage = $payment->errorMessage();
}
```

## Checkout Sessions

Checkout sessions redirect users to a hosted payment page.

### Creating a Checkout Session

```php
$checkout = $user->checkout([
    ['price' => 'price_product_a', 'quantity' => 1],
    ['price' => 'price_product_b', 'quantity' => 2],
])
    ->successUrl(route('checkout.success'))
    ->cancelUrl(route('checkout.cancel'))
    ->create();

// Redirect to checkout
return redirect($checkout->url());
```

### With Metadata

```php
$checkout = $user->checkout([
    ['price' => 'price_xxx', 'quantity' => 1],
])
    ->successUrl(route('checkout.success', ['session_id' => '{CHECKOUT_SESSION_ID}']))
    ->cancelUrl(route('checkout.cancel'))
    ->metadata([
        'order_id' => $order->id,
        'user_id' => $user->id,
    ])
    ->create();
```

### For Subscriptions

```php
$checkout = $user->checkout([
    ['price' => 'price_monthly_subscription', 'quantity' => 1],
])
    ->mode('subscription')
    ->successUrl(route('subscription.success'))
    ->cancelUrl(route('subscription.cancel'))
    ->create();
```

### On Specific Gateway

```php
// Create CHIP checkout for Malaysian users
$checkout = $user->checkout([
    ['price' => 'price_xxx', 'quantity' => 1],
], 'chip')
    ->successUrl(route('checkout.success'))
    ->cancelUrl(route('checkout.cancel'))
    ->create();
```

### Checking Checkout Status

```php
$checkout = $gateway->findCheckout($checkoutId);

if ($checkout->isComplete()) {
    // Checkout completed
}

if ($checkout->isPaid()) {
    // Payment received
}

if ($checkout->isExpired()) {
    // Session expired, user needs to start over
}
```

## Payment Methods

### Listing Payment Methods

```php
// Get all payment methods
$paymentMethods = $user->paymentMethods();

foreach ($paymentMethods as $pm) {
    echo $pm->type();     // 'card', 'fpx', etc.
    echo $pm->lastFour(); // Last 4 digits (for cards)
}
```

### Adding a Payment Method

```php
// Add a new payment method
$paymentMethod = $user->addPaymentMethod('pm_xxx');

// Make it the default
$user->updateDefaultPaymentMethod('pm_xxx');
```

### Setting Default Payment Method

```php
// Update default payment method
$user->updateDefaultPaymentMethod('pm_new_default');

// Get current default
$default = $user->defaultPaymentMethod();
```

### Removing Payment Methods

```php
// Delete a specific payment method
$paymentMethod = $user->findPaymentMethod('pm_xxx');
$paymentMethod->delete();

// Delete all payment methods
$user->deletePaymentMethods();
```

### Payment Method Details

```php
$paymentMethod = $user->findPaymentMethod('pm_xxx');

$paymentMethod->id();
$paymentMethod->type();      // 'card', 'fpx', 'bank_transfer'
$paymentMethod->lastFour();  // Card last 4 digits
$paymentMethod->brand();     // 'visa', 'mastercard'
$paymentMethod->expMonth();  // Expiration month
$paymentMethod->expYear();   // Expiration year
```

## Invoices

### Listing Invoices

```php
// Get paid invoices
$invoices = $user->invoices();

// Include pending invoices
$allInvoices = $user->invoicesIncludingPending();
```

### Finding an Invoice

```php
$invoice = $user->findInvoice('in_xxx');
```

### Invoice Details

```php
$invoice = $user->findInvoice('in_xxx');

$invoice->id();
$invoice->number();
$invoice->date();
$invoice->total();
$invoice->subtotal();
$invoice->tax();
$invoice->currency();

// Line items
foreach ($invoice->lineItems() as $item) {
    echo $item->description();
    echo $item->quantity();
    echo $item->unitAmount();
    echo $item->amount();
}
```

### Downloading Invoices

```php
// Download as PDF response
return $invoice->download();

// Get PDF as string
$pdf = $invoice->pdf();

// Save to file
file_put_contents('invoice.pdf', $invoice->pdf());
```

## Error Handling

### Payment Failures

```php
use AIArmada\Cashier\Exceptions\PaymentFailedException;
use AIArmada\Cashier\Exceptions\PaymentActionRequired;

try {
    $payment = $user->charge(1000, $paymentMethodId);
} catch (PaymentActionRequired $e) {
    // 3D Secure required
    $paymentId = $e->paymentId();
    $clientSecret = $e->clientSecret();
    $actionUrl = $e->actionUrl();
    
    // Redirect to action URL or handle client-side
    return view('payment.action-required', [
        'clientSecret' => $clientSecret,
    ]);
} catch (PaymentFailedException $e) {
    // Payment failed
    $errorCode = $e->errorCode();
    $gateway = $e->gateway();
    
    return back()->with('error', 'Payment failed: ' . $e->getMessage());
}
```

### Customer Not Found

```php
use AIArmada\Cashier\Exceptions\CustomerNotFoundException;

try {
    $customer = $user->asCustomer();
} catch (CustomerNotFoundException $e) {
    // Create customer first
    $user->createAsCustomer([
        'email' => $user->email,
        'name' => $user->name,
    ]);
}
```

## Best Practices

### 1. Always Handle Action Required

```php
$payment = $user->charge($amount, $paymentMethod);

if ($payment->requiresAction()) {
    // Don't ignore this - 3D Secure is becoming mandatory
    return redirect($payment->actionUrl());
}
```

### 2. Use Metadata for Tracking

```php
$payment = $user->charge(1000, $pm, [
    'metadata' => [
        'order_id' => $order->id,
        'user_id' => $user->id,
        'source' => 'web',
    ],
]);
```

### 3. Handle Idempotency

```php
$payment = $user->charge(1000, $pm, [
    'idempotency_key' => 'order_' . $order->id,
]);
```

### 4. Validate Amounts

```php
// Ensure amount is positive integer (cents)
$amount = max(50, (int) ($price * 100)); // Minimum 50 cents

$payment = $user->charge($amount, $pm);
```

### 5. Log Payment Events

```php
use AIArmada\Cashier\Events\PaymentSucceeded;
use AIArmada\Cashier\Events\PaymentFailed;

// In EventServiceProvider
protected $listen = [
    PaymentSucceeded::class => [
        LogPaymentSuccess::class,
        SendPaymentReceipt::class,
    ],
    PaymentFailed::class => [
        LogPaymentFailure::class,
        NotifyAdminOfFailure::class,
    ],
];
```

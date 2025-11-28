# 🔌 Payment Gateway Interface

> **A unified payment gateway that works with any CheckoutableInterface implementation—Cart, Order, Invoice, or your own.**

The CHIP package provides `ChipGateway`, a full implementation of `PaymentGatewayInterface` from `aiarmada/commerce-support`. This allows you to use CHIP interchangeably with other payment providers without changing your application code.

## 📋 Table of Contents

- [Overview](#-overview)
- [Quick Start](#-quick-start)
- [Gateway Methods](#-gateway-methods)
- [Using with Cart](#-using-with-cart)
- [Payment Flows](#-payment-flows)
- [Payment Intent](#-payment-intent)
- [Error Handling](#-error-handling)
- [Feature Support](#-feature-support)
- [Switching Gateways](#-switching-gateways)

---

## 🎯 Overview

`ChipGateway` implements the universal `PaymentGatewayInterface`:

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;

interface PaymentGatewayInterface
{
    public function getName(): string;
    public function getDisplayName(): string;
    public function isTestMode(): bool;
    
    public function createPayment(
        CheckoutableInterface $checkoutable,
        ?CustomerInterface $customer = null,
        array $options = []
    ): PaymentIntentInterface;
    
    public function getPayment(string $paymentId): PaymentIntentInterface;
    public function cancelPayment(string $paymentId): PaymentIntentInterface;
    public function refundPayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface;
    public function capturePayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface;
    public function getPaymentMethods(array $filters = []): array;
    public function supports(string $feature): bool;
    public function getWebhookHandler(): WebhookHandlerInterface;
}
```

---

## 🚀 Quick Start

### Basic Usage

```php
use AIArmada\Chip\Gateways\ChipGateway;
use AIArmada\CommerceSupport\DataObjects\Customer;

class CheckoutController extends Controller
{
    public function __construct(
        private ChipGateway $gateway
    ) {}

    public function checkout(Request $request)
    {
        // Your cart/order implements CheckoutableInterface
        $cart = app(\AIArmada\Cart\Cart::class);
        
        // Customer details
        $customer = Customer::fromArray([
            'email' => $request->user()->email,
            'name' => $request->user()->name,
        ]);
        
        // Create payment
        $payment = $this->gateway->createPayment($cart, $customer, [
            'success_url' => route('payment.success'),
            'failure_url' => route('payment.failed'),
        ]);
        
        return redirect($payment->getCheckoutUrl());
    }
}
```

### Using Dependency Injection

```php
// AppServiceProvider.php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\Chip\Gateways\ChipGateway;

public function register(): void
{
    $this->app->bind(PaymentGatewayInterface::class, ChipGateway::class);
}

// Then in your controller
public function checkout(PaymentGatewayInterface $gateway)
{
    // Works with ChipGateway or any other implementation
    $payment = $gateway->createPayment($cart, $customer, $options);
}
```

---

## 📘 Gateway Methods

### `getName(): string`

Returns the gateway identifier.

```php
$gateway->getName(); // 'chip'
```

### `getDisplayName(): string`

Returns a human-readable name for the gateway.

```php
$gateway->getDisplayName(); // 'CHIP'
```

### `isTestMode(): bool`

Check if running in sandbox/test mode.

```php
if ($gateway->isTestMode()) {
    Log::info('Using CHIP sandbox environment');
}
```

### `createPayment()`

Create a new payment from any checkoutable object.

```php
$payment = $gateway->createPayment(
    checkoutable: $cart,          // Cart, Order, or any CheckoutableInterface
    customer: $customer,           // Optional CustomerInterface
    options: [
        'success_url' => 'https://example.com/success',
        'failure_url' => 'https://example.com/failed',
        'cancel_url' => 'https://example.com/cart',
        'webhook_url' => 'https://example.com/webhooks/chip',
        'send_receipt' => true,
        'pre_authorize' => false,
        'metadata' => [
            'order_id' => $orderId,
            'campaign' => 'summer-sale',
        ],
    ]
);
```

**Available Options:**

| Option | Type | Description |
|--------|------|-------------|
| `success_url` | string | Redirect URL after successful payment |
| `failure_url` | string | Redirect URL after failed payment |
| `cancel_url` | string | Redirect URL if customer cancels |
| `webhook_url` | string | URL for payment status callbacks |
| `send_receipt` | bool | Send receipt email to customer |
| `pre_authorize` | bool | Authorize only, capture later |
| `metadata` | array | Additional data to attach to payment |

### `getPayment()`

Retrieve an existing payment by ID.

```php
$payment = $gateway->getPayment('pur_abc123xyz');

echo $payment->getStatus();      // PaymentStatus::PAID
echo $payment->getAmount();      // Money object
echo $payment->getPaidAt();      // Carbon instance
```

### `cancelPayment()`

Cancel a pending payment.

```php
$payment = $gateway->cancelPayment('pur_abc123xyz');

if ($payment->getStatus() === PaymentStatus::CANCELLED) {
    // Handle cancellation
}
```

### `refundPayment()`

Refund a completed payment (full or partial).

```php
use Akaunting\Money\Money;

// Full refund
$refunded = $gateway->refundPayment('pur_abc123xyz');

// Partial refund
$partialRefund = $gateway->refundPayment(
    'pur_abc123xyz',
    Money::MYR(5000)  // Refund RM50.00
);
```

### `capturePayment()`

Capture a pre-authorized payment.

```php
// Full capture
$captured = $gateway->capturePayment('pur_abc123xyz');

// Partial capture
$partialCapture = $gateway->capturePayment(
    'pur_abc123xyz',
    Money::MYR(10000)  // Capture only RM100.00
);
```

### `getPaymentMethods()`

Get available payment methods.

```php
$methods = $gateway->getPaymentMethods([
    'currency' => 'MYR',
]);

// Returns array of available methods
// [
//     ['id' => 'fpx', 'name' => 'FPX Online Banking', ...],
//     ['id' => 'card', 'name' => 'Credit/Debit Card', ...],
//     ...
// ]
```

### `supports()`

Check if the gateway supports a specific feature.

```php
$gateway->supports('refunds');           // true
$gateway->supports('partial_refunds');   // true
$gateway->supports('pre_authorization'); // true
$gateway->supports('recurring');         // true
$gateway->supports('webhooks');          // true
$gateway->supports('hosted_checkout');   // true
$gateway->supports('embedded_checkout'); // false
$gateway->supports('direct_charge');     // true
```

### `getWebhookHandler()`

Get the webhook handler for signature verification and event processing.

```php
$handler = $gateway->getWebhookHandler();
$payload = $handler->verify($request);

if ($payload->event === 'purchase.paid') {
    // Handle payment completion
}
```

---

## 🛒 Using with Cart

The AIArmada Cart implements `CheckoutableInterface`, making integration seamless:

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\Chip\Gateways\ChipGateway;
use Akaunting\Money\Money;

// Build your cart
Cart::add('laptop', 'MacBook Pro', Money::MYR(599900), 1);
Cart::add('mouse', 'Magic Mouse', Money::MYR(29900), 1);
Cart::addDiscount('employee', '-15%');
Cart::addTax('sst', '6%');

// Get Cart instance
$cart = app(\AIArmada\Cart\Cart::class);

// Create payment through gateway
$gateway = app(ChipGateway::class);
$payment = $gateway->createPayment($cart, $customer, [
    'success_url' => route('checkout.complete'),
    'failure_url' => route('checkout.failed'),
]);

// The gateway receives:
// - Line items from getCheckoutLineItems()
// - Totals from getCheckoutTotal(), getCheckoutSubtotal(), etc.
// - Reference from getCheckoutReference()
// - Metadata from getCheckoutMetadata()
```

---

## 💳 Payment Flows

### Standard Checkout Flow

```php
// 1. Create payment
$payment = $gateway->createPayment($cart, $customer, [
    'success_url' => route('checkout.success'),
    'failure_url' => route('checkout.failed'),
]);

// 2. Store payment ID
session(['payment_id' => $payment->getId()]);

// 3. Redirect to CHIP
return redirect($payment->getCheckoutUrl());

// 4. Handle success callback
public function success(Request $request)
{
    $payment = $gateway->getPayment(session('payment_id'));
    
    if ($payment->getStatus() === PaymentStatus::PAID) {
        // Create order, clear cart
        $order = Order::createFromPayment($payment);
        Cart::clear();
        
        return view('checkout.success', compact('order'));
    }
    
    return redirect()->route('checkout.failed');
}
```

### Pre-Authorization Flow

```php
// 1. Authorize (hold funds)
$payment = $gateway->createPayment($cart, $customer, [
    'pre_authorize' => true,
    'success_url' => route('order.review'),
]);

// 2. Customer pays, funds are held
// ...

// 3. Review order, then capture
public function confirmOrder(Order $order)
{
    $gateway = app(ChipGateway::class);
    
    // Capture full amount
    $captured = $gateway->capturePayment($order->payment_id);
    
    // Or capture partial (e.g., only in-stock items)
    $captured = $gateway->capturePayment(
        $order->payment_id,
        $order->shippableTotal()
    );
    
    $order->markAsProcessing();
}

// 4. Or release if order cancelled
public function cancelOrder(Order $order)
{
    $gateway->cancelPayment($order->payment_id);
    $order->markAsCancelled();
}
```

### Recurring Payments

```php
// Initial payment with token creation
$payment = $gateway->createPayment($cart, $customer, [
    'success_url' => route('subscription.activated'),
    'metadata' => ['save_card' => true],
]);

// Later, charge using saved token
$recurringPayment = Chip::chargePurchase(
    purchaseId: $payment->getId(),
    recurringToken: $customer->chip_token
);
```

---

## 📦 Payment Intent

`createPayment()` returns a `PaymentIntentInterface`:

```php
interface PaymentIntentInterface
{
    public function getId(): string;
    public function getGatewayReference(): string;
    public function getStatus(): PaymentStatus;
    public function getAmount(): Money;
    public function getCurrency(): string;
    public function getCheckoutUrl(): ?string;
    public function getMetadata(): array;
    public function getCreatedAt(): ?DateTimeInterface;
    public function getPaidAt(): ?DateTimeInterface;
    public function getRawResponse(): array;
}
```

### Usage

```php
$payment = $gateway->createPayment($cart, $customer, $options);

// Access payment data
$payment->getId();              // 'pur_abc123xyz'
$payment->getStatus();          // PaymentStatus::PENDING
$payment->getAmount();          // Money::MYR(62900)
$payment->getCurrency();        // 'MYR'
$payment->getCheckoutUrl();     // 'https://gate.chip-in.asia/...'
$payment->getMetadata();        // ['order_id' => '123', ...]
$payment->getCreatedAt();       // Carbon instance
$payment->getPaidAt();          // null (not yet paid)
$payment->getRawResponse();     // Full CHIP API response
```

### Payment Status

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;

$status = $payment->getStatus();

match ($status) {
    PaymentStatus::PENDING => 'Awaiting payment',
    PaymentStatus::PAID => 'Payment completed',
    PaymentStatus::FAILED => 'Payment failed',
    PaymentStatus::CANCELLED => 'Payment cancelled',
    PaymentStatus::REFUNDED => 'Payment refunded',
    PaymentStatus::AUTHORIZED => 'Payment authorized (not captured)',
};
```

---

## ⚠️ Error Handling

The gateway throws `PaymentGatewayException` for all errors:

```php
use AIArmada\CommerceSupport\Exceptions\PaymentGatewayException;

try {
    $payment = $gateway->createPayment($cart, $customer, $options);
} catch (PaymentGatewayException $e) {
    Log::error('Payment failed', [
        'gateway' => $e->getGatewayName(),    // 'chip'
        'message' => $e->getMessage(),
        'context' => $e->getContext(),         // Additional details
    ]);
    
    return back()->with('error', 'Payment could not be processed');
}
```

### Exception Types

```php
// Payment creation failed
PaymentGatewayException::creationFailed($gateway, $message, $context);

// Payment not found
PaymentGatewayException::notFound($gateway, $paymentId);

// Cancellation failed
PaymentGatewayException::cancellationFailed($gateway, $paymentId, $message);

// Refund failed
PaymentGatewayException::refundFailed($gateway, $paymentId, $message, $context);

// Capture failed
PaymentGatewayException::captureFailed($gateway, $paymentId, $message);
```

---

## ✅ Feature Support

CHIP gateway capabilities:

| Feature | Supported | Notes |
|---------|-----------|-------|
| Refunds | ✅ | Full refunds supported |
| Partial Refunds | ✅ | Specify amount to refund |
| Pre-Authorization | ✅ | Auth + capture flow |
| Recurring | ✅ | Token-based recurring |
| Webhooks | ✅ | Signed webhook payloads |
| Hosted Checkout | ✅ | Redirect to CHIP payment page |
| Embedded Checkout | ❌ | Not available |
| Direct Charge | ✅ | Using recurring tokens |

---

## 🔄 Switching Gateways

The interface allows easy gateway switching:

```php
// config/payments.php
return [
    'default' => env('PAYMENT_GATEWAY', 'chip'),
    
    'gateways' => [
        'chip' => \AIArmada\Chip\Gateways\ChipGateway::class,
        'stripe' => \App\Gateways\StripeGateway::class,
        'paypal' => \App\Gateways\PayPalGateway::class,
    ],
];

// AppServiceProvider.php
public function register(): void
{
    $this->app->bind(PaymentGatewayInterface::class, function ($app) {
        $gateway = config('payments.default');
        $class = config("payments.gateways.{$gateway}");
        
        return $app->make($class);
    });
}

// Your checkout code never changes
public function checkout(PaymentGatewayInterface $gateway)
{
    $payment = $gateway->createPayment($cart, $customer, [
        'success_url' => route('checkout.success'),
        'failure_url' => route('checkout.failed'),
    ]);
    
    return redirect($payment->getCheckoutUrl());
}
```

---

## 📚 Related Documentation

- **[CHIP API Reference](CHIP_API_REFERENCE.md)** – Complete CHIP API documentation
- **[Cart Payment Integration](../../cart/docs/payment-integration.md)** – Using Cart with payment gateways
- **[Webhook Handling](CHIP_API_REFERENCE.md#collect-webhooks)** – Processing CHIP webhooks

---

**Next Steps:**
- [Configure CHIP credentials](../README.md#environment-variables)
- [Set up webhooks](CHIP_API_REFERENCE.md#collect-webhooks)
- [Test with sandbox](CHIP_API_REFERENCE.md#testing-notes)

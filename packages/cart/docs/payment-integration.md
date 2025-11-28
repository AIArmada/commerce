# 💳 Payment Gateway Integration

> **Connect your cart to any payment gateway with a unified interface—CHIP, Stripe, PayPal, or your own.**

AIArmada Cart implements `CheckoutableInterface`, making it directly compatible with any payment gateway that follows the `PaymentGatewayInterface` contract. This allows you to swap payment providers without changing your checkout code.

## 📋 Table of Contents

- [Overview](#-overview)
- [Quick Start](#-quick-start)
- [CheckoutableInterface](#-checkoutableinterface)
- [Using with CHIP](#-using-with-chip)
- [Using with Other Gateways](#-using-with-other-gateways)
- [Custom Gateway Implementation](#-custom-gateway-implementation)
- [Customer Data](#-customer-data)
- [Webhook Handling](#-webhook-handling)
- [Testing](#-testing)

---

## 🎯 Overview

The Cart class implements `CheckoutableInterface`, which provides a standardized way to extract checkout data:

```
┌─────────────────────────────────────────────────────────────┐
│                         Cart                                │
│  implements CheckoutableInterface                           │
├─────────────────────────────────────────────────────────────┤
│  • getCheckoutLineItems() → iterable<LineItemInterface>     │
│  • getCheckoutSubtotal() → Money                            │
│  • getCheckoutDiscount() → Money                            │
│  • getCheckoutTax() → Money                                 │
│  • getCheckoutTotal() → Money                               │
│  • getCheckoutCurrency() → string                           │
│  • getCheckoutReference() → string                          │
│  • getCheckoutMetadata() → array                            │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│              PaymentGatewayInterface                        │
├─────────────────────────────────────────────────────────────┤
│  • createPayment(checkoutable, customer, options)           │
│  • getPayment(paymentId)                                    │
│  • cancelPayment(paymentId)                                 │
│  • refundPayment(paymentId, amount?)                        │
│  • capturePayment(paymentId, amount?)                       │
└─────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
        ┌──────────┐   ┌──────────┐   ┌──────────┐
        │   CHIP   │   │  Stripe  │   │  PayPal  │
        │ Gateway  │   │ Gateway  │   │ Gateway  │
        └──────────┘   └──────────┘   └──────────┘
```

---

## 🚀 Quick Start

### Using Cart with CHIP Gateway

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\Chip\Gateways\ChipGateway;

// Build your cart
Cart::add('product-1', 'Premium Widget', Money::MYR(9900), 2);
Cart::add('product-2', 'Basic Widget', Money::MYR(4900), 1);
Cart::addDiscount('loyalty', '-10%');

// Get the Cart instance (implements CheckoutableInterface)
$cart = app(\AIArmada\Cart\Cart::class);

// Create payment through the gateway
$gateway = app(ChipGateway::class);

$payment = $gateway->createPayment($cart, $customer, [
    'success_url' => route('checkout.success'),
    'failure_url' => route('checkout.failed'),
]);

// Redirect to CHIP's hosted checkout
return redirect($payment->getCheckoutUrl());
```

---

## 📦 CheckoutableInterface

The Cart automatically provides all data needed for checkout:

### Line Items

```php
// Each CartItem implements LineItemInterface
foreach ($cart->getCheckoutLineItems() as $item) {
    $item->getLineItemId();       // 'product-1'
    $item->getLineItemName();     // 'Premium Widget'
    $item->getLineItemPrice();    // Money::MYR(9900)
    $item->getLineItemQuantity(); // 2
    $item->getLineItemTotal();    // Money::MYR(19800)
    $item->getLineItemMetadata(); // ['sku' => 'PWG-001', ...]
}
```

### Totals

```php
$cart->getCheckoutSubtotal();  // Money - Before discounts/taxes
$cart->getCheckoutDiscount();  // Money - Total discounts applied
$cart->getCheckoutTax();       // Money - Total tax amount
$cart->getCheckoutTotal();     // Money - Final amount to charge
```

### Reference & Metadata

```php
$cart->getCheckoutReference(); // Unique cart ID or generated reference
$cart->getCheckoutCurrency();  // 'MYR' (from config)
$cart->getCheckoutNotes();     // Optional notes from metadata
$cart->getCheckoutMetadata();  // Array with cart details:
// [
//     'cart_identifier' => 'user-123',
//     'cart_instance' => 'default',
//     'cart_version' => 5,
//     'item_count' => 2,
//     'total_quantity' => 3,
//     'shipping_method' => 'express',
//     'conditions' => [...],
// ]
```

---

## 🔌 Using with CHIP

The `aiarmada/chip` package provides a full `PaymentGatewayInterface` implementation:

### Basic Payment Flow

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\Chip\Gateways\ChipGateway;
use AIArmada\CommerceSupport\DataObjects\Customer;

class CheckoutController extends Controller
{
    public function __construct(
        private ChipGateway $gateway
    ) {}

    public function checkout(Request $request)
    {
        $cart = app(\AIArmada\Cart\Cart::class);
        
        // Create customer from authenticated user or form data
        $customer = Customer::fromArray([
            'email' => $request->user()->email,
            'name' => $request->user()->name,
            'phone' => $request->user()->phone,
        ]);
        
        // Create the payment
        $payment = $this->gateway->createPayment($cart, $customer, [
            'success_url' => route('checkout.success'),
            'failure_url' => route('checkout.failed'),
            'webhook_url' => route('webhooks.chip'),
            'send_receipt' => true,
            'metadata' => [
                'order_source' => 'web',
                'promo_campaign' => session('promo_campaign'),
            ],
        ]);
        
        // Store payment ID for later reference
        session(['payment_id' => $payment->getId()]);
        
        return redirect($payment->getCheckoutUrl());
    }
    
    public function success(Request $request)
    {
        $paymentId = session('payment_id');
        $payment = $this->gateway->getPayment($paymentId);
        
        if ($payment->getStatus() === PaymentStatus::PAID) {
            // Convert cart to order
            Cart::clear();
            return view('checkout.success', ['payment' => $payment]);
        }
        
        return redirect()->route('checkout.failed');
    }
}
```

### Pre-Authorization Flow

```php
// Step 1: Authorize (hold funds without charging)
$payment = $gateway->createPayment($cart, $customer, [
    'pre_authorize' => true,
    'success_url' => route('checkout.authorized'),
]);

// Step 2: Later, capture the authorized payment
$captured = $gateway->capturePayment($payment->getId());

// Or capture a partial amount
$partialCapture = $gateway->capturePayment(
    $payment->getId(),
    Money::MYR(5000) // Only capture RM50.00
);
```

### Refunds

```php
// Full refund
$refunded = $gateway->refundPayment($paymentId);

// Partial refund
$partialRefund = $gateway->refundPayment(
    $paymentId,
    Money::MYR(2500) // Refund RM25.00
);
```

### Check Gateway Capabilities

```php
$gateway->supports('refunds');          // true
$gateway->supports('partial_refunds');  // true
$gateway->supports('pre_authorization'); // true
$gateway->supports('recurring');        // true
$gateway->supports('webhooks');         // true
$gateway->supports('hosted_checkout');  // true
$gateway->supports('embedded_checkout'); // false
```

---

## 🔄 Using with Other Gateways

Any gateway implementing `PaymentGatewayInterface` works identically:

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;

class CheckoutController extends Controller
{
    public function checkout(
        PaymentGatewayInterface $gateway, // Resolved from container
        Request $request
    ) {
        $cart = app(\AIArmada\Cart\Cart::class);
        
        $payment = $gateway->createPayment($cart, $customer, [
            'success_url' => route('checkout.success'),
            'failure_url' => route('checkout.failed'),
        ]);
        
        return redirect($payment->getCheckoutUrl());
    }
}
```

### Binding Different Gateways

```php
// AppServiceProvider.php
public function register(): void
{
    // Default gateway
    $this->app->bind(
        PaymentGatewayInterface::class,
        ChipGateway::class
    );
    
    // Or conditionally based on config
    $this->app->bind(PaymentGatewayInterface::class, function ($app) {
        return match (config('payments.default')) {
            'chip' => $app->make(ChipGateway::class),
            'stripe' => $app->make(StripeGateway::class),
            'paypal' => $app->make(PayPalGateway::class),
            default => throw new \RuntimeException('Unknown payment gateway'),
        };
    });
}
```

---

## 🛠 Custom Gateway Implementation

Create your own gateway by implementing `PaymentGatewayInterface`:

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use Akaunting\Money\Money;

class CustomGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'custom';
    }

    public function getDisplayName(): string
    {
        return 'Custom Payment Gateway';
    }

    public function isTestMode(): bool
    {
        return config('custom-gateway.environment') === 'sandbox';
    }

    public function createPayment(
        CheckoutableInterface $checkoutable,
        ?CustomerInterface $customer = null,
        array $options = []
    ): PaymentIntentInterface {
        // Build API request from checkoutable
        $payload = [
            'amount' => $checkoutable->getCheckoutTotal()->getAmount(),
            'currency' => $checkoutable->getCheckoutCurrency(),
            'reference' => $checkoutable->getCheckoutReference(),
            'items' => [],
        ];
        
        foreach ($checkoutable->getCheckoutLineItems() as $item) {
            $payload['items'][] = [
                'name' => $item->getLineItemName(),
                'price' => $item->getLineItemPrice()->getAmount(),
                'quantity' => $item->getLineItemQuantity(),
            ];
        }
        
        if ($customer !== null) {
            $payload['customer'] = [
                'email' => $customer->getEmail(),
                'name' => $customer->getName(),
            ];
        }
        
        // Call your gateway's API
        $response = $this->client->post('/payments', $payload);
        
        return new CustomPaymentIntent($response);
    }

    public function getPayment(string $paymentId): PaymentIntentInterface
    {
        $response = $this->client->get("/payments/{$paymentId}");
        return new CustomPaymentIntent($response);
    }

    public function cancelPayment(string $paymentId): PaymentIntentInterface
    {
        $response = $this->client->post("/payments/{$paymentId}/cancel");
        return new CustomPaymentIntent($response);
    }

    public function refundPayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface
    {
        $payload = $amount ? ['amount' => $amount->getAmount()] : [];
        $response = $this->client->post("/payments/{$paymentId}/refund", $payload);
        return new CustomPaymentIntent($response);
    }

    public function capturePayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface
    {
        $payload = $amount ? ['amount' => $amount->getAmount()] : [];
        $response = $this->client->post("/payments/{$paymentId}/capture", $payload);
        return new CustomPaymentIntent($response);
    }

    public function getPaymentMethods(array $filters = []): array
    {
        return $this->client->get('/payment-methods', $filters);
    }

    public function supports(string $feature): bool
    {
        return match ($feature) {
            'refunds', 'webhooks', 'hosted_checkout' => true,
            'partial_refunds', 'pre_authorization' => false,
            default => false,
        };
    }

    public function getWebhookHandler(): WebhookHandlerInterface
    {
        return new CustomWebhookHandler();
    }
}
```

---

## 👤 Customer Data

Payment gateways accept customer data via `CustomerInterface`:

```php
use AIArmada\CommerceSupport\DataObjects\Customer;

// From array
$customer = Customer::fromArray([
    'email' => 'customer@example.com',
    'name' => 'Jane Doe',
    'phone' => '+60123456789',
    'address' => [
        'line1' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country' => 'MY',
    ],
]);

// From authenticated user
$customer = Customer::fromUser(auth()->user());

// With custom billing
$customer = Customer::fromArray([
    'email' => auth()->user()->email,
    'name' => auth()->user()->name,
    'billing_name' => 'ACME Corp',
    'tax_id' => '123456-A',
]);
```

---

## 🔔 Webhook Handling

Handle payment status updates via webhooks:

```php
// routes/web.php
Route::post('/webhooks/chip', function (Request $request) {
    $gateway = app(ChipGateway::class);
    $handler = $gateway->getWebhookHandler();
    
    $payload = $handler->verify($request);
    
    if ($payload->event === 'purchase.paid') {
        // Payment completed
        $order = Order::where('payment_reference', $payload->reference)->first();
        $order->markAsPaid();
    }
    
    return response('OK');
});
```

---

## 🧪 Testing

### Mock Gateway in Tests

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;

it('creates payment from cart', function () {
    // Arrange
    Cart::add('product-1', 'Test Product', Money::MYR(10000), 1);
    
    $mockPayment = Mockery::mock(PaymentIntentInterface::class);
    $mockPayment->shouldReceive('getId')->andReturn('pay_123');
    $mockPayment->shouldReceive('getCheckoutUrl')->andReturn('https://pay.example.com/123');
    $mockPayment->shouldReceive('getStatus')->andReturn(PaymentStatus::PENDING);
    
    $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
    $mockGateway->shouldReceive('createPayment')->andReturn($mockPayment);
    
    $this->app->instance(PaymentGatewayInterface::class, $mockGateway);
    
    // Act
    $response = $this->post('/checkout');
    
    // Assert
    $response->assertRedirect('https://pay.example.com/123');
});
```

### Test CheckoutableInterface

```php
it('provides checkout data correctly', function () {
    Cart::add('sku-1', 'Product A', Money::MYR(5000), 2);
    Cart::add('sku-2', 'Product B', Money::MYR(3000), 1);
    Cart::addDiscount('promo', '-10%');
    
    $cart = app(\AIArmada\Cart\Cart::class);
    
    expect($cart->getCheckoutSubtotal()->getAmount())->toBe(13000)
        ->and($cart->getCheckoutTotal()->getAmount())->toBe(11700)
        ->and($cart->getCheckoutCurrency())->toBe('MYR')
        ->and(iterator_to_array($cart->getCheckoutLineItems()))->toHaveCount(2);
});
```

---

## 📚 Related Documentation

- **[Money & Currency](money-and-currency.md)** – Working with Money objects
- **[Cart Operations](cart-operations.md)** – Managing cart items
- **[Conditions](conditions.md)** – Discounts, taxes, and shipping
- **[CHIP Gateway Docs](../../chip/docs/payment-gateway.md)** – CHIP-specific implementation

---

**Next Steps:**
- [Configure CHIP credentials](../../chip/README.md)
- [Set up webhooks](../../chip/docs/CHIP_API_REFERENCE.md#collect-webhooks)
- [Handle payment events](events.md)

# Payment Gateway Integration

Connect your cart to any payment gateway using `CheckoutableInterface`.

## Overview

The Cart implements `CheckoutableInterface`, providing standardized checkout data:

```php
$cart->getCheckoutLineItems();  // iterable<LineItemInterface>
$cart->getCheckoutSubtotal();   // Money
$cart->getCheckoutDiscount();   // Money
$cart->getCheckoutTax();        // Money
$cart->getCheckoutTotal();      // Money
$cart->getCheckoutCurrency();   // string
$cart->getCheckoutReference();  // string
$cart->getCheckoutMetadata();   // array
```

## Quick Start with CHIP

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\Chip\Gateways\ChipGateway;
use AIArmada\CommerceSupport\Data\Customer;

// Build cart
Cart::add('product-1', 'Widget', 99.00, 2);
Cart::addDiscount('promo', '-10%');

// Get cart instance
$cart = app(\AIArmada\Cart\Cart::class);

// Create customer
$customer = Customer::fromArray([
    'email' => $request->user()->email,
    'name' => $request->user()->name,
]);

// Create payment
$gateway = app(ChipGateway::class);
$payment = $gateway->createPayment($cart, $customer, [
    'success_url' => route('checkout.success'),
    'failure_url' => route('checkout.failed'),
]);

return redirect($payment->getCheckoutUrl());
```

## Checkout Controller Example

```php
class CheckoutController extends Controller
{
    public function __construct(private ChipGateway $gateway) {}

    public function checkout(Request $request)
    {
        $cart = app(\AIArmada\Cart\Cart::class);
        
        $customer = Customer::fromArray([
            'email' => $request->user()->email,
            'name' => $request->user()->name,
            'phone' => $request->user()->phone,
        ]);
        
        $payment = $this->gateway->createPayment($cart, $customer, [
            'success_url' => route('checkout.success'),
            'failure_url' => route('checkout.failed'),
            'webhook_url' => route('webhooks.chip'),
            'send_receipt' => true,
        ]);
        
        session(['payment_id' => $payment->getId()]);
        
        return redirect($payment->getCheckoutUrl());
    }
    
    public function success()
    {
        $payment = $this->gateway->getPayment(session('payment_id'));
        
        if ($payment->getStatus() === PaymentStatus::PAID) {
            Cart::clear();
            return view('checkout.success', ['payment' => $payment]);
        }
        
        return redirect()->route('checkout.failed');
    }
}
```

## Line Items

Each cart item provides:

```php
foreach ($cart->getCheckoutLineItems() as $item) {
    $item->getLineItemId();       // 'product-1'
    $item->getLineItemName();     // 'Widget'
    $item->getLineItemPrice();    // Money
    $item->getLineItemQuantity(); // 2
    $item->getLineItemTotal();    // Money
    $item->getLineItemMetadata(); // ['sku' => 'WDG-001']
}
```

## Customer Data

```php
use AIArmada\CommerceSupport\Data\Customer;

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
```

## Gateway Operations

### Pre-Authorization

```php
$payment = $gateway->createPayment($cart, $customer, [
    'pre_authorize' => true,
]);

// Later, capture
$captured = $gateway->capturePayment($payment->getId());

// Or partial capture
$partial = $gateway->capturePayment($payment->getId(), Money::MYR(5000));
```

### Refunds

```php
// Full refund
$gateway->refundPayment($paymentId);

// Partial refund
$gateway->refundPayment($paymentId, Money::MYR(2500));
```

### Gateway Capabilities

```php
$gateway->supports('refunds');           // true
$gateway->supports('partial_refunds');   // true
$gateway->supports('pre_authorization'); // true
$gateway->supports('webhooks');          // true
```

## Webhook Handling

```php
Route::post('/webhooks/chip', function (Request $request) {
    $handler = app(ChipGateway::class)->getWebhookHandler();
    $payload = $handler->verify($request);
    
    if ($payload->event === 'purchase.paid') {
        $order = Order::where('payment_reference', $payload->reference)->first();
        $order->markAsPaid();
    }
    
    return response('OK');
});
```

## Using Other Gateways

Any gateway implementing `PaymentGatewayInterface` works identically:

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;

// Bind in service provider
$this->app->bind(PaymentGatewayInterface::class, function ($app) {
    return match (config('payments.default')) {
        'chip' => $app->make(ChipGateway::class),
        'stripe' => $app->make(StripeGateway::class),
        default => throw new \RuntimeException('Unknown gateway'),
    };
});
```

## Testing

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;

it('creates payment from cart', function () {
    Cart::add('product-1', 'Test', 100.00, 1);
    
    $mockPayment = Mockery::mock(PaymentIntentInterface::class);
    $mockPayment->shouldReceive('getId')->andReturn('pay_123');
    $mockPayment->shouldReceive('getCheckoutUrl')->andReturn('https://pay.example.com');
    
    $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
    $mockGateway->shouldReceive('createPayment')->andReturn($mockPayment);
    
    $this->app->instance(PaymentGatewayInterface::class, $mockGateway);
    
    $response = $this->post('/checkout');
    $response->assertRedirect('https://pay.example.com');
});
```

## Next Steps

- [Money & Currency](money-and-currency.md) – Working with prices
- [Cart Operations](cart-operations.md) – Managing items
- [Events](events.md) – Payment events

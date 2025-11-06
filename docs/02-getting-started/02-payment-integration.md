# Getting Started: Payment Integration

Learn how to accept payments using CHIP payment gateway.

## CHIP Overview

CHIP provides two services:
- **CHIP Collect**: Accept payments from customers
- **CHIP Send**: Send money to bank accounts

This guide focuses on CHIP Collect for e-commerce payments.

## Configuration

### 1. Get API Credentials

Sign up at [CHIP](https://www.chip-in.asia/) and obtain:
- Collect API Key
- Brand ID
- Public Key (for webhook verification)

### 2. Configure Environment

```env
CHIP_COLLECT_API_KEY=your-collect-api-key
CHIP_COLLECT_BRAND_ID=your-brand-id
CHIP_COLLECT_ENVIRONMENT=sandbox
CHIP_WEBHOOKS_PUBLIC_KEY=your-public-key
```

### 3. Register Webhook

In CHIP dashboard, register webhook URL:
```
https://your-app.com/webhooks/chip/{webhook_id}
```

Package automatically handles webhook routes.

## Creating Purchases

### Basic Purchase

```php
use AIArmada\Chip\Facades\Chip;

$purchase = Chip::createPurchase([
    'amount' => 10000, // RM 100.00 (in cents)
    'currency' => 'MYR',
    'reference' => 'ORDER-' . uniqid(),
    'client' => [
        'email' => 'customer@example.com',
        'full_name' => 'John Doe',
        'phone' => '+60123456789',
    ],
    'send_receipt' => true,
    'due' => now()->addDays(7),
]);

// Redirect to checkout
return redirect($purchase->checkout_url);
```

### Purchase with Cart Integration

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\Chip\Facades\Chip;

// Cart total
$total = Cart::total();

// Create purchase
$purchase = Chip::createPurchase([
    'amount' => $total->getAmount(),
    'currency' => $total->getCurrency()->getCurrency(),
    'reference' => Cart::identifier(),
    'client' => [
        'email' => auth()->user()->email,
        'full_name' => auth()->user()->name,
    ],
    'success_redirect' => route('checkout.success'),
    'failure_redirect' => route('checkout.failed'),
]);

// Store purchase ID in session
session(['purchase_id' => $purchase->id]);

// Redirect to CHIP checkout
return redirect($purchase->checkout_url);
```

### Advanced Purchase Options

```php
$purchase = Chip::createPurchase([
    'amount' => 50000,
    'currency' => 'MYR',
    'reference' => 'ORDER-12345',
    
    // Client information
    'client' => [
        'email' => 'customer@example.com',
        'full_name' => 'John Doe',
        'phone' => '+60123456789',
        'street_address' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'zip_code' => '50000',
        'country' => 'MY',
    ],
    
    // Payment settings
    'payment_method_whitelist' => ['fpx', 'card'],
    'skip_capture' => false, // Auto-capture payment
    'send_receipt' => true,
    
    // Redirect URLs
    'success_redirect' => route('checkout.success'),
    'failure_redirect' => route('checkout.failed'),
    'success_callback' => route('webhooks.chip.success'),
    
    // Due date
    'due' => now()->addDays(7),
    
    // Products (for receipt)
    'products' => [
        [
            'name' => 'T-Shirt',
            'price' => 2999,
            'quantity' => 1,
        ],
        [
            'name' => 'Jeans',
            'price' => 7999,
            'quantity' => 2,
        ],
    ],
]);
```

## Handling Webhooks

Webhooks notify your app when payment status changes.

### Automatic Webhook Handling

Package automatically verifies signatures and fires events:

```php
// app/Listeners/ChipPurchasePaidListener.php
namespace App\Listeners;

use AIArmada\Chip\Events\PurchasePaid;
use App\Models\Order;

class ChipPurchasePaidListener
{
    public function handle(PurchasePaid $event): void
    {
        $purchase = $event->purchase;
        
        // Find order by reference
        $order = Order::where('reference', $purchase->reference)->first();
        
        if ($order) {
            $order->update([
                'status' => 'paid',
                'payment_id' => $purchase->id,
                'paid_at' => now(),
            ]);
            
            // Send confirmation email
            $order->user->notify(new OrderPaidNotification($order));
            
            // Clear cart
            Cart::instance($purchase->reference)->clear();
        }
    }
}
```

### Register Listener

```php
// app/Providers/EventServiceProvider.php
use AIArmada\Chip\Events\{PurchasePaid, PurchaseFailed};
use App\Listeners\{ChipPurchasePaidListener, ChipPurchaseFailedListener};

protected $listen = [
    PurchasePaid::class => [
        ChipPurchasePaidListener::class,
    ],
    PurchaseFailed::class => [
        ChipPurchaseFailedListener::class,
    ],
];
```

### Available Events

- `PurchaseCreated`: Purchase created
- `PurchasePaid`: Payment successful
- `PurchaseFailed`: Payment failed
- `PurchaseRefunded`: Purchase refunded
- `PurchaseCancelled`: Purchase cancelled

## Success Callback

Handle customer return after payment:

```php
// routes/web.php
Route::get('/checkout/success', function () {
    $purchaseId = session('purchase_id');
    
    if (!$purchaseId) {
        return redirect()->route('cart.index')
            ->with('error', 'Invalid session');
    }
    
    $purchase = Chip::getPurchase($purchaseId);
    
    if ($purchase->status === 'paid') {
        // Find order
        $order = Order::where('reference', $purchase->reference)->first();
        
        return view('checkout.success', [
            'order' => $order,
            'purchase' => $purchase,
        ]);
    }
    
    return redirect()->route('checkout.failed')
        ->with('error', 'Payment not completed');
})->name('checkout.success');
```

## Refunds

### Full Refund

```php
use AIArmada\Chip\Facades\Chip;

$purchase = Chip::refundPurchase('purchase-id');

// Check refund status
if ($purchase->status === 'refunded') {
    // Update order
    $order->update(['status' => 'refunded']);
}
```

### Partial Refund

```php
// Refund RM 50.00 out of RM 100.00
$purchase = Chip::refundPurchase('purchase-id', amount: 5000);
```

### Refund with Metadata

```php
$purchase = Chip::refundPurchase('purchase-id', metadata: [
    'reason' => 'Customer requested',
    'refunded_by' => auth()->id(),
]);
```

## Recurring Payments

### Create Recurring Token

```php
$purchase = Chip::createPurchase([
    'amount' => 4999,
    'currency' => 'MYR',
    'reference' => 'SUBSCRIPTION-' . uniqid(),
    'recurring_token' => true, // Request token
    'client' => [
        'email' => 'customer@example.com',
    ],
]);

// After customer pays, token is available
$token = $purchase->recurring_token;
```

### Charge Recurring Token

```php
// Charge monthly subscription
$purchase = Chip::chargeRecurringToken($token, [
    'amount' => 4999,
    'reference' => 'SUBSCRIPTION-' . now()->format('Y-m'),
]);

if ($purchase->status === 'paid') {
    // Subscription renewed
}
```

### Delete Recurring Token

```php
Chip::deleteRecurringToken('purchase-id');
```

## Testing Payments

### Sandbox Mode

Use sandbox for development:

```env
CHIP_COLLECT_ENVIRONMENT=sandbox
```

### Test Cards

Use CHIP test cards in sandbox:

- **Success**: 4242 4242 4242 4242
- **Declined**: 4000 0000 0000 0002
- **Expired**: Use any past expiry date
- **CVV**: Any 3 digits

### Test FPX

CHIP sandbox provides test bank credentials.

## Complete Checkout Flow

```php
// 1. Display checkout page
Route::get('/checkout', function () {
    if (Cart::count() === 0) {
        return redirect()->route('cart.index');
    }
    
    return view('checkout', [
        'items' => Cart::content(),
        'total' => Cart::total(),
    ]);
})->name('checkout.index');

// 2. Process checkout
Route::post('/checkout', function (Request $request) {
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email',
        'phone' => 'required|string',
    ]);
    
    // Create order
    $order = Order::create([
        'user_id' => auth()->id(),
        'reference' => 'ORD-' . uniqid(),
        'total' => Cart::total()->getAmount(),
        'currency' => 'MYR',
        'status' => 'pending',
        'customer_name' => $validated['name'],
        'customer_email' => $validated['email'],
        'customer_phone' => $validated['phone'],
    ]);
    
    // Save items
    foreach (Cart::content() as $item) {
        $order->items()->create([
            'product_id' => $item->id,
            'name' => $item->name,
            'price' => $item->price,
            'quantity' => $item->quantity,
        ]);
    }
    
    // Create CHIP purchase
    $purchase = Chip::createPurchase([
        'amount' => $order->total,
        'currency' => 'MYR',
        'reference' => $order->reference,
        'client' => [
            'email' => $order->customer_email,
            'full_name' => $order->customer_name,
            'phone' => $order->customer_phone,
        ],
        'success_redirect' => route('checkout.success'),
        'failure_redirect' => route('checkout.failed'),
    ]);
    
    // Store in session
    session(['order_id' => $order->id, 'purchase_id' => $purchase->id]);
    
    // Redirect to CHIP
    return redirect($purchase->checkout_url);
})->name('checkout.process');

// 3. Success callback
Route::get('/checkout/success', function () {
    $orderId = session('order_id');
    $purchaseId = session('purchase_id');
    
    $order = Order::findOrFail($orderId);
    $purchase = Chip::getPurchase($purchaseId);
    
    if ($purchase->status === 'paid') {
        Cart::clear();
        
        return view('checkout.success', compact('order'));
    }
    
    return redirect()->route('checkout.failed');
})->name('checkout.success');
```

## Security Best Practices

1. **Always verify webhook signatures**
   - Package handles this automatically
   - Don't trust webhook data without verification

2. **Use HTTPS in production**
   - CHIP requires HTTPS for webhooks

3. **Store sensitive data securely**
   - Never log full card numbers or tokens
   - Package automatically masks sensitive data

4. **Implement idempotency**
   - Use unique references for each purchase
   - Handle duplicate webhook notifications

## Next Steps

- **[Voucher System](03-voucher-system.md)**: Add discount codes to checkout
- **[CHIP Package Reference](../03-packages/02-chip.md)**: Complete API documentation
- **[Filament CHIP Plugin](../03-packages/08-filament-chip.md)**: Admin panel for managing purchases

---
title: Troubleshooting
---

# Troubleshooting

## Common Issues

### No Payment Gateway Installed

**Error**: `MissingPaymentGatewayException: No payment gateway package is installed`

**Solution**: Install at least one payment gateway:

```bash
composer require aiarmada/chip
# or
composer require aiarmada/cashier-chip
# or
composer require aiarmada/cashier
```

### Session Expired

**Error**: `InvalidCheckoutStateException: Checkout session 'xxx' has expired`

**Causes**:
- Session TTL exceeded (default: 24 hours)
- User abandoned checkout

**Solutions**:
1. Increase session TTL in config:
   ```php
   'defaults' => [
       'session_ttl' => 172800, // 48 hours
   ],
   ```
2. Start a new checkout session

### Empty Cart

**Error**: `InvalidCheckoutStateException: Cart 'xxx' is empty`

**Causes**:
- Cart items were removed
- Cart expired

**Solution**: Ensure cart has items before starting checkout:

```php
$cart = Cart::find($cartId);
if ($cart->isEmpty()) {
    return redirect()->route('cart.index')
        ->with('error', 'Your cart is empty');
}
$session = Checkout::startCheckout($cartId);
```

### Payment Failed

**Error**: `PaymentException: Payment failed`

**Common Causes**:
- Insufficient funds
- Card declined
- Network timeout
- Invalid card details

**Solutions**:
1. Allow payment retry:
   ```php
   $result = Checkout::retryPayment($session);
   ```
2. Log detailed error context:
   ```php
   Log::error('Payment failed', [
       'session_id' => $session->id,
       'context' => $exception->context,
   ]);
   ```

### Inventory Reservation Failed

**Error**: `InventoryException: Insufficient stock for 'xxx'`

**Causes**:
- Item sold out during checkout
- Concurrent purchases depleted stock

**Solutions**:
1. Update cart quantities
2. Suggest alternatives
3. Enable inventory reservation earlier in flow

### Owner Not Resolved

**Error**: `RuntimeException: Checkout owner is enabled but no resolver is bound`

**Solution**: Bind the owner resolver:

```php
// AppServiceProvider.php
$this->app->singleton(
    OwnerResolverInterface::class,
    YourOwnerResolver::class
);
```

Or disable owner mode:

```php
// config/checkout.php
'owner' => [
    'enabled' => false,
],
```

## Debugging

### Enable Debug Logging

```php
// config/logging.php
'channels' => [
    'checkout' => [
        'driver' => 'single',
        'path' => storage_path('logs/checkout.log'),
        'level' => 'debug',
    ],
],
```

### Log Checkout Steps

```php
use AIArmada\Checkout\Events\CheckoutStepCompleted;

Event::listen(CheckoutStepCompleted::class, function ($event) {
    Log::channel('checkout')->debug('Step completed', [
        'session_id' => $event->session->id,
        'step' => $event->stepIdentifier,
        'data' => $event->result->data,
    ]);
});
```

### Inspect Session State

```php
$session = Checkout::resumeCheckout($sessionId);

dd([
    'id' => $session->id,
    'status' => $session->status->value,
    'current_step' => $session->current_step,
    'completed_steps' => $session->completed_steps,
    'payment_attempts' => $session->payment_attempts,
    'metadata' => $session->metadata,
]);
```

### Available Gateways

```php
$resolver = app(PaymentGatewayResolverInterface::class);

dd([
    'available' => $resolver->available(),
    'default' => $resolver->getDefault(),
]);
```

## Testing Tips

### Mock Payment Gateway

```php
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Data\PaymentResult;

$mock = Mockery::mock(PaymentProcessorInterface::class);
$mock->shouldReceive('process')->andReturn(
    PaymentResult::success('test_pay', 'test_txn', 10000)
);

app(PaymentGatewayResolverInterface::class)
    ->register('mock', $mock);
```

### Test Checkout Flow

```php
test('completes checkout successfully', function () {
    $cart = Cart::create([...]);
    $session = Checkout::startCheckout($cart->id);
    
    $result = Checkout::processCheckout($session);
    
    expect($result->success)->toBeTrue()
        ->and($result->orderId)->not->toBeNull();
});
```

### Test Step Failure

```php
test('handles inventory exception gracefully', function () {
    // Create cart with out-of-stock item
    $cart = Cart::create([...]);
    
    $session = Checkout::startCheckout($cart->id);
    
    expect(fn() => Checkout::processCheckout($session))
        ->toThrow(InventoryException::class);
});
```

## Performance

### Slow Checkout

**Symptoms**: Checkout takes > 5 seconds

**Possible Causes**:
1. Slow payment gateway response
2. Complex tax calculations
3. Many cart items

**Solutions**:
1. Implement caching for tax rates:
   ```php
   Cache::remember("tax_rate_{$zoneId}", 3600, fn() => ...);
   ```
2. Optimize database queries
3. Use queued processing for non-critical steps

### Database Deadlocks

**Symptoms**: `Deadlock found when trying to get lock`

**Cause**: Concurrent inventory updates

**Solution**: Use database transactions with retry:

```php
DB::transaction(function () use ($session) {
    // Checkout operations
}, attempts: 3);
```

## Getting Help

If you encounter issues not covered here:

1. Check the [GitHub Issues](https://github.com/aiarmada/checkout/issues)
2. Search error messages in the codebase
3. Enable debug logging and review logs
4. Create a minimal reproduction case

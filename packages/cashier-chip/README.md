# Laravel Cashier CHIP

Laravel Cashier CHIP provides an expressive, fluent interface to [CHIP](https://www.chip-in.asia/) payment services. It handles subscriptions, one-off charges, payment methods, and more, following the Laravel Cashier patterns.

## Installation

Install the package via Composer:

```bash
composer require aiarmada/cashier-chip
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=cashier-chip-config
```

Add the following to your `.env` file:

```env
CHIP_BRAND_ID=your-brand-id
CHIP_SECRET_KEY=your-secret-key
CHIP_WEBHOOK_SECRET=your-webhook-secret
```

Run the migrations:

```bash
php artisan migrate
```

## Billable Model

Add the `Billable` trait to your User model:

```php
use AIArmada\CashierChip\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

## Customer Management

### Creating Customers

```php
// Create a CHIP customer
$user->createAsChipCustomer();

// Create only if not exists
$user->createAsChipCustomerIfNotExists();

// Get CHIP customer ID
$chipId = $user->chipId();

// Check if user is a CHIP customer
if ($user->hasChipId()) {
    // ...
}
```

## One-off Charges

### Simple Charges

```php
// Charge the user 100.00 MYR
$payment = $user->charge(10000); // Amount in cents

// Charge with description
$payment = $user->charge(10000, [
    'reference' => 'Product Purchase',
]);
```

### Checkout Sessions

```php
// Create a checkout session
$checkout = $user->checkout(10000, [
    'reference' => 'Premium Plan',
]);

return $checkout->redirect();
```

### Guest Checkout

```php
use AIArmada\CashierChip\Checkout;

$checkout = Checkout::guest()
    ->addProduct('Product Name', 5000, 2) // name, price in cents, quantity
    ->successUrl('/success')
    ->cancelUrl('/cancel')
    ->create(10000);

return $checkout->redirect();
```

## Payment Methods (Recurring Tokens)

```php
// Get all payment methods
$paymentMethods = $user->paymentMethods();

// Get default payment method
$defaultMethod = $user->defaultPaymentMethod();

// Update default payment method
$user->updateDefaultPaymentMethod($recurringToken);

// Delete a payment method
$user->deletePaymentMethod($recurringToken);

// Check if user has payment method
if ($user->hasDefaultPaymentMethod()) {
    // ...
}
```

### Adding Payment Methods via Checkout

Use zero-amount preauthorization to securely add a payment method without charging:

```php
// Create a setup purchase (zero-amount preauthorization)
$checkout = $user->createSetupPurchase([
    'success_url' => route('billing.payment-methods'),
    'cancel_url' => route('billing.payment-methods'),
]);

// Redirect to CHIP checkout to collect card details
return redirect($checkout->checkout_url);

// Or use the convenience method
$url = $user->setupPaymentMethodUrl([
    'success_url' => route('billing.payment-methods'),
    'cancel_url' => route('billing.payment-methods'),
]);

return redirect($url);
```

This creates a CHIP purchase with:
- `total_override = 0` (zero amount)
- `skip_capture = true` (preauthorization only)  
- `force_recurring = true` (save card for future use)

When the customer completes checkout, the webhook will automatically save the recurring token as a payment method.

## Customer Billing Portal

Unlike Stripe, CHIP doesn't provide a hosted billing portal. The `aiarmada/filament-chip` package provides a self-hosted Filament panel for customer billing management.

### Getting the Portal URL

```php
use AIArmada\Cashier\Facades\Gateway;

// Get the billing portal URL via gateway
$url = Gateway::driver('chip')->customerPortalUrl(
    returnUrl: route('home'),
    options: ['panel_id' => 'billing']
);

return redirect($url);
```

See the [filament-chip documentation](../filament-chip/README.md) for full billing portal setup.

## Subscriptions

### Creating Subscriptions

```php
// Create a subscription
$subscription = $user->newSubscription('default', 'price_monthly')
    ->create();

// With trial period
$subscription = $user->newSubscription('default', 'price_monthly')
    ->trialDays(14)
    ->create();

// With specific trial end date
$subscription = $user->newSubscription('default', 'price_monthly')
    ->trialUntil(now()->addMonth())
    ->create();

// With recurring token
$subscription = $user->newSubscription('default', 'price_monthly')
    ->create($recurringToken);

// Via checkout
$checkout = $user->newSubscription('default', 'price_monthly')
    ->checkout();

return $checkout->redirect();
```

### Checking Subscription Status

```php
if ($user->subscribed('default')) {
    // User has an active subscription...
}

if ($user->subscription('default')->valid()) {
    // Subscription is active, on trial, or on grace period...
}

if ($user->subscription('default')->onTrial()) {
    // Subscription is on trial...
}

if ($user->subscription('default')->canceled()) {
    // Subscription has been canceled...
}

if ($user->subscription('default')->onGracePeriod()) {
    // Subscription is canceled but still in grace period...
}

if ($user->subscription('default')->ended()) {
    // Subscription has completely ended...
}
```

### Subscription Management

```php
// Cancel at end of billing period
$user->subscription('default')->cancel();

// Cancel immediately
$user->subscription('default')->cancelNow();

// Resume a canceled subscription
$user->subscription('default')->resume();

// Swap to a different price
$user->subscription('default')->swap('price_yearly');

// Change quantity
$user->subscription('default')->incrementQuantity();
$user->subscription('default')->decrementQuantity();
$user->subscription('default')->updateQuantity(10);
```

### Multiple Subscriptions

```php
// Create different subscription types
$user->newSubscription('default', 'price_monthly')->create();
$user->newSubscription('swimming', 'price_swimming_monthly')->create();

// Check specific subscription
if ($user->subscribed('swimming')) {
    // ...
}
```

## Billing Intervals

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->monthly() // or ->weekly(), ->yearly(), ->daily()
    ->create();

// Custom interval
$subscription = $user->newSubscription('default', 'price_custom')
    ->billingInterval('week', 2) // Every 2 weeks
    ->create();
```

## Webhooks

The package automatically registers a webhook route at `/chip/webhook`. Configure your CHIP dashboard to send webhooks to this URL.

### Webhook Events

You can listen for the following events:

```php
// In EventServiceProvider
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\SubscriptionCreated;
use AIArmada\CashierChip\Events\SubscriptionCanceled;
use AIArmada\CashierChip\Events\WebhookReceived;
use AIArmada\CashierChip\Events\WebhookHandled;

protected $listen = [
    PaymentSucceeded::class => [
        // Your listeners...
    ],
];
```

### Custom Webhook Handlers

Extend the webhook controller for custom handling:

```php
use AIArmada\CashierChip\Http\Controllers\WebhookController as CashierWebhookController;

class WebhookController extends CashierWebhookController
{
    protected function handlePurchasePaymentSuccess(array $payload): Response
    {
        // Your custom logic...
        
        return parent::handlePurchasePaymentSuccess($payload);
    }
}
```

## Configuration Options

```php
// config/cashier-chip.php

return [
    'path' => env('CASHIER_CHIP_PATH', 'chip'),
    
    'currency' => env('CASHIER_CURRENCY', 'MYR'),
    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'ms_MY'),
    
    'webhooks' => [
        'secret' => env('CHIP_WEBHOOK_SECRET'),
        'verify_signature' => true,
    ],
    
    'success_url' => env('CASHIER_CHIP_SUCCESS_URL'),
    'cancel_url' => env('CASHIER_CHIP_CANCEL_URL'),
];
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
